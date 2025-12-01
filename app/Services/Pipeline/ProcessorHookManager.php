<?php

declare(strict_types=1);

namespace App\Services\Pipeline;

use App\Contracts\Processors\ProcessorHook;
use App\Models\ProcessorExecution;
use Illuminate\Support\Facades\Log;

/**
 * ProcessorHookManager
 *
 * Manages processor execution hooks, allowing registration and invocation
 * of hooks at various points in processor lifecycle.
 */
class ProcessorHookManager
{
    /**
     * @var ProcessorHook[]
     */
    private array $hooks = [];

    /**
     * Register a processor hook.
     *
     * @param  ProcessorHook  $hook
     * @return void
     */
    public function register(ProcessorHook $hook): void
    {
        $this->hooks[] = $hook;
        Log::debug('[Hooks] Processor hook registered', [
            'hook' => get_class($hook),
            'total_hooks' => count($this->hooks),
        ]);
    }

    /**
     * Invoke beforeExecution hooks.
     *
     * @param  ProcessorExecution  $execution
     * @return void
     */
    public function beforeExecution(ProcessorExecution $execution): void
    {
        Log::debug('[Hooks] Invoking beforeExecution hooks', [
            'execution_id' => $execution->id,
            'hook_count' => count($this->hooks),
        ]);

        foreach ($this->hooks as $hook) {
            try {
                $hook->beforeExecution($execution);
            } catch (\Throwable $e) {
                Log::error('[Hooks] Error in beforeExecution hook', [
                    'hook' => get_class($hook),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Invoke afterExecution hooks.
     *
     * @param  ProcessorExecution  $execution
     * @param  array  $output
     * @return void
     */
    public function afterExecution(ProcessorExecution $execution, array $output): void
    {
        Log::debug('[Hooks] Invoking afterExecution hooks', [
            'execution_id' => $execution->id,
            'hook_count' => count($this->hooks),
        ]);

        foreach ($this->hooks as $hook) {
            try {
                $hook->afterExecution($execution, $output);
            } catch (\Throwable $e) {
                Log::error('[Hooks] Error in afterExecution hook', [
                    'hook' => get_class($hook),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Invoke onFailure hooks.
     *
     * @param  ProcessorExecution  $execution
     * @param  \Throwable  $exception
     * @return void
     */
    public function onFailure(ProcessorExecution $execution, \Throwable $exception): void
    {
        Log::debug('[Hooks] Invoking onFailure hooks', [
            'execution_id' => $execution->id,
            'hook_count' => count($this->hooks),
        ]);

        foreach ($this->hooks as $hook) {
            try {
                $hook->onFailure($execution, $exception);
            } catch (\Throwable $e) {
                Log::error('[Hooks] Error in onFailure hook', [
                    'hook' => get_class($hook),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Clear all registered hooks.
     *
     * @return void
     */
    public function clear(): void
    {
        $this->hooks = [];
    }

    /**
     * Get count of registered hooks.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->hooks);
    }
}
