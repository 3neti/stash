# Campaign Definition Examples

This directory contains example campaign definitions in JSON and YAML formats that can be imported using the `campaign:import` command.

## Usage

Import a campaign definition:

```bash
# JSON format
php artisan campaign:import campaigns/examples/invoice-processing.json --tenant=<tenant-id>

# YAML format
php artisan campaign:import campaigns/examples/invoice-processing.yaml --tenant=<tenant-id>

# Validate without importing
php artisan campaign:import campaigns/examples/basic-ocr.yaml --tenant=<tenant-id> --validate-only
```

## File Structure

### Required Fields

- **name** (string): Campaign display name
- **type** (string): Campaign type - must be one of: `template`, `custom`, `meta`
- **state** (string): Initial campaign state - must be one of: `draft`, `active`, `paused`, `archived`
- **processors** (array): Pipeline processors - must contain at least 1 processor

### Optional Fields

- **slug** (string): URL-friendly identifier (auto-generated from name if omitted)
- **description** (string): Campaign description
- **settings** (object): Campaign settings (locale, queue, etc.)
- **allowed_mime_types** (array): Accepted file MIME types
- **max_file_size_bytes** (integer): Maximum file size in bytes (default: 10485760 = 10MB)
- **max_concurrent_jobs** (integer): Maximum concurrent jobs (default: 10)
- **retention_days** (integer): Data retention days (default: 90)
- **checklist_template** (array): Checklist items for manual review

### Processor Structure

Each processor in the `processors` array must have:

- **id** (string): Unique step identifier within the pipeline
- **type** (string): Processor slug (must match registered processor)
- **config** (object): Processor-specific configuration

## Available Processors

The system currently supports these processor types:

- `ocr` - Tesseract OCR text extraction
- `classification` - OpenAI document classification
- `extraction` - OpenAI structured data extraction
- `dataenricher` - External data enrichment
- `ekycverification` - Identity verification
- `electronicsignature` - E-signature handling
- `emailnotifier` - Email notifications
- `openaivision` - OpenAI Vision processing
- `s3storage` - S3 storage upload
- `schemavalidator` - JSON schema validation
- `csvimport` - CSV file import with validation

## Example: Invoice Processing

```yaml
name: Invoice Processing
slug: invoice-processing
description: Extract and validate invoice data from uploaded documents
type: template
state: active

processors:
  - id: ocr
    type: ocr
    config:
      language: eng
      psm: 3
      dpi: 300

  - id: classify
    type: classification
    config:
      categories:
        - invoice
        - receipt
        - contract
        - other
      model: gpt-4o-mini
      temperature: 0.3
      min_confidence: 0.7

  - id: extract
    type: extraction
    config:
      model: gpt-4o-mini
      schema:
        invoice:
          - invoice_number
          - date
          - vendor
          - total_amount
        receipt:
          - merchant
          - date
          - total

settings:
  locale: en
  queue: high-priority

allowed_mime_types:
  - application/pdf
  - image/png
  - image/jpeg

max_file_size_bytes: 10485760
max_concurrent_jobs: 10
retention_days: 90
```

## Example: Basic OCR (Minimal)

```yaml
name: Basic OCR
description: Simple OCR text extraction
type: custom
state: draft

processors:
  - id: ocr
    type: ocr
    config:
      language: eng
      psm: 3
```

## Validation

The import command validates:

1. **Required fields**: name, type, state, processors
2. **Enum values**: type (template|custom|meta), state (draft|active|paused|archived)
3. **Processors**: At least 1 processor, all types exist in registry
4. **Step IDs**: Unique within the pipeline
5. **JSON/YAML syntax**: Valid file format

Use `--validate-only` to check a definition without creating the campaign.

## Creating Custom Definitions

1. Copy an existing example
2. Modify name, slug, and description
3. Configure processors for your workflow
4. Add optional fields as needed
5. Validate: `php artisan campaign:import <file> --tenant=<id> --validate-only`
6. Import: `php artisan campaign:import <file> --tenant=<id>`

## Common Processor Configurations

### OCR Processor
```yaml
- id: ocr
  type: ocr
  config:
    language: eng  # Tesseract language code
    psm: 3         # Page segmentation mode (3 = automatic)
    dpi: 300       # DPI for PDF rasterization
```

### Classification Processor
```yaml
- id: classify
  type: classification
  config:
    categories:
      - invoice
      - receipt
      - contract
    model: gpt-4o-mini
    temperature: 0.3
    min_confidence: 0.7
```

### Extraction Processor
```yaml
- id: extract
  type: extraction
  config:
    model: gpt-4o-mini
    schema:
      invoice:
        - invoice_number
        - date
        - vendor
        - total_amount
```

## Troubleshooting

### "Unknown processor type"
- Check processor slug matches registered processors
- Run `php artisan tinker` and execute: `app(\App\Services\Pipeline\ProcessorRegistry::class)->getRegisteredIds()`

### "Duplicate step ID"
- Ensure all processor `id` fields are unique within the pipeline

### "Validation failed"
- Check required fields are present
- Verify `type` is one of: template, custom, meta
- Verify `state` is one of: draft, active, paused, archived
- Ensure `processors` array has at least 1 item

### "Tenant not found"
- Verify tenant ID exists in central database
- List tenants: `php artisan tenant:list`
