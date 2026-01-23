<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class TwoFactorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->two_factor_secret) {
            return response()->json([
                'data' => [
                    'enabled' => false,
                    'has_recovery_codes' => false,
                ],
            ]);
        }

        return response()->json([
            'data' => [
                'enabled' => ! is_null($user->two_factor_secret),
                'has_recovery_codes' => ! is_null($user->two_factor_recovery_codes),
                'qr_url' => null,
            ],
        ]);
    }

    public function setup(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'password' => ['required', 'string', 'current_password'],
            'code' => ['nullable', 'string', 'size:6', 'alpha_dash'],
        ]);

        if (! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect',
            ], 422);
        }

        $tfa = new PragmaRX\Google2FA\Google2FA($user->email);

        $secret = $tfa->createSecret();
        $recoveryCodes = $tfa->generateRecoveryCodes(8);

        $user->update([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $recoveryCodes,
        ]);

        return response()->json([
            'message' => 'Two-factor authentication enabled',
            'data' => [
                'recovery_codes' => $recoveryCodes,
            ],
        ]);
    }

    public function verify(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $tfa = new PragmaRX\Google2FA($user->email);

        if (! $tfa->verifyCode($user->two_factor_secret, $validated['code'])) {
            $user->update(['two_factor_confirmed_at' => null]);

            return response()->json([
                'message' => 'Invalid two-factor code',
            ], 401);
        }

        $user->update([
            'two_factor_confirmed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Two-factor authentication verified',
        ]);
    }

    public function disable(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'password' => ['required', 'string', 'current_password'],
        ]);

        if (! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect',
            ], 422);
        }

        $user->update([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);

        return response()->json([
            'message' => 'Two-factor authentication disabled',
        ]);
    }

    public function generateRecoveryCodes(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'password' => ['required', 'string', 'current_password'],
        ]);

        if (! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'pragmaRX\Google2FA\Google2FA($user->email): Current password is incorrect',
            ], 422);
        }

        $tfa = new PragmaRX\Google2FA($user->email);

        $recoveryCodes = $tfa->generateRecoveryCodes(8);

        $user->update([
            'two_factor_recovery_codes' => $recoveryCodes,
        ]);

        return response()->json([
            'message' => 'New recovery codes generated',
            'data' => [
                'recovery_codes' => $recoveryCodes,
            ],
        ]);
    }
}
