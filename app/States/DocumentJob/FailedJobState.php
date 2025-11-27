<?php

namespace App\States\DocumentJob;

use App\Models\DocumentJob;

class FailedJobState extends DocumentJobState
{
    public static $name = 'failed';
    public function color(): string
    {
        return 'red';
    }

    public function label(): string
    {
        return 'Failed';
    }

    public function __construct(DocumentJob $job)
    {
        parent::__construct($job);

        if (!$job->failed_at) {
            $job->failed_at = now();
            $job->attempts++;
            $job->saveQuietly();
        }
    }

    public function canRetry(): bool
    {
        $job = $this->$model;
        return $job->attempts < $job->max_attempts;
    }
}
