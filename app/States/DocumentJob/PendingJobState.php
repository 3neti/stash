<?php

namespace App\States\DocumentJob;

class PendingJobState extends DocumentJobState
{
    public static $name = 'pending';

    public function color(): string
    {
        return 'gray';
    }

    public function label(): string
    {
        return 'Pending';
    }
}
