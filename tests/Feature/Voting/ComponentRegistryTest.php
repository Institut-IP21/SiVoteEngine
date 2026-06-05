<?php

namespace Tests\Feature\Voting;

use App\BallotComponents\ApprovalVote\v1\ApprovalVote;
use App\BallotComponents\RankedChoice\v1\RankedChoice;
use App\BallotComponents\Support\ComponentRegistry;
use App\BallotComponents\YesNo\v1\YesNo;
use Tests\TestCase;

class ComponentRegistryTest extends TestCase
{
    private ComponentRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = app(ComponentRegistry::class);
    }

    public function test_get_types_returns_all_registered_types(): void
    {
        $this->assertEqualsCanonicalizing(
            ['YesNo', 'FirstPastThePost', 'RankedChoice', 'ApprovalVote'],
            $this->registry->getTypes()
        );
    }

    public function test_get_versions(): void
    {
        $this->assertEquals(['v1'], $this->registry->getVersions('ApprovalVote'));
        $this->assertEquals([], $this->registry->getVersions('Nonexistent'));
    }

    public function test_has(): void
    {
        $this->assertTrue($this->registry->has('YesNo', 'v1'));
        $this->assertFalse($this->registry->has('YesNo', 'v2'));
        $this->assertFalse($this->registry->has('Nope', 'v1'));
    }

    public function test_get_class_returns_class_string(): void
    {
        $this->assertSame(YesNo::class, $this->registry->getClass('YesNo', 'v1'));
        $this->assertSame(RankedChoice::class, $this->registry->getClass('RankedChoice', 'v1'));
    }

    public function test_get_class_throws_for_unknown_component(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->registry->getClass('YesNo', 'v99');
    }

    public function test_resolve_returns_a_component_instance(): void
    {
        $this->assertInstanceOf(ApprovalVote::class, $this->registry->resolve('ApprovalVote', 'v1'));
    }

    public function test_resolve_throws_for_unknown_component(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->registry->resolve('Unknown', 'v1');
    }
}
