<?php

declare(strict_types=1);

namespace App\Modules\Admin\CommunicationsContent\Services;

use App\Models\AdminUser;
use App\Models\Announcement;
use App\Models\AnnouncementTranslation;
use App\Models\CommunicationTemplate;
use App\Models\CommunicationTemplateTranslation;
use App\Models\ContentItem;
use App\Models\ContentTranslation;
use App\Models\LegalDocument;
use App\Models\LegalDocumentTranslation;
use App\Modules\Admin\CommunicationsContent\Exceptions\CommunicationsContentException;
use Illuminate\Database\Eloquent\Model;

final class PublicationService
{
    public function translation(string $kind, string $id, string $locale, array $data, AdminUser $admin): Model
    {
        [$entityClass, $translationClass, $foreignKey] = $this->types($kind);
        $entity = $entityClass::query()->find($id);
        if (! $entity) {
            throw new CommunicationsContentException('CONTENT_NOT_FOUND', 'The requested content record was not found.', 404);
        }

        $payload = [
            'status' => $data['status'] ?? 'draft',
            'title' => $data['title'] ?? null,
            'body' => $data['body'],
        ];
        if ($kind === 'template') {
            $payload['subject'] = $data['subject'] ?? null;
        }
        if ($kind === 'content') {
            $payload['metadata'] = $data['metadata'] ?? null;
        }
        if (($data['status'] ?? 'draft') === 'review') {
            $payload['reviewed_by_admin_id'] = $admin->id;
            $payload['reviewed_at'] = now();
        }

        return $translationClass::query()->updateOrCreate(
            [$foreignKey => $id, 'locale' => strtolower($locale)],
            $payload,
        );
    }

    public function publish(string $kind, string $id, ?AdminUser $admin): Model
    {
        [$entityClass, $translationClass, $foreignKey] = $this->types($kind);
        $entity = $entityClass::query()->find($id);
        if (! $entity) {
            throw new CommunicationsContentException('CONTENT_NOT_FOUND', 'The requested content record was not found.', 404);
        }
        $translations = $translationClass::query()->where($foreignKey, $id)->whereIn('status', ['review', 'published'])->get();
        if ($translations->isEmpty()) {
            throw new CommunicationsContentException('CONTENT_REVIEW_REQUIRED', 'At least one reviewed translation is required before publication.');
        }
        foreach ($translations as $translation) {
            $translation->forceFill(['status' => 'published', 'published_at' => now()])->save();
        }
        $entity->forceFill([
            'status' => 'published',
            'published_by_admin_id' => $admin?->id,
            'published_at' => now(),
        ])->save();

        return $entity->refresh();
    }

    public function scheduleContent(ContentItem $item, AdminUser $admin): ContentItem
    {
        $translations = ContentTranslation::query()
            ->where('content_item_id', $item->id)
            ->whereIn('status', ['review', 'published'])
            ->count();

        if ($translations === 0) {
            throw new CommunicationsContentException('CONTENT_REVIEW_REQUIRED', 'At least one reviewed translation is required before scheduling.');
        }
        if (! $item->scheduled_at || $item->scheduled_at->isPast()) {
            /** @var ContentItem $published */
            $published = $this->publish('content', $item->id, $admin);

            return $published;
        }

        $item->forceFill(['status' => 'scheduled'])->save();

        return $item->refresh();
    }

    /** @return array{0:class-string<Model>,1:class-string<Model>,2:string} */
    private function types(string $kind): array
    {
        return match ($kind) {
            'template' => [CommunicationTemplate::class, CommunicationTemplateTranslation::class, 'template_id'],
            'announcement' => [Announcement::class, AnnouncementTranslation::class, 'announcement_id'],
            'content' => [ContentItem::class, ContentTranslation::class, 'content_item_id'],
            'legal' => [LegalDocument::class, LegalDocumentTranslation::class, 'legal_document_id'],
            default => throw new CommunicationsContentException('CONTENT_TYPE_INVALID', 'Unsupported content type.', 422),
        };
    }
}
