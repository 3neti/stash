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
            ->allowTransition(PendingDocumentState::class, QueuedDocumentState::class)
            ->allowTransition(QueuedDocumentState::class, ProcessingDocumentState::class)
            ->allowTransition(ProcessingDocumentState::class, CompletedDocumentState::class)
            ->allowTransition(ProcessingDocumentState::class, FailedDocumentState::class)
            ->allowTransitions([
                PendingDocumentState::class,
                QueuedDocumentState::class,
                ProcessingDocumentState::class,
            ], CancelledDocumentState::class);
    }
}
