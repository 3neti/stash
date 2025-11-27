<?php

namespace App\States\DocumentJob;

class QueuedJobState extends DocumentJobState
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
