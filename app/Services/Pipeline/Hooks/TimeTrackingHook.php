<?php

declare(strict_types=1);

namespace App\Services\Pipeline\Hooks;

use App\Contracts\Processors\ProcessorHook;
use App\Models\ProcessorExecution;
use Illuminate\Support\Facades\Log;

/**
 * TimeTrackingHook
 *
 * Tracks processor execution time by recording start time before execution
 * and calculating duration after execution completes.
 */
class TimeTrackingHook implements ProcessorHook
{
    /**
     * Called before processor execution.
     *
     * Records the start time in the execution record.
     *
     * @param  ProcessorExecution  $execution
     * @return void
     */
    public function beforeExecution(ProcessorExecution $execution): void
    {
        Log::debug('[TimeTracking] Starting timer for processor execution', [
            'execution_id' => $execution->id,
            'processor_id' => $execution->processor_id,
        ]);

        $execution->start();
    }

    /**
     * Called after successful processor execution.
     *
     * Calculates and records the duration of execution.
     *
     * @param  ProcessorExecution  $execution
     * @param  array  $output
     * @return void
     */
    public function afterExecution(ProcessorExecution $execution, array $output): void
    {
        // Duration is calculated in ProcessorExecution->complete()
        // This hook can be extended for additional timing insights
        Log::debug('[TimeTracking] Processor execution completed', [
            'execution_id' => $execution->id,
            'duration_ms' => $execution->duration_ms,
        ]);
    }

    /**
     * Called when processor execution fails.
     *
     * Records the duration even in case of failure.
     *
     * @param  ProcessorExecution  $execution
     * @param  \Throwable  $exception
     * @return void
     */
    public function onFailure(ProcessorExecution $execution, \Throwable $exception): void
    {
        // Duration is calculated in ProcessorExecution->fail()
        Log::debug('[TimeTracking] Processor execution failed', [
            'execution_id' => $execution->id,
            'duration_ms' => $execution->duration_ms,
            'error' => $exception->getMessage(),
        ]);
    }
}
