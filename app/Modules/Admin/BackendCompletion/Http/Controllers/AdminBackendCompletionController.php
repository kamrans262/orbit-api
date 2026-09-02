<?php

declare(strict_types=1);

namespace App\Modules\Admin\BackendCompletion\Http\Controllers;

use App\Models\AdminDashboardPreference;
use App\Models\AdminIpPolicy;
use App\Models\AdminSavedView;
use App\Modules\Admin\BackendCompletion\Services\AdminDashboardService;
use App\Modules\Admin\BackendCompletion\Services\AdminGlobalSearchService;
use App\Modules\Admin\BackendCompletion\Services\AdminIpPolicyService;
use App\Modules\Admin\BackendCompletion\Services\AdminSavedViewService;
use App\Modules\Admin\BackendCompletion\Services\ReleaseReadinessService;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

final class AdminBackendCompletionController
{
    public function dashboard(Request $request, AdminDashboardService $service): JsonResponse
    {
        $preference = AdminDashboardPreference::query()->find($request->user()->id);

        return AdminApiResponse::success($request, [
            'snapshot' => $service->snapshot(),
            'layout' => $preference?->layout ?? [],
        ]);
    }

    public function updateDashboardLayout(Request $request, AdminAuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'layout' => ['required', 'array', 'max:40'],
            'layout.*.key' => ['required', 'string', 'max:80'],
            'layout.*.visible' => ['required', 'boolean'],
            'layout.*.position' => ['required', 'integer', 'min:0', 'max:100'],
            'layout.*.size' => ['nullable', Rule::in(['small', 'medium', 'large'])],
        ]);
        $row = AdminDashboardPreference::query()->updateOrCreate(
            ['admin_user_id' => $request->user()->id],
            ['layout' => array_values($data['layout'])],
        );
        $audit->write('admin.dashboard.layout.updated', $request->user(), $request->attributes->get('admin_session'), 'admin_dashboard_preference', (string) $request->user()->id, after: ['widget_count' => count($data['layout'])], request: $request);

        return AdminApiResponse::success($request, $row->layout);
    }

    public function search(Request $request, AdminGlobalSearchService $service): JsonResponse
    {
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:100'], 'limit' => ['nullable', 'integer', 'min:1', 'max:20']]);

        return AdminApiResponse::success($request, $service->search($request->user(), trim($data['q']), (int) ($data['limit'] ?? 8)));
    }

    public function views(Request $request, AdminSavedViewService $service): JsonResponse
    {
        $data = $request->validate(['module' => ['nullable', 'string', 'max:60']]);

        return AdminApiResponse::success($request, $service->visible($request->user(), $data['module'] ?? null)->toArray());
    }

    public function createView(Request $request, AdminSavedViewService $service, AdminAuditLogger $audit): JsonResponse
    {
        $data = $this->viewData($request);
        if (! $service->canAccessModule($request->user(), (string) $data['module'])) {
            return AdminApiResponse::error($request, 'You do not have permission to access this saved-view module.', 'ADMIN_FORBIDDEN', 403);
        }
        if (($data['scope'] ?? 'personal') === 'team' && ! $request->user()->hasPermission('views.share')) {
            return AdminApiResponse::error($request, 'You do not have permission to create team views.', 'ADMIN_FORBIDDEN', 403);
        }
        try {
            $view = $service->create($request->user(), $data);
        } catch (InvalidArgumentException $exception) {
            return AdminApiResponse::error($request, $exception->getMessage(), 'SAVED_VIEW_INVALID', 422);
        }
        $audit->write('admin.saved_view.created', $request->user(), $request->attributes->get('admin_session'), 'admin_saved_view', $view->id, after: ['module' => $view->module, 'scope' => $view->scope], request: $request);

        return AdminApiResponse::success($request, $view->toArray(), 201);
    }

    public function updateView(Request $request, string $id, AdminSavedViewService $service, AdminAuditLogger $audit): JsonResponse
    {
        $view = AdminSavedView::query()->find($id);
        if (! $view) {
            return AdminApiResponse::error($request, 'Saved view not found.', 'SAVED_VIEW_NOT_FOUND', 404);
        }
        $data = $this->viewData($request, false);
        $effectiveModule = (string) ($data['module'] ?? $view->module);
        if (! $service->canAccessModule($request->user(), $effectiveModule)) {
            return AdminApiResponse::error($request, 'You do not have permission to access this saved-view module.', 'ADMIN_FORBIDDEN', 403);
        }
        if (($data['scope'] ?? $view->scope) === 'team' && ! $request->user()->hasPermission('views.share')) {
            return AdminApiResponse::error($request, 'You do not have permission to create team views.', 'ADMIN_FORBIDDEN', 403);
        }
        try {
            $view = $service->update($view, $request->user(), $data);
        } catch (InvalidArgumentException $exception) {
            return AdminApiResponse::error($request, $exception->getMessage(), 'SAVED_VIEW_FORBIDDEN', 403);
        }
        $audit->write('admin.saved_view.updated', $request->user(), $request->attributes->get('admin_session'), 'admin_saved_view', $view->id, after: ['module' => $view->module, 'scope' => $view->scope], request: $request);

        return AdminApiResponse::success($request, $view->toArray());
    }

    public function deleteView(Request $request, string $id, AdminSavedViewService $service, AdminAuditLogger $audit): JsonResponse
    {
        $view = AdminSavedView::query()->find($id);
        if (! $view) {
            return AdminApiResponse::error($request, 'Saved view not found.', 'SAVED_VIEW_NOT_FOUND', 404);
        }
        try {
            $service->delete($view, $request->user());
        } catch (InvalidArgumentException $exception) {
            return AdminApiResponse::error($request, $exception->getMessage(), 'SAVED_VIEW_FORBIDDEN', 403);
        }
        $audit->write('admin.saved_view.deleted', $request->user(), $request->attributes->get('admin_session'), 'admin_saved_view', $id, request: $request);

        return AdminApiResponse::success($request, ['deleted' => true]);
    }

    public function readiness(Request $request, ReleaseReadinessService $service): JsonResponse
    {
        return AdminApiResponse::success($request, $service->audit());
    }

    public function ipPolicies(Request $request): JsonResponse
    {
        $data = $request->validate(['admin_user_id' => ['nullable', 'integer', 'exists:admin_users,id']]);
        $query = AdminIpPolicy::query()->latest();
        if (isset($data['admin_user_id'])) {
            $query->where('admin_user_id', $data['admin_user_id']);
        }

        return AdminApiResponse::success($request, $query->limit(200)->get()->toArray());
    }

    public function createIpPolicy(Request $request, AdminIpPolicyService $service, AdminAuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'admin_user_id' => ['required', 'integer', 'exists:admin_users,id'],
            'cidr' => ['required', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:200'],
            'enabled' => ['nullable', 'boolean'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);
        if (! $service->validCidr($data['cidr'])) {
            return AdminApiResponse::error($request, 'CIDR is not a valid IPv4 or IPv6 network.', 'ADMIN_IP_CIDR_INVALID', 422);
        }
        $row = AdminIpPolicy::query()->firstOrCreate(
            ['admin_user_id' => $data['admin_user_id'], 'cidr' => trim($data['cidr'])],
            ['description' => $data['description'] ?? null, 'enabled' => (bool) ($data['enabled'] ?? true), 'created_by_admin_id' => $request->user()->id],
        );
        $audit->write('admin.security.ip_policy.created', $request->user(), $request->attributes->get('admin_session'), 'admin_user', (string) $data['admin_user_id'], reason: $data['reason'], after: ['policy_id' => $row->id, 'cidr' => $row->cidr, 'enabled' => $row->enabled], request: $request);

        return AdminApiResponse::success($request, $row->toArray(), 201);
    }

    public function deleteIpPolicy(Request $request, string $id, AdminAuditLogger $audit): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);
        $row = AdminIpPolicy::query()->find($id);
        if (! $row) {
            return AdminApiResponse::error($request, 'IP policy not found.', 'ADMIN_IP_POLICY_NOT_FOUND', 404);
        }
        $target = (string) $row->admin_user_id;
        $before = ['cidr' => $row->cidr, 'enabled' => $row->enabled];
        $row->delete();
        $audit->write('admin.security.ip_policy.deleted', $request->user(), $request->attributes->get('admin_session'), 'admin_user', $target, reason: $data['reason'], before: $before, request: $request);

        return AdminApiResponse::success($request, ['deleted' => true]);
    }

    private function viewData(Request $request, bool $create = true): array
    {
        return $request->validate([
            'name' => [$create ? 'required' : 'sometimes', 'string', 'max:120'],
            'module' => [$create ? 'required' : 'sometimes', 'string', 'max:60'],
            'scope' => ['sometimes', Rule::in(['personal', 'team'])],
            'filters' => ['sometimes', 'array'],
            'columns' => ['sometimes', 'array', 'max:100'],
            'sort' => ['sometimes', 'array'],
        ]);
    }
}
