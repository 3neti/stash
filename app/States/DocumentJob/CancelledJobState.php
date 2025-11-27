<?php

namespace App\States\DocumentJob;

class CancelledJobState extends DocumentJobState
{
    protected static $name = 'cancelled';
    public function color(): string
    {
        return 'gray';
    }

    public function label(): string
    {
        return 'Cancelled';
    }
}
