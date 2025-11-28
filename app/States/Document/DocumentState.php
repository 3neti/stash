<?php

namespace App\States\Document;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class DocumentState extends State
{
    abstract public function color(): string;

    abstract public function label(): string;

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(PendingDocumentState::class)
            ->registerStatesFromDirectory(__DIR__)
            // Standard workflow
            ->allowTransition(PendingDocumentState::class, QueuedDocumentState::class)
            ->allowTransition(QueuedDocumentState::class, ProcessingDocumentState::class)
            ->allowTransition(ProcessingDocumentState::class, CompletedDocumentState::class)
            ->allowTransition(ProcessingDocumentState::class, FailedDocumentState::class)
            // Direct transitions (bypass queue)
            ->allowTransition(PendingDocumentState::class, ProcessingDocumentState::class)
            ->allowTransition(PendingDocumentState::class, CompletedDocumentState::class)
            ->allowTransition(PendingDocumentState::class, FailedDocumentState::class)
            // Cancellation from any state
            ->allowTransition(PendingDocumentState::class, CancelledDocumentState::class)
            ->allowTransition(QueuedDocumentState::class, CancelledDocumentState::class)
            ->allowTransition(ProcessingDocumentState::class, CancelledDocumentState::class);
    }
}
