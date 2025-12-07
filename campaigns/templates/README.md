# Campaign Templates

Default campaign templates that are automatically created for new tenants.

## Available Templates

### 1. Simple File Storage (`simple-storage.json`)
**Purpose:** Basic file upload, storage, and notification

**Workflow:**
1. Upload file
2. Store in S3
3. Send email notification

**Best for:** Basic document management, file sharing

---

### 2. E-Signature Workflow (`e-signature-workflow.yaml`)
**Purpose:** Full e-signature flow with KYC verification

**Workflow:**
1. KYC verification (HyperVerge)
2. Electronic signature
3. Store signed document
4. Send notification

**Best for:** Legal documents, contracts, agreements

---

### 3. OCR Document Processing (`ocr-processing.json`)
**Purpose:** Extract and classify text from documents

**Workflow:**
1. OCR text extraction
2. Document classification
3. Schema validation
4. Store processed document
5. Send notification

**Best for:** Invoice processing, receipt scanning, form extraction

---

## Configuration

### Enable/Disable Templates

Edit `.env`:
```env
# Comma-separated list of template slugs
DEFAULT_CAMPAIGN_TEMPLATES=simple-storage,e-signature-workflow,ocr-processing

# Or single template
DEFAULT_CAMPAIGN_TEMPLATES=simple-storage

# Or none (empty)
DEFAULT_CAMPAIGN_TEMPLATES=
```

### Apply Templates to Existing Tenant

```php
use App\Actions\Campaigns\ApplyDefaultTemplates;
use App\Models\Tenant;

$tenant = Tenant::find('01abc');

// Apply all default templates
ApplyDefaultTemplates::run($tenant);

// Apply specific templates
ApplyDefaultTemplates::run($tenant, ['simple-storage', 'ocr-processing']);
```

### Via Artisan Command

```bash
# Apply default templates to tenant
php artisan tinker --execute="
App\Actions\Campaigns\ApplyDefaultTemplates::run(
    App\Models\Tenant::find('01abc')
);
"
```

---

## Creating New Templates

### 1. Create Template File

Create a JSON or YAML file in this directory:
```bash
campaigns/templates/my-workflow.json
```

### 2. Define Campaign

Use the same format as `campaign:import`:
```json
{
  "name": "My Custom Workflow",
  "slug": "my-workflow",
  "description": "Description of workflow",
  "type": "template",
  "state": "active",
  "processors": [
    {
      "id": "step1",
      "type": "ocr",
      "config": {}
    }
  ]
}
```

### 3. Validate Template

```bash
php artisan campaign:import campaigns/templates/my-workflow.json \
  --tenant=<test-tenant-id> \
  --validate-only
```

### 4. Add to Default List

Update `.env`:
```env
DEFAULT_CAMPAIGN_TEMPLATES=simple-storage,my-workflow
```

---

## Template Guidelines

### Naming
- **Slug:** Lowercase, hyphenated (e.g., `simple-storage`)
- **File:** `{slug}.json` or `{slug}.yaml`
- **Name:** User-friendly (e.g., "Simple File Storage")

### Processors
- Use generic step IDs (e.g., `ocr-step`, `s3-step`)
- Include sensible default configs
- Document any required settings

### Settings
- Set appropriate queue priority
- Include locale if applicable
- Add workflow type if needed

### File Types
- Be permissive with MIME types for templates
- Set reasonable size limits
- Consider tenant use cases

---

## Template Variables

Templates support variable substitution (future enhancement):

```json
{
  "config": {
    "bucket": "{{tenant_slug}}-documents",
    "recipients": ["{{uploader.email}}"]
  }
}
```

**Available variables:**
- `{{tenant_id}}` - Tenant ULID
- `{{tenant_slug}}` - Tenant slug
- `{{tenant_name}}` - Tenant name
- `{{uploader.email}}` - Document uploader email

---

## Testing Templates

### Validate Syntax
```bash
# JSON
jq . campaigns/templates/simple-storage.json

# YAML
yq . campaigns/templates/e-signature-workflow.yaml
```

### Test Import
```bash
php artisan campaign:import campaigns/templates/simple-storage.json \
  --tenant=01abc \
  --validate-only
```

### Apply to Test Tenant
```bash
php artisan tinker --execute="
App\Actions\Campaigns\ApplyDefaultTemplates::run(
    App\Models\Tenant::find('01abc'),
    ['simple-storage']
);
"
```

---

## See Also

- `app/Actions/Campaigns/ApplyDefaultTemplates.php` - Template application logic
- `config/campaigns.php` - Template configuration
- `campaigns/examples/` - Additional campaign examples
- `WARP.md` - Campaign import documentation
