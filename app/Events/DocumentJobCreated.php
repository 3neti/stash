<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Campaign;
use App\Models\Document;
use App\Models\DocumentJob;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a DocumentJob is created and ready for processing.
 */
class DocumentJobCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public DocumentJob $job,
        public Document $document,
        public Campaign $campaign,
    ) {}
}
