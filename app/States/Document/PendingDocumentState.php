<?php

namespace App\States\Document;

class PendingDocumentState extends DocumentState
{
    protected static $name = 'pending';

    public function color(): string
    {
        return 'gray';
    }

    public function label(): string
    {
        return 'Pending';
    }
}
