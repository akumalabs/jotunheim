<?php

namespace App\Policies;

use App\Models\Server;
use App\Models\User;

class ServerPolicy
{
    /**
     * Determine if user can view any servers.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('server.view.own') || $user->hasPermission('server.view.all');
    }

    /**
     * Determine if user can view the server.
     */
    public function view(User $user, Server $server): bool
    {
        if ($user->hasPermission('server.view.all')) {
            return true;
        }

        return $user->hasPermission('server.view.own') && $server->user_id === $user->id;
    }

    /**
     * Determine if user can create servers.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('server.create');
    }

    /**
     * Determine if user can update the server.
     */
    public function update(User $user, Server $server): bool
    {
        if ($user->hasPermission('server.edit.all')) {
            return true;
        }

        return $user->hasPermission('server.edit.own') && $server->user_id === $user->id;
    }

    /**
     * Determine if user can delete the server.
     */
    public function delete(User $user, Server $server): bool
    {
        if ($user->hasPermission('server.delete.all')) {
            return true;
        }

        return $user->hasPermission('server.delete.own') && $server->user_id === $user->id;
    }

    /**
     * Determine if user can rebuild the server.
     */
    public function rebuild(User $user, Server $server): bool
    {
        return $user->hasPermission('server.rebuild') && 
               ($user->hasPermission('server.edit.all') || $server->user_id === $user->id);
    }

    /**
     * Determine if user can control server power.
     */
    public function power(User $user, Server $server): bool
    {
        return $user->hasPermission('server.power') && 
               ($user->hasPermission('server.view.all') || $server->user_id === $user->id);
    }

    /**
     * Determine if user can access console.
     */
    public function console(User $user, Server $server): bool
    {
        return $user->hasPermission('server.console') && 
               ($user->hasPermission('server.view.all') || $server->user_id === $user->id);
    }

    /**
     * Determine if user can reset password.
     */
    public function resetPassword(User $user, Server $server): bool
    {
        return $user->hasPermission('server.reset-password') && 
               ($user->hasPermission('server.edit.all') || $server->user_id === $user->id);
    }
}
