<?php

declare(strict_types=1);

namespace App\BallotComponents\Support;

use App\BallotComponents\ApprovalVote\v1\ApprovalVote;
use App\BallotComponents\Contracts\BallotComponentInterface;
use App\BallotComponents\FirstPastThePost\v1\FirstPastThePost;
use App\BallotComponents\RankedChoice\v1\RankedChoice;
use App\BallotComponents\YesNo\v1\YesNo;
use Illuminate\Contracts\Container\Container;

/**
 * Registry for ballot component types and versions.
 *
 * Provides a central location for component discovery and instantiation.
 */
final class ComponentRegistry
{
    /** @var array<string, array<string, class-string<BallotComponentInterface>>> */
    private const COMPONENTS = [
        'YesNo' => ['v1' => YesNo::class],
        'FirstPastThePost' => ['v1' => FirstPastThePost::class],
        'RankedChoice' => ['v1' => RankedChoice::class],
        'ApprovalVote' => ['v1' => ApprovalVote::class],
    ];

    public function __construct(
        private readonly Container $container,
    ) {}

    /**
     * Get all registered components.
     *
     * @return array<string, array<string, class-string<BallotComponentInterface>>>
     */
    public function all(): array
    {
        return self::COMPONENTS;
    }

    /**
     * Get all available component types.
     *
     * @return array<string>
     */
    public function getTypes(): array
    {
        return array_keys(self::COMPONENTS);
    }

    /**
     * Get available versions for a component type.
     *
     * @return array<string>
     */
    public function getVersions(string $type): array
    {
        return array_key_exists($type, self::COMPONENTS)
            ? array_keys(self::COMPONENTS[$type])
            : [];
    }

    /**
     * Get the class name for a component type and version.
     *
     * @return class-string<BallotComponentInterface>
     * @throws \InvalidArgumentException If component type/version not found
     */
    public function getClass(string $type, string $version): string
    {
        if (!isset(self::COMPONENTS[$type][$version])) {
            throw new \InvalidArgumentException(
                "Component type '{$type}' version '{$version}' not found"
            );
        }

        return self::COMPONENTS[$type][$version];
    }

    /**
     * Resolve a component instance from the container.
     *
     * @throws \InvalidArgumentException If component type/version not found
     */
    public function resolve(string $type, string $version): BallotComponentInterface
    {
        return $this->container->make($this->getClass($type, $version));
    }

    /**
     * Check if a component type and version exists.
     */
    public function has(string $type, string $version): bool
    {
        return isset(self::COMPONENTS[$type][$version]);
    }
}
