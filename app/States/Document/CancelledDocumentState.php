<?php

namespace App\States\Document;

class CancelledDocumentState extends DocumentState
{
    public function color(): string
    {
        return 'gray';
    }

    public function label(): string
    {
        return 'Cancelled';
    }
}
