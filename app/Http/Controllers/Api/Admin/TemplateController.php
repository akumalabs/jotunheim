<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Node;
use App\Models\Template;
use App\Models\TemplateGroup;
use App\Services\Proxmox\ProxmoxApiClient;
use App\Services\Proxmox\ProxmoxApiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TemplateController extends Controller
{
    /**
     * List all template groups with templates.
     */
    public function index(Request $request): JsonResponse
    {
        $query = TemplateGroup::with('templates')
            ->withCount('templates');

        if ($request->has('node_id')) {
            $query->where('node_id', $request->node_id);
        }

        $groups = $query->orderBy('order')->get()
            ->map(fn ($group) => $this->formatGroup($group));

        return response()->json([
            'data' => $groups,
        ]);
    }

    /**
     * Get templates for a specific node.
     */
    public function byNode(Node $node): JsonResponse
    {
        $groups = $node->templateGroups()
            ->with('templates')
            ->orderBy('order')
            ->get()
            ->map(fn ($group) => $this->formatGroup($group));

        return response()->json([
            'data' => $groups,
        ]);
    }

    /**
     * Create a template group.
     */
    public function storeGroup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'node_id' => ['required', 'exists:nodes,id'],
            'name' => ['required', 'string', 'max:255'],
            'order' => ['sometimes', 'integer', 'min:0'],
            'visible' => ['sometimes', 'boolean'],
        ]);

        $group = TemplateGroup::create($validated);

        return response()->json([
            'message' => 'Template group created',
            'data' => $this->formatGroup($group),
        ], 201);
    }

    /**
     * Update a template group.
     */
    public function updateGroup(Request $request, TemplateGroup $group): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'order' => ['sometimes', 'integer', 'min:0'],
            'visible' => ['sometimes', 'boolean'],
        ]);

        $group->update($validated);

        return response()->json([
            'message' => 'Template group updated',
            'data' => $this->formatGroup($group),
        ]);
    }

    /**
     * Delete a template group.
     */
    public function destroyGroup(TemplateGroup $group): JsonResponse
    {
        $group->delete(); // Cascades to templates

        return response()->json([
            'message' => 'Template group deleted',
        ]);
    }

    /**
     * Create a template.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'template_group_id' => ['required', 'exists:template_groups,id'],
            'name' => ['required', 'string', 'max:255'],
            'vmid' => ['required', 'string'],
            'min_cpu' => ['sometimes', 'integer', 'min:1'],
            'min_memory' => ['sometimes', 'integer', 'min:0'],
            'min_disk' => ['sometimes', 'integer', 'min:0'],
            'order' => ['sometimes', 'integer', 'min:0'],
            'visible' => ['sometimes', 'boolean'],
        ]);

        $template = Template::create($validated);

        return response()->json([
            'message' => 'Template created',
            'data' => $this->formatTemplate($template),
        ], 201);
    }

    /**
     * Update a template.
     */
    public function update(Request $request, Template $template): JsonResponse
    {
        $validated = $request->validate([
            'template_group_id' => ['sometimes', 'exists:template_groups,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'vmid' => ['sometimes', 'string'],
            'min_cpu' => ['sometimes', 'integer', 'min:1'],
            'min_memory' => ['sometimes', 'integer', 'min:0'],
            'min_disk' => ['sometimes', 'integer', 'min:0'],
            'order' => ['sometimes', 'integer', 'min:0'],
            'visible' => ['sometimes', 'boolean'],
        ]);

        $template->update($validated);

        return response()->json([
            'message' => 'Template updated',
            'data' => $this->formatTemplate($template),
        ]);
    }

    /**
     * Delete a template.
     */
    public function destroy(Template $template): JsonResponse
    {
        $template->delete();

        return response()->json([
            'message' => 'Template deleted',
        ]);
    }

    /**
     * Sync templates from Proxmox.
     */
    public function sync(Node $node): JsonResponse
    {
        try {
            $client = new ProxmoxApiClient($node);
            $proxmoxTemplates = $client->getTemplates();

            // Create or update default group
            $group = TemplateGroup::firstOrCreate(
                ['node_id' => $node->id, 'name' => 'Imported Templates'],
                ['order' => 0, 'visible' => true]
            );

            $synced = [];
            foreach ($proxmoxTemplates as $pTemplate) {
                // Fetch VM config to get actual specs
                $vmid = (int) $pTemplate['vmid'];
                $config = [];
                $minCpu = 1;
                $minMemory = 536870912; // 512MB default
                $minDisk = 1073741824; // 1GB default

                try {
                    $config = $client->getVMConfig($vmid);

                    // Extract CPU cores
                    $minCpu = $config['cores'] ?? $config['sockets'] ?? 1;

                    // Extract memory (Proxmox returns in MB)
                    if (isset($config['memory'])) {
                        $minMemory = (int) $config['memory'] * 1024 * 1024; // Convert MB to bytes
                    }

                    // Extract disk size - look for scsi0, virtio0, ide0, etc.
                    foreach (['scsi0', 'virtio0', 'ide0', 'sata0'] as $diskKey) {
                        if (isset($config[$diskKey]) && preg_match('/size=(\d+)([GMT])/', $config[$diskKey], $matches)) {
                            $size = (int) $matches[1];
                            $unit = $matches[2];
                            $minDisk = match ($unit) {
                                'T' => $size * 1024 * 1024 * 1024 * 1024,
                                'G' => $size * 1024 * 1024 * 1024,
                                'M' => $size * 1024 * 1024,
                                default => $size,
                            };
                            break;
                        }
                    }
                } catch (\Exception $e) {
                    // If we can't get config, use defaults
                }

                $template = Template::updateOrCreate(
                    ['template_group_id' => $group->id, 'vmid' => (string) $vmid],
                    [
                        'name' => $pTemplate['name'] ?? "Template {$vmid}",
                        'min_cpu' => $minCpu,
                        'min_memory' => $minMemory,
                        'min_disk' => $minDisk,
                        'visible' => true,
                    ]
                );
                $synced[] = [
                    'name' => $template->name,
                    'min_cpu' => $minCpu,
                    'min_memory' => round($minMemory / 1024 / 1024).' MB',
                    'min_disk' => round($minDisk / 1024 / 1024 / 1024).' GB',
                ];
            }

            return response()->json([
                'message' => 'Templates synced from Proxmox',
                'data' => [
                    'count' => count($synced),
                    'templates' => $synced,
                ],
            ]);

        } catch (ProxmoxApiException $e) {
            return response()->json([
                'message' => 'Failed to sync templates',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Format template group for response.
     */
    protected function formatGroup(TemplateGroup $group): array
    {
        return [
            'id' => $group->id,
            'node_id' => $group->node_id,
            'name' => $group->name,
            'order' => $group->order,
            'visible' => $group->visible,
            'templates_count' => $group->templates_count ?? $group->templates->count(),
            'templates' => $group->templates->map(fn ($t) => $this->formatTemplate($t)),
        ];
    }

    /**
     * Format template for response.
     */
    protected function formatTemplate(Template $template): array
    {
        return [
            'id' => $template->id,
            'uuid' => $template->uuid,
            'name' => $template->name,
            'vmid' => $template->vmid,
            'min_cpu' => $template->min_cpu ?? 1,
            'min_memory' => $template->min_memory ?? 536870912,
            'min_disk' => $template->min_disk ?? 1073741824,
            'order' => $template->order,
            'visible' => $template->visible,
        ];
    }
}
