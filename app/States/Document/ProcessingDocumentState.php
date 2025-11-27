<?php

namespace App\States\Document;

class ProcessingDocumentState extends DocumentState
{
    public function color(): string
    {
        return 'yellow';
    }

    public function label(): string
    {
        return 'Processing';
    }
}
