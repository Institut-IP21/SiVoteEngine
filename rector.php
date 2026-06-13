<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/app',
        __DIR__ . '/tests',
        __DIR__ . '/database',
    ])
    ->withSkip([
        // Calculator tally logic — secret-ballot result computation. Keep Rector
        // away from it entirely; the PHP-set rules (first-class-callable,
        // foreach-to-array-any, typed consts) rewrite the tallying code, which the
        // task forbids touching. Type declarations only must not reach here.
        __DIR__ . '/app/BallotComponents',
    ])
    ->withPhpSets()
    ->withPreparedSets(typeDeclarations: true)
    ->withImportNames(importShortClasses: false);
