<?php

namespace App\States\DocumentJob;

class CancelledJobState extends DocumentJobState
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
