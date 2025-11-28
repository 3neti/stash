<?php

namespace App\States\DocumentJob;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class DocumentJobState extends State
{
    abstract public function color(): string;

    abstract public function label(): string;

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(PendingJobState::class)
            ->allowTransition(PendingJobState::class, QueuedJobState::class)
            ->allowTransition(QueuedJobState::class, RunningJobState::class)
            ->allowTransition(RunningJobState::class, CompletedJobState::class)
            ->allowTransition(RunningJobState::class, FailedJobState::class)
            ->allowTransition(FailedJobState::class, QueuedJobState::class)
            ->allowTransition(PendingJobState::class, CancelledJobState::class)
            ->allowTransition(QueuedJobState::class, CancelledJobState::class)
            ->allowTransition(RunningJobState::class, CancelledJobState::class);
    }
}
