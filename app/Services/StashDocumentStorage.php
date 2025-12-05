<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Document;
use App\Models\ProcessorExecution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use LBHurtado\HyperVerge\Contracts\DocumentStoragePort;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Stash Document Storage Adapter
 *
 * Adapts Stash's storage architecture to hyperverge's DocumentStoragePort interface.
 *
 * Architecture:
 * - Document model: Uses direct Laravel Storage (storage_path, storage_disk)
 * - ProcessorExecution model: Uses Spatie MediaLibrary for artifacts
 *
 * This adapter bridges the two systems:
 * - Reading original documents: From Document->storage_path
 * - Storing signed documents: Into ProcessorExecution media collections
 */
class StashDocumentStorage implements DocumentStoragePort
{
    /**
     * Store a document file in ProcessorExecution media collection.
     *
     * Note: The $model is expected to be a Document, but we store artifacts
     * in the associated ProcessorExecution (following Stash's architecture).
     */
    public function storeDocument(Model $model, string $filePath, string $collection, array $customProperties = []): mixed
    {
        // If model is ProcessorExecution, use it directly
        if ($model instanceof ProcessorExecution) {
            return $model->addMedia($filePath)
                ->withCustomProperties($customProperties)
                ->toMediaCollection($collection);
        }

        // If model is Document, find its latest ProcessorExecution
        if ($model instanceof Document) {
            $execution = $model->documentJob?->processorExecutions()
                ->whereHas('processor', fn ($q) => $q->where('slug', 'electronic-signature'))
                ->latest()
                ->first();

            if (! $execution) {
                throw new \RuntimeException(
                    'Cannot store signed document: No ProcessorExecution found for Document. '.
                    'This should not happen in normal workflow execution.'
                );
            }

            return $execution->addMedia($filePath)
                ->withCustomProperties($customProperties)
                ->toMediaCollection($collection);
        }

        throw new \InvalidArgumentException(
            'Model must be Document or ProcessorExecution. Got: '.get_class($model)
        );
    }

    /**
     * Retrieve the original document from Document model's storage.
     *
     * Returns a fake "Media" object that provides getPath() for compatibility.
     */
    public function getDocument(Model $model, string $collection): mixed
    {
        if ($model instanceof Document) {
            // For "documents" collection, return the original document
            if ($collection === 'documents' && $model->fileExists()) {
                // Return a simple object with getPath() method
                return new class($model)
                {
                    private Document $document;

                    public function __construct(Document $document)
                    {
                        $this->document = $document;
                    }

                    public function getPath(): string
                    {
                        return Storage::disk($this->document->storage_disk)
                            ->path($this->document->storage_path);
                    }

                    public function getUrl(): string
                    {
                        return Storage::disk($this->document->storage_disk)
                            ->url($this->document->storage_path);
                    }
                };
            }

            return null;
        }

        // For ProcessorExecution, use MediaLibrary
        if ($model instanceof ProcessorExecution && method_exists($model, 'getFirstMedia')) {
            return $model->getFirstMedia($collection);
        }

        return null;
    }

    /**
     * Get absolute file path for a media object.
     */
    public function getPath(mixed $media): string
    {
        if (method_exists($media, 'getPath')) {
            return $media->getPath();
        }

        if ($media instanceof Media) {
            return $media->getPath();
        }

        throw new \InvalidArgumentException('Media object must have getPath() method');
    }

    /**
     * Get public URL for a media object.
     */
    public function getUrl(mixed $media): string
    {
        if (method_exists($media, 'getUrl')) {
            return $media->getUrl();
        }

        if ($media instanceof Media) {
            return $media->getUrl();
        }

        throw new \InvalidArgumentException('Media object must have getUrl() method');
    }

    /**
     * Check if document exists.
     */
    public function hasDocument(Model $model, string $collection): bool
    {
        if ($model instanceof Document && $collection === 'documents') {
            return $model->fileExists();
        }

        if ($model instanceof ProcessorExecution && method_exists($model, 'hasMedia')) {
            return $model->hasMedia($collection);
        }

        return false;
    }

    /**
     * Delete document from storage.
     */
    public function deleteDocument(Model $model, string $collection): bool
    {
        if ($model instanceof Document && $collection === 'documents') {
            return $model->deleteFile();
        }

        if ($model instanceof ProcessorExecution && method_exists($model, 'getFirstMedia')) {
            $media = $model->getFirstMedia($collection);

            if ($media) {
                $media->delete();

                return true;
            }
        }

        return false;
    }
}
