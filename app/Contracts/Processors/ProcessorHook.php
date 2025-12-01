<?php

declare(strict_types=1);

namespace App\Contracts\Processors;

use App\Models\ProcessorExecution;

/**
 * ProcessorHook Interface
 *
 * Defines hooks for processor execution lifecycle events.
 * Hooks can intercept, monitor, or modify processor execution.
 */
interface ProcessorHook
{
    /**
     * Called before processor execution.
     *
     * @param  ProcessorExecution  $execution The processor execution record
     * @return void
     */
    public function beforeExecution(ProcessorExecution $execution): void;

    /**
     * Called after successful processor execution.
     *
     * @param  ProcessorExecution  $execution The processor execution record
     * @param  array  $output The processor output
     * @return void
     */
    public function afterExecution(ProcessorExecution $execution, array $output): void;

    /**
     * Called when processor execution fails.
     *
     * @param  ProcessorExecution  $execution The processor execution record
     * @param  \Throwable  $exception The exception that was thrown
     * @return void
     */
    public function onFailure(ProcessorExecution $execution, \Throwable $exception): void;
}
