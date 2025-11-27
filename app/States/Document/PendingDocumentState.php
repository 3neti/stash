<?php

namespace App\States\Document;

class PendingDocumentState extends DocumentState
{
    public function color(): string
    {
        return 'gray';
    }

    public function label(): string
    {
        return 'Pending';
    }
}
