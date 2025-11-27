<?php

namespace App\States\Document;

class QueuedDocumentState extends DocumentState
{
    public static $name = 'queued';

    public function color(): string
    {
        return 'blue';
    }

    public function label(): string
    {
        return 'Queued';
    }
}
