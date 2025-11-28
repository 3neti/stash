<?php

namespace App\States\ProcessorExecution;

use App\Models\ProcessorExecution;

class FailedExecutionState extends ProcessorExecutionState
{
    public static $name = 'failed';
    public function color(): string
    {
        return 'red';
    }

    public function label(): string
    {
        return 'Failed';
    }

    public function __construct(ProcessorExecution $execution)
    {
        parent::__construct($execution);

        if ($execution->started_at && !$execution->duration_ms) {
            $execution->duration_ms = (int) $execution->started_at->diffInMilliseconds(now());
            $execution->saveQuietly();
        }
    }
}
