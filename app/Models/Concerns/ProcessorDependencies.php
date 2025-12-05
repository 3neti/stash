<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Processor;
use App\Models\ProcessorExecution;

/**
 * Processor Dependencies Trait
 * 
 * Validates that required processors have been executed before the current processor.
 */
trait ProcessorDependencies
{
    /**
     * Check if all dependencies for this processor have been satisfied.
     * 
     * @param string $jobId DocumentJob ID to check execution history
     * @return array ['satisfied' => bool, 'missing' => array]
     */
    public function checkDependencies(string $jobId): array
    {
        $dependencies = $this->dependencies ?? [];
        
        if (empty($dependencies)) {
            return ['satisfied' => true, 'missing' => []];
        }
        
        // Get all completed processor executions for this job
        $completedSlugs = ProcessorExecution::where('job_id', $jobId)
            ->where('status', 'completed')
            ->whereHas('processor')
            ->with('processor:id,slug')
            ->get()
            ->pluck('processor.slug')
            ->toArray();
        
        // Check which dependencies are missing
        $missing = array_diff($dependencies, $completedSlugs);
        
        return [
            'satisfied' => empty($missing),
            'missing' => array_values($missing),
        ];
    }
    
    /**
     * Assert that all dependencies are satisfied, throw exception if not.
     * 
     * @param string $jobId DocumentJob ID
     * @throws \RuntimeException
     */
    public function assertDependenciesSatisfied(string $jobId): void
    {
        $result = $this->checkDependencies($jobId);
        
        if (!$result['satisfied']) {
            $missing = implode(', ', $result['missing']);
            throw new \RuntimeException(
                "Processor '{$this->slug}' requires these processors to run first: {$missing}"
            );
        }
    }
    
    /**
     * Get the full dependency tree for this processor.
     * Returns processors in execution order.
     * 
     * @return array Array of Processor models in dependency order
     */
    public function getDependencyTree(): array
    {
        $tree = [];
        $visited = [];
        
        $this->buildDependencyTree($this, $tree, $visited);
        
        return $tree;
    }
    
    /**
     * Recursively build dependency tree.
     */
    protected function buildDependencyTree(Processor $processor, array &$tree, array &$visited): void
    {
        // Prevent circular dependencies
        if (in_array($processor->slug, $visited)) {
            return;
        }
        
        $visited[] = $processor->slug;
        
        $dependencies = $processor->dependencies ?? [];
        
        foreach ($dependencies as $depSlug) {
            $depProcessor = Processor::where('slug', $depSlug)->first();
            
            if ($depProcessor) {
                $this->buildDependencyTree($depProcessor, $tree, $visited);
            }
        }
        
        // Add current processor after its dependencies
        if (!in_array($processor->id, array_column($tree, 'id'))) {
            $tree[] = $processor;
        }
    }
}
