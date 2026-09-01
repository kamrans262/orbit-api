<?php

declare(strict_types=1);

namespace App\Modules\Admin\Operations\Services;

use App\Models\AdminRecordNote;
use App\Models\AdminRecordTag;
use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Modules\Admin\Services\AdminAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final readonly class AdminAnnotationService
{
    public function __construct(private AdminAuditLogger $audit) {}

    public function addNote(string $targetType, string $targetId, string $note, AdminUser $admin, AdminSession $session, Request $request): AdminRecordNote
    {
        $record = AdminRecordNote::query()->create([
            'id' => (string) Str::uuid7(),
            'admin_user_id' => $admin->id,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'note' => $note,
            'created_at' => now(),
        ]);
        $this->audit->write(
            'admin.annotation.note.created', $admin, $session, $targetType, $targetId,
            after: ['note_id' => $record->id], request: $request,
        );

        return $record;
    }

    public function addTag(string $targetType, string $targetId, string $tag, AdminUser $admin, AdminSession $session, Request $request): AdminRecordTag
    {
        $record = AdminRecordTag::query()->firstOrCreate(
            ['target_type' => $targetType, 'target_id' => $targetId, 'tag' => $tag],
            ['id' => (string) Str::uuid7(), 'admin_user_id' => $admin->id, 'created_at' => now()],
        );
        if ($record->wasRecentlyCreated) {
            $this->audit->write(
                'admin.annotation.tag.created', $admin, $session, $targetType, $targetId,
                after: ['tag_id' => $record->id, 'tag' => $tag], request: $request,
            );
        }

        return $record;
    }

    public function removeTag(string $targetType, string $targetId, string $tagId, AdminUser $admin, AdminSession $session, Request $request): bool
    {
        $tag = AdminRecordTag::query()->whereKey($tagId)->where('target_type', $targetType)->where('target_id', $targetId)->first();
        if ($tag === null) {
            return false;
        }
        $before = ['tag_id' => $tag->id, 'tag' => $tag->tag];
        $tag->delete();
        $this->audit->write(
            'admin.annotation.tag.removed', $admin, $session, $targetType, $targetId,
            before: $before, request: $request,
        );

        return true;
    }
}
