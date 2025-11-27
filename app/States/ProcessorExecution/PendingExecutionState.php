<?php

namespace App\States\ProcessorExecution;

class PendingExecutionState extends ProcessorExecutionState
{
    public static $name = 'pending';
    public function color(): string
    {
        return 'gray';
    }

    public function label(): string
    {
        return 'Pending';
    }
}
