<?php

declare(strict_types=1);

namespace App\Modules\Admin\CommunicationsContent\Services;

use App\Models\Announcement;
use App\Models\AnnouncementTranslation;
use App\Models\ContentItem;
use App\Models\ContentTranslation;
use App\Models\LegalAcceptance;
use App\Models\LegalDocument;
use App\Models\LegalDocumentTranslation;
use App\Models\User;

final readonly class ConsumerContentService
{
    public function __construct(private AudienceMatcher $audience) {}

    public function announcements(User $user, string $locale): array
    {
        return Announcement::query()
            ->where('status', 'published')
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->latest('priority')
            ->get()
            ->filter(fn (Announcement $item): bool => $this->audience->matches($user, $item->audience ?? []))
            ->map(function (Announcement $item) use ($locale): array {
                $row = AnnouncementTranslation::query()->where('announcement_id', $item->id)->where('status', 'published')->where('locale', $locale)->first()
                    ?? AnnouncementTranslation::query()->where('announcement_id', $item->id)->where('status', 'published')->where('locale', 'en')->first();

                return [
                    'id' => $item->id,
                    'type' => $item->type,
                    'priority' => $item->priority,
                    'dismissible' => (bool) $item->dismissible,
                    'deep_link' => $item->deep_link,
                    'title' => $row?->title,
                    'body' => $row?->body,
                    'starts_at' => $item->starts_at?->toIso8601String(),
                    'ends_at' => $item->ends_at?->toIso8601String(),
                ];
            })->values()->all();
    }

    public function content(string $slug, string $locale, ?string $country): ?array
    {
        $item = ContentItem::query()->where('slug', $slug)->where('status', 'published')->first();
        if (! $item || ! $this->regionAllowed($item->regions ?? [], $country)) {
            return null;
        }
        $row = ContentTranslation::query()->where('content_item_id', $item->id)->where('status', 'published')->where('locale', $locale)->first()
            ?? ContentTranslation::query()->where('content_item_id', $item->id)->where('status', 'published')->where('locale', 'en')->first();
        if (! $row) {
            return null;
        }

        return ['id' => $item->id, 'type' => $item->type, 'slug' => $item->slug, 'title' => $row->title, 'body' => $row->body, 'metadata' => $row->metadata ?? []];
    }

    public function legal(User $user, string $locale, ?string $country): array
    {
        return LegalDocument::query()
            ->where('status', 'published')
            ->where(fn ($q) => $q->whereNull('effective_at')->orWhere('effective_at', '<=', now()))
            ->latest('effective_at')
            ->get()
            ->filter(fn (LegalDocument $doc): bool => $this->regionAllowed($doc->regions ?? [], $country))
            ->map(function (LegalDocument $doc) use ($user, $locale): array {
                $row = LegalDocumentTranslation::query()->where('legal_document_id', $doc->id)->where('status', 'published')->where('locale', $locale)->first()
                    ?? LegalDocumentTranslation::query()->where('legal_document_id', $doc->id)->where('status', 'published')->where('locale', 'en')->first();

                return [
                    'id' => $doc->id,
                    'document_type' => $doc->document_type,
                    'version' => $doc->version,
                    'title' => $row?->title,
                    'body' => $row?->body,
                    'effective_at' => $doc->effective_at?->toIso8601String(),
                    'requires_reacceptance' => (bool) $doc->requires_reacceptance,
                    'accepted' => LegalAcceptance::query()->where('legal_document_id', $doc->id)->where('user_id', $user->getKey())->exists(),
                ];
            })->values()->all();
    }

    private function regionAllowed(array $regions, ?string $country): bool
    {
        if ($regions === []) {
            return true;
        }
        if (! $country) {
            return false;
        }

        return in_array(strtoupper($country), array_map(fn ($v): string => strtoupper((string) $v), $regions), true);
    }
}
