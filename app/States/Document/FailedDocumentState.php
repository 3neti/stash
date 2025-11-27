<?php

namespace App\States\Document;

use App\Models\Document;

class FailedDocumentState extends DocumentState
{
    protected static $name = 'failed';

    public function color(): string
    {
        return 'red';
    }

    public function label(): string
    {
        return 'Failed';
    }

    public function __construct(Document $document)
    {
        parent::__construct($document);

        if (!$document->failed_at) {
            $document->failed_at = now();
            $document->saveQuietly();
        }
    }
}
