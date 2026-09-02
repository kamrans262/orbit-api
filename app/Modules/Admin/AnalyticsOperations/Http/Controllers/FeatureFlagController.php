<?php

declare(strict_types=1);

namespace App\Modules\Admin\AnalyticsOperations\Http\Controllers;

use App\Models\FeatureFlag;
use App\Models\User;
use App\Modules\Admin\AnalyticsOperations\Services\FeatureFlagService;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\Request;

final class FeatureFlagController
{
    public function index(Request $r)
    {
        return AdminApiResponse::success($r, ['items' => FeatureFlag::query()->orderBy('key')->paginate(min(100, max(1, (int) $r->integer('per_page', 25))))->items()]);
    }

    public function create(Request $r, AdminAuditLogger $a)
    {
        $d = $r->validate(['key' => 'required|alpha_dash|max:120|unique:feature_flags,key', 'name' => 'required|string|max:160', 'description' => 'nullable|string|max:1000', 'environment' => 'nullable|string|max:24', 'status' => 'nullable|in:enabled,disabled', 'default_enabled' => 'nullable|boolean', 'rollout_percentage' => 'nullable|integer|min:0|max:100', 'targeting' => 'nullable|array', 'starts_at' => 'nullable|date', 'ends_at' => 'nullable|date|after:starts_at', 'removal_at' => 'nullable|date']);
        $f = FeatureFlag::query()->create([...$d, 'owner_admin_id' => $r->user()->id, 'updated_by_admin_id' => $r->user()->id]);
        $a->write('feature_flag.created', $r->user(), $r->attributes->get('admin_session'), 'feature_flag', $f->id, request: $r, after: $f->toArray());

        return AdminApiResponse::success($r, $f, 201);
    }

    public function update(Request $r, string $id, AdminAuditLogger $a)
    {
        $f = FeatureFlag::query()->findOrFail($id);
        $d = $r->validate(['name' => 'sometimes|string|max:160', 'description' => 'nullable|string|max:1000', 'status' => 'sometimes|in:enabled,disabled', 'default_enabled' => 'sometimes|boolean', 'rollout_percentage' => 'sometimes|integer|min:0|max:100', 'targeting' => 'nullable|array', 'starts_at' => 'nullable|date', 'ends_at' => 'nullable|date', 'removal_at' => 'nullable|date', 'archive' => 'nullable|boolean', 'reason' => 'required|string|min:4|max:500']);
        $before = $f->toArray();
        if (($d['archive'] ?? false) === true) {
            $d['archived_at'] = now();
        }unset($d['archive'],$d['reason']);
        $d['updated_by_admin_id'] = $r->user()->id;
        $f->fill($d)->save();
        $a->write('feature_flag.updated', $r->user(), $r->attributes->get('admin_session'), 'feature_flag', $f->id, reason: $r->input('reason'), request: $r, before: $before, after: $f->fresh()->toArray());

        return AdminApiResponse::success($r, $f->fresh());
    }

    public function clone(Request $r, string $id, AdminAuditLogger $a)
    {
        $source = FeatureFlag::query()->findOrFail($id);
        $d = $r->validate(['key' => 'required|alpha_dash|max:120|unique:feature_flags,key', 'name' => 'required|string|max:160', 'reason' => 'required|string|min:4|max:500']);
        $copy = $source->replicate(['key', 'name', 'owner_admin_id', 'updated_by_admin_id', 'archived_at']);
        $copy->key = $d['key'];
        $copy->name = $d['name'];
        $copy->status = 'disabled';
        $copy->rollout_percentage = 0;
        $copy->owner_admin_id = $r->user()->id;
        $copy->updated_by_admin_id = $r->user()->id;
        $copy->save();
        $a->write('feature_flag.cloned', $r->user(), $r->attributes->get('admin_session'), 'feature_flag', $copy->id, reason: $d['reason'], metadata: ['source_flag_id' => $source->id], request: $r);

        return AdminApiResponse::success($r, $copy, 201);
    }

    public function evaluate(Request $r, FeatureFlagService $s)
    {
        $u = User::query()->findOrFail((int) $r->route('userId'));

        return AdminApiResponse::success($r,['flags' => $s->evaluated($u,(string) $r->input('environment','production'))]);
    }
}
