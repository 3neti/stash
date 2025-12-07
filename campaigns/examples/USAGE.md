# Campaign Import Usage Examples

## Command Line Interface

The `campaign:import` command supports three input methods:
1. **File** (JSON or YAML)
2. **STDIN** (pipe or redirect)
3. **JSON string** (inline)

### Input Priority
When multiple inputs are provided, the command uses this priority:
```
--json > --stdin > file
```

---

## 1. File Input (Original Method)

### JSON File
```bash
php artisan campaign:import campaigns/examples/invoice-processing.json --tenant=<tenant-id>
```

### YAML File
```bash
php artisan campaign:import campaigns/examples/basic-ocr.yaml --tenant=<tenant-id>
```

### Validate Only (No Import)
```bash
php artisan campaign:import campaign.json --tenant=<tenant-id> --validate-only
```

---

## 2. STDIN Input (New)

### Pipe from File
```bash
cat campaign.json | php artisan campaign:import --stdin --tenant=<tenant-id>
```

### Pipe from Command
```bash
curl https://api.example.com/campaign.json | php artisan campaign:import --stdin --tenant=<tenant-id>
```

### Here Document
```bash
php artisan campaign:import --stdin --tenant=<tenant-id> << 'EOF'
{
  "name": "Quick Test Campaign",
  "type": "custom",
  "processors": [
    {"id": "ocr", "type": "ocr", "config": {}}
  ]
}
EOF
```

### YAML via STDIN
```bash
cat campaign.yaml | php artisan campaign:import --stdin --tenant=<tenant-id>
```

---

## 3. JSON String Input (New)

### Inline JSON
```bash
php artisan campaign:import \
  --json='{"name":"Test","type":"custom","processors":[{"id":"ocr","type":"ocr","config":{}}]}' \
  --tenant=<tenant-id>
```

### From Environment Variable
```bash
CAMPAIGN_JSON='{"name":"Env Campaign","type":"custom","processors":[{"id":"ocr","type":"ocr","config":{}}]}'

php artisan campaign:import --json="$CAMPAIGN_JSON" --tenant=<tenant-id>
```

### From Script
```bash
#!/bin/bash
CAMPAIGN=$(cat << 'JSON'
{
  "name": "Scripted Campaign",
  "type": "template",
  "processors": [
    {"id": "ocr-step", "type": "ocr", "config": {"language": "eng"}}
  ]
}
JSON
)

php artisan campaign:import --json="$CAMPAIGN" --tenant=01abc123
```

---

## 4. Programmatic Usage (PHP)

### From Array
```php
use App\\Actions\\Campaigns\\ImportCampaign;
use App\\Data\\Campaigns\\CampaignImportData;
use App\\Services\\Pipeline\\ProcessorRegistry;

$data = [
    'name' => 'Programmatic Campaign',
    'type' => 'custom',
    'processors' => [
        ['id' => 'ocr', 'type' => 'ocr', 'config' => []],
    ],
];

$dto = CampaignImportData::from($data);
$registry = app(ProcessorRegistry::class);

$campaign = ImportCampaign::run($dto, $registry);
```

### From JSON String
```php
$json = '{"name":"JSON Campaign","type":"custom","processors":[...]}';
$data = json_decode($json, true);
$dto = CampaignImportData::from($data);

$campaign = ImportCampaign::run($dto, $registry);
```

### From API Response
```php
$response = Http::get('https://api.example.com/campaigns/123');
$dto = CampaignImportData::from($response->json());

$campaign = ImportCampaign::run($dto, app(ProcessorRegistry::class));
```

---

## 5. CI/CD Integration

### GitHub Actions
```yaml
- name: Import Campaign
  run: |
    php artisan campaign:import \
      --json='${{ secrets.CAMPAIGN_CONFIG }}' \
      --tenant=${{ secrets.TENANT_ID }}
```

### GitLab CI
```yaml
deploy:
  script:
    - cat config/campaign.json | php artisan campaign:import --stdin --tenant=$TENANT_ID
```

### Docker
```bash
docker exec app php artisan campaign:import --stdin --tenant=01abc < campaign.json
```

---

## 6. Advanced Examples

### Multiple Campaigns from Directory
```bash
#!/bin/bash
TENANT_ID="01abc123"

for file in campaigns/*.json; do
  echo "Importing $file..."
  php artisan campaign:import "$file" --tenant=$TENANT_ID
done
```

### Dynamic Campaign Generation
```bash
#!/bin/bash
# Generate campaign JSON dynamically
CAMPAIGN=$(jq -n \
  --arg name "Dynamic Campaign $(date +%Y%m%d)" \
  --arg type "custom" \
  '{
    name: $name,
    type: $type,
    processors: [
      {id: "ocr", type: "ocr", config: {}}
    ]
  }')

php artisan campaign:import --json="$CAMPAIGN" --tenant=01abc123
```

### Validation Pipeline
```bash
#!/bin/bash
# Validate before importing
if php artisan campaign:import campaign.json --tenant=$TENANT_ID --validate-only; then
  echo "✓ Validation passed"
  php artisan campaign:import campaign.json --tenant=$TENANT_ID
else
  echo "✗ Validation failed"
  exit 1
fi
```

---

## Error Handling

### Common Errors

**No input provided:**
```bash
$ php artisan campaign:import --tenant=123
Parse error: No input provided. Use a file argument, --stdin flag, or --json option.
```

**Invalid JSON:**
```bash
$ php artisan campaign:import --json='{invalid' --tenant=123
Parse error: Invalid JSON: Syntax error
```

**Missing tenant:**
```bash
$ php artisan campaign:import campaign.json
Tenant ID required. Use --tenant=<id>
```

**File not found:**
```bash
$ php artisan campaign:import missing.json --tenant=123
Parse error: File not found: missing.json
```

---

## Best Practices

1. **Use `--validate-only` first** for new campaigns
2. **Store sensitive configs** in environment variables, not files
3. **Use STDIN in CI/CD** to avoid creating temporary files
4. **Use `--json` for testing** with quick inline definitions
5. **Keep files in version control** for production campaigns

---

## See Also

- `campaigns/examples/README.md` - Example campaign files
- `WARP.md` - Full campaign import documentation
- Tests: `tests/Feature/Console/CampaignImportCommandTest.php`
