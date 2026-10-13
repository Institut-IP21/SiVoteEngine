<?php

namespace Tests\Feature\BallotComponent;

use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Election;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** An update can clear component settings (e.g. YesNo two-thirds back to simple majority). */
class ComponentSettingsClearTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Election, 1: BallotComponent} */
    private function yesNoWithTwoThirds(): array
    {
        $election = Election::factory()->create();
        $ballot = Ballot::factory()->create(['election_id' => $election->id]);
        $component = BallotComponent::factory()->create([
            'ballot_id' => $ballot->id,
            'type' => 'YesNo',
            'version' => 'v1',
            'options' => ['yes', 'no'],
            'settings' => ['pass_threshold' => 'two_thirds'],
        ]);

        return [$election, $component];
    }

    /** @param array<string, mixed> $extra */
    private function update(Election $election, BallotComponent $component, array $extra): void
    {
        $this->withHeaders(['Authorization' => '123123123', 'Owner' => $election->owner])
            ->postJson("/api/election/$election->id/ballot/$component->ballot_id/component/$component->id", array_merge([
                'title' => 'Approve the budget?',
                'type' => 'YesNo',
                'version' => 'v1',
            ], $extra))
            ->assertOk();
    }

    public function test_empty_settings_array_clears_settings(): void
    {
        [$election, $component] = $this->yesNoWithTwoThirds();

        $this->update($election, $component, ['settings' => []]);

        $this->assertNull($component->fresh()?->settings);
    }

    public function test_null_settings_clears_settings(): void
    {
        [$election, $component] = $this->yesNoWithTwoThirds();

        $this->update($election, $component, ['settings' => null]);

        $this->assertNull($component->fresh()?->settings);
    }

    public function test_settings_without_threshold_clears_threshold(): void
    {
        [$election, $component] = $this->yesNoWithTwoThirds();

        $this->update($election, $component, ['settings' => ['pass_threshold' => null]]);

        $this->assertNull($component->fresh()?->settings);
    }

    public function test_absent_settings_key_keeps_existing_settings(): void
    {
        [$election, $component] = $this->yesNoWithTwoThirds();

        $this->update($election, $component, []);

        $this->assertSame(['pass_threshold' => 'two_thirds'], $component->fresh()?->settings);
    }

    public function test_settings_can_still_be_changed(): void
    {
        [$election, $component] = $this->yesNoWithTwoThirds();

        $this->update($election, $component, ['settings' => ['pass_threshold' => 'three_quarters']]);

        $this->assertSame(['pass_threshold' => 'three_quarters'], $component->fresh()?->settings);
    }
}
