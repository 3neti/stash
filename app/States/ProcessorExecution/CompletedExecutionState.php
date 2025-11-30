<?php

namespace App\States\ProcessorExecution;

use App\Models\ProcessorExecution;

class CompletedExecutionState extends ProcessorExecutionState
{
    public static $name = 'completed';

    public function color(): string
    {
        return 'green';
    }

    public function label(): string
    {
        return 'Completed';
    }

    public function __construct(ProcessorExecution $execution)
    {
        parent::__construct($execution);

        if (! $execution->completed_at) {
            $execution->completed_at = now();

            if ($execution->started_at) {
                $execution->duration_ms = (int) $execution->started_at->diffInMilliseconds(now());
            }

            $execution->saveQuietly();
        }
    }
}
