<?php

namespace App\BallotComponents;

use App\Models\BallotComponent;
use App\Models\Election;

abstract class BallotComponentType
{
    abstract public static function calculateResults(array $votes, BallotComponent $component);
    abstract public static function getSubmissionValidator(BallotComponent $component, Election $election);
    abstract public static function validateOptions($options);

    public static function valuesToCsv($values, $component_id)
    {
        return $values[$component_id];
    }
}
