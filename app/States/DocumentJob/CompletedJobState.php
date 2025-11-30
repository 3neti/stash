<?php

namespace App\States\DocumentJob;

use App\Models\DocumentJob;

class CompletedJobState extends DocumentJobState
{
    public static $name = 'completed';

    public function color(): string
    {
        return 'green';
    }

    public function label(): string
    {
        return 'Completed';
    }

    public function __construct(DocumentJob $job)
    {
        parent::__construct($job);

        if (! $job->completed_at) {
            $job->completed_at = now();
            $job->saveQuietly();
        }
    }
}
