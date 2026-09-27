<?php

declare(strict_types=1);

namespace App\BallotComponents\DTOs;

/**
 * Bilingual academic/educational explainer content for a ballot component
 * type: a neutral explanation plus pros/cons, migrated from web_app's
 * `resources/lang/{en,sl}/academic.php` into the engine so every method's
 * explanatory content is centralized here rather than duplicated in web_app.
 * `en`/`sl` are fetched explicitly in BOTH locales regardless of the request
 * locale (mirrors `StatuteText`) so a locale toggle needs no second round
 * trip.
 *
 * Background/educational reading — NOT the organization's actual rules (see
 * `StatuteText` for the legal-reference clause text) and NOT the short
 * voter-facing "how it works" copy (see `components.<slug>.lay_explanation`,
 * exposed as `BallotComponent::$lay_explanation`).
 */
final readonly class AcademicText
{
    /**
     * @param array{explanation: string, pros: list<string>, cons: list<string>} $en
     * @param array{explanation: string, pros: list<string>, cons: list<string>} $sl
     */
    public function __construct(
        public string $type,
        public array $en,
        public array $sl,
    ) {}

    /**
     * @return array{
     *     type: string,
     *     en: array{explanation: string, pros: list<string>, cons: list<string>},
     *     sl: array{explanation: string, pros: list<string>, cons: list<string>},
     * }
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
