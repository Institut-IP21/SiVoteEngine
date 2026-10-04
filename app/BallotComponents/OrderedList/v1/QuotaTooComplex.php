<?php

declare(strict_types=1);

namespace App\BallotComponents\OrderedList\v1;

/**
 * Internal signal: the tie-resolution tree outgrew QuotaCorrector's safety
 * cap; the quota result fails safe (provisional, nothing certain).
 */
final class QuotaTooComplex extends \RuntimeException
{
}
