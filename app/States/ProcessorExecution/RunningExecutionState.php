<?php

namespace App\States\ProcessorExecution;

use App\Models\ProcessorExecution;

class RunningExecutionState extends ProcessorExecutionState
{
    protected static $name = 'running';
    public function color(): string
    {
        return 'yellow';
    }

    public function label(): string
    {
        return 'Running';
    }

    public function __construct(ProcessorExecution $execution)
    {
        parent::__construct($execution);

        if (!$execution->started_at) {
            $execution->started_at = now();
            $execution->saveQuietly();
        }
    }
}
