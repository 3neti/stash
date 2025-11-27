<?php

namespace App\States\ProcessorExecution;

class SkippedExecutionState extends ProcessorExecutionState
{
    protected static $name = 'skipped';
    public function color(): string
    {
        return 'gray';
    }

    public function label(): string
    {
        return 'Skipped';
    }
}
