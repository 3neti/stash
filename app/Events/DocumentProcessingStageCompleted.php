<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\DocumentJob;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a document processing stage completes successfully.
 *
 * Fired after a processor execution succeeds and before advancing to the next stage.
 */
class DocumentProcessingStageCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public DocumentJob $job,
    ) {}
}
