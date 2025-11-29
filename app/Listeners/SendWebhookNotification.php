<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\DocumentProcessingCompleted;
use App\Events\DocumentProcessingFailed;
use App\Jobs\DispatchWebhook;

/**
 * Listen to document processing events and dispatch webhooks.
 */
class SendWebhookNotification
{
    /**
     * Handle DocumentProcessingCompleted event.
     */
    public function handleCompleted(DocumentProcessingCompleted $event): void
    {
        $this->dispatchWebhook($event);
    }

    /**
     * Handle DocumentProcessingFailed event.
     */
    public function handleFailed(DocumentProcessingFailed $event): void
    {
        $this->dispatchWebhook($event);
    }

    /**
     * Dispatch webhook for event.
     */
    protected function dispatchWebhook(DocumentProcessingCompleted|DocumentProcessingFailed $event): void
    {
        if (! $event->campaign->webhook) {
            return;
        }

        DispatchWebhook::dispatch(
            $event->campaign,
            $event->getPayload()['event'],
            $event->getPayload()
        );
    }

    /**
     * Register event listeners.
     */
    public function subscribe($events): array
    {
        return [
            DocumentProcessingCompleted::class => 'handleCompleted',
            DocumentProcessingFailed::class => 'handleFailed',
        ];
    }
}
