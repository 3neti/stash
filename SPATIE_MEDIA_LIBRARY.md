# Spatie Media Library Integration

## Status: Planned for Future Enhancement

Currently, document storage is handled via Laravel's Storage facade with tenant-scoped disks. However, for production and enhanced features, we should migrate to **Spatie Media Library**.

## Why Spatie Media Library?

### Current Limitations
- Manual file management (upload, hash, paths)
- No automatic image optimization
- No thumbnail generation
- No media collections
- Manual cleanup required

### Benefits of Spatie Media Library
1. **Automatic Media Management**
   - Handles file uploads automatically
   - Generates thumbnails and conversions
   - Optimizes images automatically
   - Manages file cleanup on model deletion

2. **Media Collections**
   - Organize files by type (original, thumbnail, preview)
   - Multiple files per document
   - Custom properties per file

3. **Responsive Images**
   - Automatic responsive image generation
   - WebP conversion
   - Image optimization

4. **S3/Cloud Support**
   - Seamless cloud storage integration
   - Presigned URLs
   - CDN support

## Implementation Plan

### Phase 1: Install Package
```bash
composer require spatie/laravel-medialibrary
php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="migrations"
php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="config"
php artisan migrate
```

### Phase 2: Update Document Model
```php
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Document extends Model implements HasMedia
{
    use InteractsWithMedia, BelongsToTenant;
    
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('original')
            ->singleFile()
            ->useDisk('tenant')
            ->acceptsMimeTypes([
                'application/pdf',
                'image/png',
                'image/jpeg',
                'image/tiff',
            ]);
            
        $this->addMediaCollection('thumbnails')
            ->useDisk('tenant');
    }
    
    public function registerMediaConversions(Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(200)
            ->height(200)
            ->sharpen(10)
            ->performOnCollections('original');
            
        $this->addMediaConversion('preview')
            ->width(800)
            ->height(800)
            ->performOnCollections('original');
    }
}
```

### Phase 3: Update Upload Action
```php
class UploadDocument
{
    public function handle(
        Campaign $campaign,
        UploadedFile $file,
        ?array $metadata = null
    ): Document {
        // Create Document record
        $document = Document::create([
            'id' => Ulid::generate(),
            'uuid' => Str::uuid(),
            'campaign_id' => $campaign->id,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'metadata' => $metadata ?? [],
        ]);
        
        // Attach file using Media Library
        $document->addMedia($file)
            ->withCustomProperties([
                'hash' => hash_file('sha256', $file->getRealPath()),
                'uploaded_by' => auth()->id(),
            ])
            ->toMediaCollection('original');
        
        // Dispatch pipeline processing
        ProcessDocumentJob::dispatch($document);
        
        return $document;
    }
}
```

### Phase 4: Image Processing for OCR
```php
// In OcrProcessor
$document = Document::find($documentId);
$media = $document->getFirstMedia('original');

// Get optimized version for OCR
if ($media->mime_type === 'application/pdf') {
    // Use original PDF
    $path = $media->getPath();
} else {
    // Use preview conversion (optimized image)
    $path = $media->getPath('preview');
}

// Run Tesseract on optimized image
$text = (new TesseractOCR($path))->run();
```

### Phase 5: Presigned URLs for Download
```php
// In API response
$document = Document::find($documentId);
$media = $document->getFirstMedia('original');

return [
    'download_url' => $media->getTemporaryUrl(now()->addMinutes(30)),
    'thumbnail_url' => $media->getUrl('thumb'),
    'preview_url' => $media->getUrl('preview'),
];
```

## Migration Strategy

### Step 1: Add Media Library (Non-Breaking)
- Install package alongside existing storage
- Add `HasMedia` interface to Document model
- Keep existing `storage_path` column

### Step 2: Dual Write (Transition Period)
- Write to both old storage AND Media Library
- Use feature flag to switch between them
- Test thoroughly in staging

### Step 3: Migrate Existing Files
```php
php artisan app:migrate-documents-to-media-library
```

Create command to:
1. Find all documents with `storage_path`
2. Copy file to Media Library
3. Verify hash matches
4. Update document record

### Step 4: Switch to Media Library Only
- Remove old storage code
- Drop `storage_path` column (optional, keep for backup)
- Update all file access to use Media Library

## Configuration

### Tenant-Scoped Disks
```php
// config/media-library.php
return [
    'disk_name' => 'tenant', // Use tenant-scoped disk
    
    'path_generator' => \App\Support\MediaLibrary\TenantPathGenerator::class,
    
    // Custom path: tenants/{tenant_id}/media/{year}/{month}/{media_id}/
];
```

### Custom Path Generator
```php
namespace App\Support\MediaLibrary;

use App\Tenancy\TenantContext;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

class TenantPathGenerator implements PathGenerator
{
    public function getPath(Media $media): string
    {
        $tenant = TenantContext::current();
        $date = $media->created_at;
        
        return sprintf(
            'tenants/%s/media/%s/%s/%s/',
            $tenant->id,
            $date->format('Y'),
            $date->format('m'),
            $media->id
        );
    }
    
    // ... other methods
}
```

## Testing

```php
// Feature test
test('document can upload file using media library', function () {
    $document = Document::factory()->create();
    $file = UploadedFile::fake()->image('test.png');
    
    $document->addMedia($file)->toMediaCollection('original');
    
    expect($document->getFirstMedia('original'))->not->toBeNull()
        ->and($document->getMedia('original'))->toHaveCount(1)
        ->and($document->getFirstMediaUrl('original'))->toContain('test');
});
```

## Resources

- [Spatie Media Library Docs](https://spatie.be/docs/laravel-medialibrary)
- [Image Conversions](https://spatie.be/docs/laravel-medialibrary/v11/converting-images/defining-conversions)
- [Custom Path Generator](https://spatie.be/docs/laravel-medialibrary/v11/advanced-usage/using-a-custom-path-generator)

## Timeline

- **Phase 1-2**: Next sprint after Document Ingestion API is stable
- **Phase 3**: 1-2 days for migration script and testing
- **Phase 4-5**: 1 day for cleanup and optimization

## Notes

- Media Library adds ~3 columns to media table (not documents)
- Supports S3, Cloudinary, DigitalOcean Spaces
- Built-in queue support for image conversions
- Automatic cleanup on model deletion
- Compatible with our multi-tenancy system
