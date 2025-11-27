<?php

namespace App\States\Document;

class QueuedDocumentState extends DocumentState
{
    protected static $name = 'queued';

    public function color(): string
    {
        return 'blue';
    }

    public function label(): string
    {
        return 'Queued';
    }
}
