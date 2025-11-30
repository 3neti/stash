<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Usage Event Type Enum
 *
 * Represents types of billable events in the system.
 */
enum UsageEventType: string
{
    case Upload = 'upload';
    case Storage = 'storage';
    case ProcessorExecution = 'processor_execution';
    case AiTask = 'ai_task';

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Upload => 'Document Upload',
            self::Storage => 'Storage Usage',
            self::ProcessorExecution => 'Processor Execution',
            self::AiTask => 'AI Task',
        };
    }

    /**
     * Get unit of measurement.
     */
    public function unit(): string
    {
        return match ($this) {
            self::Upload => 'documents',
            self::Storage => 'bytes',
            self::ProcessorExecution => 'executions',
            self::AiTask => 'tokens',
        };
    }

    /**
     * Get cost per unit (in credits).
     */
    public function costPerUnit(): int
    {
        return match ($this) {
            self::Upload => 1,
            self::Storage => 0, // Free for now, charged per GB-month
            self::ProcessorExecution => 5,
            self::AiTask => 0, // Charged per 1000 tokens
        };
    }

    /**
     * Get all enum values as array.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
