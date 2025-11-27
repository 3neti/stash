<?php

namespace App\States\ProcessorExecution;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class ProcessorExecutionState extends State
{
    abstract public function color(): string;

    abstract public function label(): string;

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(PendingExecutionState::class)
            ->allowTransition(PendingExecutionState::class, RunningExecutionState::class)
            ->allowTransition(RunningExecutionState::class, CompletedExecutionState::class)
            ->allowTransition(RunningExecutionState::class, FailedExecutionState::class)
            ->allowTransition(PendingExecutionState::class, SkippedExecutionState::class);
    }
}
