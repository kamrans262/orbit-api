<?php

declare(strict_types=1);

namespace App\Modules\Admin\CommunicationsContent\Services;

use App\Models\CommunicationTemplate;
use App\Models\CommunicationTemplateTranslation;
use App\Modules\Admin\CommunicationsContent\Exceptions\CommunicationsContentException;

final class TemplateRenderingService
{
    public function render(CommunicationTemplate $template, string $locale, array $variables): array
    {
        $translation = CommunicationTemplateTranslation::query()
            ->where('template_id', $template->id)
            ->where('locale', strtolower($locale))
            ->first()
            ?? CommunicationTemplateTranslation::query()->where('template_id', $template->id)->where('locale', 'en')->first();

        if (! $translation) {
            throw new CommunicationsContentException('TEMPLATE_TRANSLATION_MISSING', 'No translation is available for this template.', 422);
        }

        $declared = collect($template->variables ?? [])->map(fn ($v): string => is_array($v) ? (string) ($v['name'] ?? '') : (string) $v)->filter()->unique()->values()->all();
        $missing = array_values(array_filter($declared, fn (string $name): bool => ! array_key_exists($name, $variables)));
        if ($missing !== []) {
            throw new CommunicationsContentException('TEMPLATE_VARIABLES_MISSING', 'Required template variables are missing: '.implode(', ', $missing).'.', 422);
        }

        $render = function (?string $text) use ($variables): ?string {
            if ($text === null) {
                return null;
            }
            foreach ($variables as $key => $value) {
                if (is_scalar($value) || $value === null) {
                    $text = preg_replace('/{{\s*'.preg_quote((string) $key, '/').'\s*}}/', (string) $value, $text) ?? $text;
                }
            }

            return $text;
        };

        return [
            'subject' => $render($translation->subject),
            'title' => $render($translation->title),
            'body' => $render($translation->body),
            'locale' => $translation->locale,
        ];
    }
}
