<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\BrandContrast;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BrandContrastTest extends TestCase
{
    #[DataProvider('cases')]
    public function test_foreground(?string $bg, string $expected): void
    {
        $this->assertSame($expected, BrandContrast::foreground($bg));
    }

    /** @return array<string, array{0: ?string, 1: string}> */
    public static function cases(): array
    {
        return [
            'white bg -> dark text'        => ['#ffffff', '#11161a'],
            'black bg -> white text'       => ['#000000', '#ffffff'],
            'bright yellow -> dark text'   => ['#ffeb3b', '#11161a'],
            'navy -> white text'           => ['#1a237e', '#ffffff'],
            'mid cyan-blue -> dark text'   => ['#34b6df', '#11161a'],
            'null -> white (fallback)'     => [null, '#ffffff'],
            'invalid -> white (fallback)'  => ['not-a-hex', '#ffffff'],
        ];
    }
}
