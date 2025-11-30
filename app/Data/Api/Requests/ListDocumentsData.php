<?php

declare(strict_types=1);

namespace App\Data\Api\Requests;

use Carbon\Carbon;
use Spatie\LaravelData\Attributes\Validation\DateFormat;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\Integer;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;

/**
 * List Documents Request DTO
 *
 * Validates and structures document listing/filtering requests.
 */
class ListDocumentsData extends Data
{
    public function __construct(
        #[In(['pending', 'processing', 'completed', 'failed'])]
        public ?string $status = null,

        #[DateFormat('Y-m-d')]
        public ?string $date_from = null,

        #[DateFormat('Y-m-d')]
        public ?string $date_to = null,

        #[Integer]
        #[Min(1)]
        public ?int $page = 1,

        #[Integer]
        #[Min(1)]
        #[Max(100)]
        public ?int $per_page = 15,
    ) {}

    /**
     * Get date_from as Carbon instance.
     */
    public function getDateFrom(): ?Carbon
    {
        return $this->date_from ? Carbon::parse($this->date_from) : null;
    }

    /**
     * Get date_to as Carbon instance.
     */
    public function getDateTo(): ?Carbon
    {
        return $this->date_to ? Carbon::parse($this->date_to) : null;
    }

    /**
     * Get per_page value (capped at 100).
     */
    public function getPerPage(): int
    {
        return min($this->per_page ?? 15, 100);
    }

    /**
     * Check if any filters are applied.
     */
    public function hasFilters(): bool
    {
        return $this->status !== null
            || $this->date_from !== null
            || $this->date_to !== null;
    }

    /**
     * Get array of applied filters for logging.
     */
    public function getAppliedFilters(): array
    {
        return array_filter([
            'status' => $this->status,
            'date_from' => $this->date_from,
            'date_to' => $this->date_to,
        ]);
    }
}
