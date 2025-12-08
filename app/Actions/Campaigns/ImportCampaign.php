<?php

declare(strict_types=1);

namespace App\Actions\Campaigns;

use App\Data\Campaigns\CampaignImportData;
use App\Models\Campaign;
use App\Services\Pipeline\ProcessorRegistry;
use App\States\Campaign\ActiveCampaignState;
use App\States\Campaign\ArchivedCampaignState;
use App\States\Campaign\DraftCampaignState;
use App\States\Campaign\PausedCampaignState;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Import Campaign from DTO
 *
 * Validates processor types, step IDs, maps state strings to state classes,
 * and creates campaign directly (similar to CampaignSeeder).
 */
class ImportCampaign
{
    use AsAction;

    /**
     * Import campaign from validated DTO.
     */
    public function handle(
        CampaignImportData $data,
        ProcessorRegistry $registry
    ): Campaign {
        // Load processors from tenant database into registry
        $registry->registerFromDatabase();
        
        // Validate processors array is not empty
        if ($data->processors->count() === 0) {
            throw new \InvalidArgumentException('Campaign must have at least one processor.');
        }

        // Validate processor types exist in registry
        $this->validateProcessorTypes($data->processors, $registry);

        // Validate step IDs are unique
        $this->validateUniqueStepIds($data->processors);

        // Map processor types to actual Processor model ULIDs
        $processorsWithUlids = $this->mapProcessorTypesToUlids($data->processors);

        // Create campaign directly (like CampaignSeeder does)
        // This allows us to set all fields including state and optional fields
        $campaignData = [
            'name' => $data->name,
            'slug' => $data->slug ?? Str::slug($data->name),
            'description' => $data->description,
            'type' => $data->type,
            'state' => $this->mapStateStringToClass($data->state),
            'pipeline_config' => [
                'processors' => $processorsWithUlids,
            ],
            'settings' => $data->settings ?? [],
            'allowed_mime_types' => $data->allowed_mime_types ?? ['application/pdf'],
            'max_file_size_bytes' => $data->max_file_size_bytes,
            'max_concurrent_jobs' => $data->max_concurrent_jobs,
            'retention_days' => $data->retention_days,
            'checklist_template' => $data->checklist_template,
        ];

        // Remove null values to use model defaults
        $campaignData = array_filter($campaignData, fn ($value) => $value !== null);

        return Campaign::create($campaignData);
    }

    /**
     * Validate all processor types exist in registry.
     *
     * @throws \InvalidArgumentException
     */
    private function validateProcessorTypes($processors, ProcessorRegistry $registry): void
    {
        foreach ($processors as $processor) {
            if (! $registry->has($processor->type)) {
                throw new \InvalidArgumentException(
                    "Unknown processor type '{$processor->type}'. Available processors: ".
                    implode(', ', $registry->getRegisteredIds())
                );
            }
        }
    }

    /**
     * Validate step IDs are unique within pipeline.
     *
     * @throws \InvalidArgumentException
     */
    private function validateUniqueStepIds($processors): void
    {
        $ids = [];
        foreach ($processors as $processor) {
            if (in_array($processor->id, $ids, true)) {
                throw new \InvalidArgumentException(
                    "Duplicate step ID '{$processor->id}' found in pipeline. Step IDs must be unique."
                );
            }
            $ids[] = $processor->id;
        }
    }

    /**
     * Map processor types (slugs) to actual Processor model ULIDs.
     * 
     * Replaces the template 'type' field with the database processor ULID in the 'id' field.
     * This maintains backward compatibility with code that expects 'id' to be a ULID.
     */
    private function mapProcessorTypesToUlids($processors): array
    {
        $mapped = [];
        
        foreach ($processors as $processor) {
            $processorArray = is_array($processor) ? $processor : $processor->toArray();
            
            // Look up Processor model by slug (type)
            $processorModel = \App\Models\Processor::where('slug', $processorArray['type'])->first();
            
            if (!$processorModel) {
                throw new \InvalidArgumentException(
                    "Processor not found in database with slug: {$processorArray['type']}"
                );
            }
            
            // Preserve original template ID as 'step_id' for placeholder resolution
            $processorArray['step_id'] = $processorArray['id'];
            
            // Replace 'id' with the actual Processor ULID
            $processorArray['id'] = $processorModel->id;
            
            $mapped[] = $processorArray;
        }
        
        return $mapped;
    }

    /**
     * Map state string to state class instance.
     */
    private function mapStateStringToClass(string $state): string
    {
        return match ($state) {
            'active' => ActiveCampaignState::class,
            'paused' => PausedCampaignState::class,
            'archived' => ArchivedCampaignState::class,
            default => DraftCampaignState::class,
        };
    }
}
