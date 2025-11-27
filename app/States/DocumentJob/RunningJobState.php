<?php

namespace App\States\DocumentJob;

use App\Models\DocumentJob;

class RunningJobState extends DocumentJobState
{
    public static $name = 'running';
    public function color(): string
    {
        return 'yellow';
    }

    public function label(): string
    {
        return 'Running';
    }

    public function __construct(DocumentJob $job)
    {
        parent::__construct($job);

        if (!$job->started_at) {
            $job->started_at = now();
            $job->saveQuietly();
        }
    }
}
