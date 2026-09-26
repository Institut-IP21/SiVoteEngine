<?php

declare(strict_types=1);

namespace App\BallotComponents\DTOs;

/**
 * Bilingual statute/legal-reference clause text for a ballot component type
 * (see local_docs/voting-components/statute-feature-spec.md). `en`/`sl` are
 * ordered lists of clause paragraphs, fetched explicitly in BOTH locales
 * regardless of the request locale (§2.2) so the settings page's locale
 * toggle needs no second round trip. Numbering ("(1)", "(2)", ...) is
 * generated for display from array order and is never stored in the text.
 *
 * Static, admin-only reference material — not part of `ComponentMetadata`
 * (see `AbstractBallotComponent::getStatuteText()` for why it's a separate
 * method/DTO rather than folded into `getStrings()`).
 */
final readonly class StatuteText
{
    /**
     * @param list<string> $en
     * @param list<string> $sl
     */
    public function __construct(
        public string $type,
        public array $en,
        public array $sl,
    ) {}

    /**
     * @return array{type: string, en: list<string>, sl: list<string>}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'en' => $this->en,
            'sl' => $this->sl,
        ];
    }
}
