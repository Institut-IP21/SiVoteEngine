<?php

namespace App\Exceptions;

/** Thrown when an operation that requires an active ballot finds it inactive under the row lock. */
class BallotNotActiveException extends \RuntimeException
{
}
