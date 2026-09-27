<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * EN<->SL key parity for the two lang files added alongside the by-hand
 * calculation steps + method comparison content: `manual.php` (per-slug
 * ordered step lists) and `comparison.php` (the shared `labels`/
 * `disclaimer` keys plus each slug's `elected` descriptor). Mirrors the
 * same parity guarantee `academic.php`/`components.php` already rely on
 * (see their own docblocks), just enforced here as an explicit test since
 * no shared parity test previously existed for those either.
 */
class ManualComparisonLangParityTest extends TestCase
{
    public function test_manual_lang_files_have_identical_key_structure_in_both_locales(): void
    {
        $en = require base_path('resources/lang/en/manual.php');
        $sl = require base_path('resources/lang/sl/manual.php');

        $this->assertSame($this->keyShape($en), $this->keyShape($sl));

        // Every slug must have a NON-EMPTY, ORDERED list of steps in both locales.
        foreach (array_keys($en) as $slug) {
            $this->assertNotEmpty($en[$slug], "manual.en.{$slug} must not be empty");
            $this->assertNotEmpty($sl[$slug], "manual.sl.{$slug} must not be empty");
            $this->assertSame(count($en[$slug]), count($sl[$slug]), "manual.{$slug} step count must match between locales");
        }
    }

    public function test_comparison_lang_files_have_identical_key_structure_in_both_locales(): void
    {
        $en = require base_path('resources/lang/en/comparison.php');
        $sl = require base_path('resources/lang/sl/comparison.php');

        $this->assertSame($this->keyShape($en), $this->keyShape($sl));
    }

    /**
     * Recursively reduce an array to just its key structure (nested key
     * names, dropping leaf values), key-sorted at every level so the
     * comparison is about the KEY SET, not source-order — two locales can
     * then be compared for identical shape regardless of their
     * (necessarily different) text.
     *
     * @param array<mixed> $array
     * @return array<mixed>
     */
    private function keyShape(array $array): array
    {
        $shape = [];
        foreach ($array as $key => $value) {
            $shape[$key] = is_array($value) ? $this->keyShape($value) : true;
        }
        ksort($shape);
        return $shape;
    }
}
