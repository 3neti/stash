<?php

declare(strict_types=1);

namespace App\Actions\Documents\Web;

use App\Models\Document;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Show single document with relationships.
 */
class ShowDocument
{
    use AsAction;

    /**
     * Show document.
     */
    public function handle(string $uuid): Document
    {
        return Document::query()
            ->with(['campaign', 'documentJob'])
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    /**
     * Handle as controller.
     */
    public function asController(string $uuid): Document
    {
        return $this->handle($uuid);
    }
}
