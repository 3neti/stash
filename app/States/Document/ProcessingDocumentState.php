<?php

namespace App\States\Document;

class ProcessingDocumentState extends DocumentState
{
    protected static $name = 'processing';

    public function color(): string
    {
        return 'yellow';
    }

    public function label(): string
    {
        return 'Processing';
    }
}
