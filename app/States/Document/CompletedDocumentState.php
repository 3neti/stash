<?php

namespace App\States\Document;

use App\Models\Document;

class CompletedDocumentState extends DocumentState
{
    public static $name = 'completed';

    public function color(): string
    {
        return 'green';
    }

    public function label(): string
    {
        return 'Completed';
    }

    public function __construct(Document $document)
    {
        parent::__construct($document);

        if (!$document->processed_at) {
            $document->processed_at = now();
            $document->saveQuietly();
        }
    }
}
