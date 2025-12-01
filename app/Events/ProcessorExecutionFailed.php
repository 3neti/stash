<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\DocumentJob;
use App\Models\ProcessorExecution;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a processor execution fails.
 */
class ProcessorExecutionFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ProcessorExecution $execution,
        public DocumentJob $job,
    ) {}
}
