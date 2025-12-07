# TODO: Drag-and-Drop Processor Pipeline Builder UX

## Overview

Create an intuitive visual interface for building document processing pipelines when creating or editing campaigns. Users should be able to drag processors from a palette, order them sequentially, and configure each step—without needing to understand technical implementation details like slugs or JSON structures.

## Current State

### Campaign Pipeline Configuration
- **Model**: `Campaign` (`app/Models/Campaign.php`)
- **Storage**: `pipeline_config` JSON column (array cast)
- **Structure**:
  ```json
  {
    "processors": [
      {
        "id": "ocr",                    // Unique step identifier within pipeline
        "type": "ocr",                  // Processor slug (maps to registered processor)
        "config": {                     // Processor-specific configuration
          "language": "eng",
          "psm": 3
        }
      },
      {
        "id": "classifier",
        "type": "classification",
        "config": {
          "categories": ["invoice", "receipt", "contract", "other"],
          "model": "gpt-4o-mini"
        }
      }
    ]
  }
  ```

### Available Processors
Currently 11 active processors in `app/Processors/`:

| Processor | Slug | Category | Purpose |
|-----------|------|----------|---------|
| `OcrProcessor` | `ocr` | ocr | Extract text from images/PDFs via Tesseract |
| `ClassificationProcessor` | `classification` | classification | Categorize documents via OpenAI |
| `ExtractionProcessor` | `extraction` | extraction | Extract structured fields via OpenAI |
| `DataEnricherProcessor` | `dataenricher` | enrichment | Enrich data with external sources |
| `EKycVerificationProcessor` | `ekycverification` | verification | Verify identity documents |
| `ElectronicSignatureProcessor` | `electronicsignature` | signature | Handle e-signatures |
| `EmailNotifierProcessor` | `emailnotifier` | notification | Send email notifications |
| `OpenAIVisionProcessor` | `openaivision` | vision | Process images with OpenAI Vision |
| `S3StorageProcessor` | `s3storage` | storage | Upload to S3 |
| `SchemaValidatorProcessor` | `schemavalidator` | validation | Validate against JSON schema |
| `CsvImportProcessor` | `csvimport` | import | Process CSV file imports with validation |

### Processor Registry
- **Service**: `ProcessorRegistry` (`app/Services/Pipeline/ProcessorRegistry.php`)
- **Auto-discovery**: Scans `app/Processors/` and generates slugs automatically
- **Database sync**: `Processor` model stores metadata (name, slug, class_name, config_schema, output_schema)
- **Lookup**: Registry resolves slug → processor instance

### Pipeline Execution
- **Service**: `PipelineOrchestrator` (`app/Services/Pipeline/PipelineOrchestrator.php`)
- **Workflow**: `DocumentProcessingWorkflow` (`app/Workflows/DocumentProcessingWorkflow.php`)
- Uses Laravel Workflow for durable execution with automatic checkpointing
- Tracks execution via `ProcessorExecution` model

## Proposed UX Design

### 1. Pipeline Builder Interface

#### A. Processor Palette (Left Sidebar)
**Visual Elements**:
- Grouped by category (OCR, Classification, Extraction, Validation, Storage, etc.)
- Each processor card shows:
  - **Icon**: Visual identifier (from `Processor.icon` field)
  - **Name**: Human-readable (e.g., "Tesseract OCR" not "ocr")
  - **Category badge**: Color-coded by type
  - **Short description**: One-line purpose (from `Processor.description`)
  - **Drag handle**: Visual affordance for dragging

**Interactions**:
- Collapsible category groups
- Search/filter by name, category, or keyword
- Tooltip on hover showing:
  - Full description
  - Required dependencies
  - Estimated cost (tokens/credits if applicable)
  - Supported input types (mime types)
- Click "Info" icon → opens processor documentation (`Processor.documentation_url`)

**Data Source**:
```php
// Query active processors grouped by category
$processors = Processor::active()
    ->orderBy('category')
    ->orderBy('name')
    ->get()
    ->groupBy('category');
```

#### B. Pipeline Canvas (Center Area)
**Visual Elements**:
- Ordered list of pipeline steps (vertical or horizontal flow)
- Each step card shows:
  - **Step number**: Auto-numbered (1, 2, 3...)
  - **Processor name + icon**
  - **Step ID**: Editable unique identifier (default: slug, e.g., "ocr", "ocr_2")
  - **Configuration status**: Icon indicator (✓ configured, ⚠ needs config, ✗ invalid)
  - **Drag handle**: Reorder within pipeline
  - **Remove button**: Delete step from pipeline
  - **Edit button**: Open configuration panel

**Interactions**:
- Drag processors from palette → drop onto canvas
- Drag steps within canvas to reorder
- Click step → opens config side panel
- Visual connectors/arrows between steps
- Empty state: "Drag processors here to build your pipeline"

**Validation Indicators**:
- **Green border**: Step fully configured
- **Yellow border**: Optional config missing
- **Red border**: Required config missing or invalid
- Dependency warnings: "This processor requires OCR output"

#### C. Configuration Panel (Right Sidebar)
Opens when a step is clicked. Shows:

**Step Settings**:
- **Step ID**: Text input (must be unique, alphanumeric + underscore)
- **Processor Type**: Read-only display (slug)
- **Processor Name**: Read-only display

**Processor Configuration**:
- Dynamic form generated from `Processor.config_schema` (JSON Schema)
- Common field types:
  - Text inputs (API keys, paths)
  - Dropdowns (model selection: gpt-4o-mini, gpt-4o)
  - Multi-select (categories: invoice, receipt, contract)
  - Number inputs (temperature, confidence threshold)
  - Toggle switches (boolean flags)
  - JSON editor (advanced schemas)

**Credential Management**:
- Show required credentials (e.g., "Requires: OpenAI API Key")
- Status indicators:
  - ✓ Configured at campaign level
  - ✓ Configured at tenant level
  - ✓ Configured at system level
  - ✗ Not configured (show setup button)
- Link to credential vault configuration

**Dependencies**:
- Show required previous processors (e.g., Classification requires OCR)
- Auto-suggest adding missing dependencies

**Preview**:
- Show expected input/output schema
- Example configuration
- Estimated cost per execution (if applicable)

**Actions**:
- **Save**: Validate and apply configuration
- **Cancel**: Discard changes
- **Restore Defaults**: Reset to default config

### 2. User Workflows

#### Creating a New Pipeline
1. User creates new campaign
2. Opens "Pipeline Builder" tab
3. Drags "Tesseract OCR" from palette → canvas (becomes step 1)
4. Clicks step 1 → configures language="eng", psm=3
5. Drags "OpenAI Classification" → canvas (becomes step 2)
6. Clicks step 2 → configures categories, model
7. Drags "OpenAI Extraction" → canvas (becomes step 3)
8. Clicks step 3 → configures extraction schema
9. Clicks "Save Campaign"

**Behind the scenes**:
```json
{
  "processors": [
    {"id": "ocr", "type": "ocr", "config": {"language": "eng", "psm": 3}},
    {"id": "classification", "type": "classification", "config": {"categories": ["invoice","receipt"], "model": "gpt-4o-mini"}},
    {"id": "extraction", "type": "extraction", "config": {"schema": {"invoice": ["invoice_number", "date"]}, "model": "gpt-4o-mini"}}
  ]
}
```

#### Editing Existing Pipeline
1. User opens campaign edit page
2. Pipeline builder shows current steps
3. User drags step 2 (classification) above step 1 (ocr)
4. Validation error: "Classification requires OCR output"
5. User reverts change
6. User clicks step 3 → edits extraction schema
7. Clicks "Save"

#### Using Templates
1. User clicks "Start from Template"
2. Chooses "Invoice Processing" template
3. Pipeline pre-populated with: OCR → Classification → Extraction → Validation → S3 Storage
4. User customizes configurations
5. Saves as new campaign

### 3. Advanced Features

#### A. Validation & Error Prevention
**Pre-save validation**:
- ✓ All step IDs unique
- ✓ All processor types registered and active
- ✓ Required config fields present
- ✓ Dependencies satisfied (e.g., Classification after OCR)
- ✓ Credentials available for processors requiring them
- ✓ No circular dependencies (if branching supported)

**Real-time validation**:
- Highlight invalid steps with red border
- Show error messages in config panel
- Disable "Save" button until valid

**Dependency checking**:
```php
// Example: Classification processor requires extracted_text from OCR
public function getDependencies(): array
{
    return [
        'required_previous' => ['ocr'],
        'required_metadata' => ['extracted_text'],
    ];
}
```

#### B. Pipeline Templates
Pre-configured pipelines for common use cases:

**Invoice Processing Template**:
```json
{
  "name": "Invoice Processing",
  "description": "Extract and validate invoice data",
  "processors": [
    {"id": "ocr", "type": "ocr", "config": {"language": "eng"}},
    {"id": "classify", "type": "classification", "config": {"categories": ["invoice", "receipt", "other"]}},
    {"id": "extract", "type": "extraction", "config": {"schema": {"invoice": ["invoice_number", "date", "vendor", "total_amount"]}}},
    {"id": "validate", "type": "schemavalidator", "config": {"schema": {"type": "object", "required": ["invoice_number", "total_amount"]}}},
    {"id": "store", "type": "s3storage", "config": {"bucket": "invoices"}}
  ]
}
```

**eKYC Verification Template**:
```json
{
  "name": "Identity Verification",
  "processors": [
    {"id": "ocr", "type": "ocr", "config": {"language": "eng"}},
    {"id": "vision", "type": "openaivision", "config": {"prompt": "Extract ID information"}},
    {"id": "verify", "type": "ekycverification", "config": {"provider": "jumio"}},
    {"id": "notify", "type": "emailnotifier", "config": {"template": "verification_complete"}}
  ]
}
```

**CSV Import Template**:
```json
{
  "name": "CSV Data Import",
  "processors": [
    {"id": "csvimport", "type": "csvimport", "config": {
      "has_header": true,
      "mappings": {"column_a": "field1", "column_b": "field2"},
      "validations": ["required:field1", "numeric:field2"]
    }}
  ]
}
```

#### C. Cost Estimation
Show estimated cost per document before saving:

```
Estimated Cost per Document:
- OCR: 0 credits (free)
- Classification (gpt-4o-mini): ~500 tokens ≈ $0.02
- Extraction (gpt-4o-mini): ~1000 tokens ≈ $0.05
Total: ~$0.07 per document
```

**Implementation**:
```php
// In Processor model or service
public function estimateCost(array $config): array
{
    return [
        'tokens' => 500,
        'credits' => 0.02,
        'currency' => 'USD',
    ];
}
```

#### D. Pipeline Visualization
After saving, show visual pipeline flow diagram:
```
┌─────┐    ┌──────────────┐    ┌────────────┐    ┌────────────┐
│ OCR │───>│Classification│───>│ Extraction │───>│ Validation │
└─────┘    └──────────────┘    └────────────┘    └────────────┘
```

#### E. Conditional Branching (Future)
Support conditional execution based on previous step output:

```json
{
  "id": "extract_invoice",
  "type": "extraction",
  "config": {"schema": "invoice_schema"},
  "condition": {
    "field": "classify.category",
    "operator": "equals",
    "value": "invoice"
  }
}
```

#### F. Parallel Execution (Future)
Support running multiple processors simultaneously:

```json
{
  "processors": [
    {"id": "ocr", "type": "ocr"},
    {
      "id": "parallel_group",
      "type": "parallel",
      "steps": [
        {"id": "classify", "type": "classification"},
        {"id": "extract", "type": "extraction"}
      ]
    }
  ]
}
```

## Implementation Plan

### Phase 1: Backend Foundation
**Tasks**:
1. ✅ Processor model with slug, config_schema, output_schema fields (already exists)
2. ✅ ProcessorRegistry with auto-discovery (already exists)
3. ✅ Campaign.pipeline_config JSON structure (already exists)
4. 🔲 Add `icon` field to Processor model
5. 🔲 Add `documentation_url` field to Processor model
6. 🔲 Populate config_schema for all existing processors
7. 🔲 Create ProcessorSeeder to populate database with processor metadata
8. 🔲 Add validation service: `PipelineConfigValidator`
9. 🔲 Add dependency checking: `ProcessorDependencyResolver`

**Code Example**:
```php
// app/Services/Pipeline/PipelineConfigValidator.php
class PipelineConfigValidator
{
    public function validate(array $pipelineConfig): ValidationResult
    {
        $errors = [];
        
        // Check unique step IDs
        $ids = array_column($pipelineConfig['processors'], 'id');
        if (count($ids) !== count(array_unique($ids))) {
            $errors[] = 'Step IDs must be unique';
        }
        
        // Check processor types exist
        foreach ($pipelineConfig['processors'] as $step) {
            if (!$this->registry->has($step['type'])) {
                $errors[] = "Unknown processor type: {$step['type']}";
            }
        }
        
        // Check dependencies
        foreach ($pipelineConfig['processors'] as $index => $step) {
            $processor = $this->registry->get($step['type']);
            $dependencies = $processor->getDependencies();
            
            foreach ($dependencies as $dep) {
                if (!$this->isDependencySatisfied($dep, $pipelineConfig, $index)) {
                    $errors[] = "Step '{$step['id']}' requires {$dep}";
                }
            }
        }
        
        return new ValidationResult(empty($errors), $errors);
    }
}
```

### Phase 2: API Endpoints
**Routes** (`routes/web.php` or `routes/api.php`):
```php
// Get all available processors
GET /api/processors
Response: [
    {
        "id": "01ABC...",
        "slug": "ocr",
        "name": "Tesseract OCR",
        "category": "ocr",
        "description": "Extract text from images and PDFs",
        "icon": "mdi:text-recognition",
        "config_schema": {...},
        "output_schema": {...},
        "dependencies": [],
        "is_active": true
    },
    ...
]

// Get processor by slug
GET /api/processors/{slug}

// Validate pipeline configuration
POST /api/campaigns/{campaign}/pipeline/validate
Request: {"processors": [...]}
Response: {
    "valid": true,
    "errors": [],
    "warnings": ["Classification accuracy improves with higher confidence threshold"],
    "estimated_cost": {"tokens": 1500, "credits": 0.07}
}

// Get pipeline templates
GET /api/pipeline-templates
Response: [
    {
        "id": "invoice-processing",
        "name": "Invoice Processing",
        "description": "Extract and validate invoice data",
        "category": "finance",
        "pipeline_config": {...}
    },
    ...
]
```

**Controllers**:
```php
// app/Http/Controllers/Api/ProcessorController.php
class ProcessorController extends Controller
{
    public function index(ProcessorRegistry $registry)
    {
        return Processor::active()
            ->orderBy('category')
            ->orderBy('name')
            ->get();
    }
    
    public function show(string $slug, ProcessorRegistry $registry)
    {
        return Processor::where('slug', $slug)->firstOrFail();
    }
}

// app/Http/Controllers/Api/PipelineController.php
class PipelineController extends Controller
{
    public function validate(
        Campaign $campaign,
        Request $request,
        PipelineConfigValidator $validator
    ) {
        $pipelineConfig = $request->validate([
            'processors' => 'required|array',
            'processors.*.id' => 'required|string',
            'processors.*.type' => 'required|string',
            'processors.*.config' => 'required|array',
        ]);
        
        $result = $validator->validate($pipelineConfig);
        
        return response()->json([
            'valid' => $result->isValid(),
            'errors' => $result->getErrors(),
            'warnings' => $result->getWarnings(),
            'estimated_cost' => $this->estimateCost($pipelineConfig),
        ]);
    }
}
```

### Phase 3: Frontend Components (Vue 3 + Inertia + Reka UI)

**Component Structure**:
```
resources/js/pages/campaigns/
├── Edit.vue                          # Campaign edit page
└── components/
    └── PipelineBuilder/
        ├── PipelineBuilder.vue       # Main wrapper component
        ├── ProcessorPalette.vue      # Left sidebar with draggable processors
        ├── PipelineCanvas.vue        # Center area with pipeline steps
        ├── PipelineStep.vue          # Individual step card
        ├── ConfigurationPanel.vue    # Right sidebar for step config
        ├── ProcessorIcon.vue         # Processor icon component
        └── templates/
            └── TemplateSelector.vue  # Template selection modal
```

**Key Components**:

#### A. PipelineBuilder.vue
```vue
<script setup lang="ts">
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import ProcessorPalette from './ProcessorPalette.vue';
import PipelineCanvas from './PipelineCanvas.vue';
import ConfigurationPanel from './ConfigurationPanel.vue';

const props = defineProps<{
  campaign: Campaign;
  processors: Processor[];
}>();

const form = useForm({
  pipeline_config: props.campaign.pipeline_config || { processors: [] },
});

const selectedStep = ref<number | null>(null);

const addProcessor = (processor: Processor) => {
  const stepId = generateUniqueStepId(processor.slug);
  form.pipeline_config.processors.push({
    id: stepId,
    type: processor.slug,
    config: getDefaultConfig(processor),
  });
};

const removeStep = (index: number) => {
  form.pipeline_config.processors.splice(index, 1);
  if (selectedStep.value === index) {
    selectedStep.value = null;
  }
};

const reorderSteps = (oldIndex: number, newIndex: number) => {
  const [removed] = form.pipeline_config.processors.splice(oldIndex, 1);
  form.pipeline_config.processors.splice(newIndex, 0, removed);
};

const updateStepConfig = (index: number, config: any) => {
  form.pipeline_config.processors[index].config = config;
};

const savePipeline = () => {
  form.put(route('campaigns.update', props.campaign.id));
};
</script>

<template>
  <div class="flex h-screen">
    <ProcessorPalette
      :processors="processors"
      @add-processor="addProcessor"
    />
    
    <PipelineCanvas
      v-model:steps="form.pipeline_config.processors"
      :selected-step="selectedStep"
      @select-step="selectedStep = $event"
      @remove-step="removeStep"
      @reorder="reorderSteps"
    />
    
    <ConfigurationPanel
      v-if="selectedStep !== null"
      :step="form.pipeline_config.processors[selectedStep]"
      :processor="processors.find(p => p.slug === form.pipeline_config.processors[selectedStep].type)"
      @update="updateStepConfig(selectedStep, $event)"
      @close="selectedStep = null"
    />
    
    <div class="fixed bottom-4 right-4 flex gap-2">
      <Button variant="outline" @click="validatePipeline">
        Validate
      </Button>
      <Button @click="savePipeline" :disabled="form.processing">
        Save Pipeline
      </Button>
    </div>
  </div>
</template>
```

#### B. ProcessorPalette.vue
```vue
<script setup lang="ts">
import { ref, computed } from 'vue';
import { useDraggable } from '@vueuse/core';
import ProcessorIcon from './ProcessorIcon.vue';

const props = defineProps<{
  processors: Processor[];
}>();

const emit = defineEmits<{
  addProcessor: [processor: Processor];
}>();

const searchQuery = ref('');
const expandedCategories = ref<Set<string>>(new Set());

const processorsByCategory = computed(() => {
  const filtered = props.processors.filter(p => 
    p.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
    p.description?.toLowerCase().includes(searchQuery.value.toLowerCase())
  );
  
  return filtered.reduce((acc, processor) => {
    if (!acc[processor.category]) {
      acc[processor.category] = [];
    }
    acc[processor.category].push(processor);
    return acc;
  }, {} as Record<string, Processor[]>);
});

const toggleCategory = (category: string) => {
  if (expandedCategories.value.has(category)) {
    expandedCategories.value.delete(category);
  } else {
    expandedCategories.value.add(category);
  }
};
</script>

<template>
  <aside class="w-80 border-r bg-muted/30 overflow-y-auto">
    <div class="p-4 border-b">
      <h2 class="text-lg font-semibold mb-2">Processors</h2>
      <Input
        v-model="searchQuery"
        placeholder="Search processors..."
        class="w-full"
      />
    </div>
    
    <div class="p-2">
      <div
        v-for="(processors, category) in processorsByCategory"
        :key="category"
        class="mb-2"
      >
        <button
          @click="toggleCategory(category)"
          class="w-full flex items-center justify-between p-2 hover:bg-muted rounded"
        >
          <span class="font-medium capitalize">{{ category }}</span>
          <ChevronDown
            :class="{ 'rotate-180': expandedCategories.has(category) }"
            class="transition-transform"
          />
        </button>
        
        <Collapsible :open="expandedCategories.has(category)">
          <div class="pl-2 space-y-1">
            <div
              v-for="processor in processors"
              :key="processor.id"
              draggable="true"
              @dragstart="$emit('addProcessor', processor)"
              class="p-3 border rounded cursor-move hover:bg-accent hover:border-accent-foreground"
            >
              <div class="flex items-start gap-2">
                <ProcessorIcon :icon="processor.icon" />
                <div class="flex-1 min-w-0">
                  <div class="font-medium text-sm">{{ processor.name }}</div>
                  <div class="text-xs text-muted-foreground line-clamp-2">
                    {{ processor.description }}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </Collapsible>
      </div>
    </div>
  </aside>
</template>
```

#### C. PipelineCanvas.vue
```vue
<script setup lang="ts">
import { useSortable } from '@vueuse/integrations/useSortable';
import PipelineStep from './PipelineStep.vue';

const props = defineProps<{
  steps: PipelineStep[];
  selectedStep: number | null;
}>();

const emit = defineEmits<{
  'update:steps': [steps: PipelineStep[]];
  selectStep: [index: number];
  removeStep: [index: number];
  reorder: [oldIndex: number, newIndex: number];
}>();

const stepsContainer = ref<HTMLElement | null>(null);

useSortable(stepsContainer, props.steps, {
  animation: 150,
  onUpdate: (event) => {
    emit('reorder', event.oldIndex, event.newIndex);
  },
});
</script>

<template>
  <main class="flex-1 overflow-y-auto p-8">
    <div class="max-w-4xl mx-auto">
      <div class="mb-6">
        <h2 class="text-2xl font-bold">Pipeline Builder</h2>
        <p class="text-muted-foreground">
          Drag processors from the left to build your pipeline
        </p>
      </div>
      
      <div
        v-if="steps.length === 0"
        class="border-2 border-dashed rounded-lg p-12 text-center text-muted-foreground"
      >
        <div class="text-4xl mb-4">📋</div>
        <p>Drag processors here to build your pipeline</p>
      </div>
      
      <div v-else ref="stepsContainer" class="space-y-4">
        <PipelineStep
          v-for="(step, index) in steps"
          :key="step.id"
          :step="step"
          :index="index"
          :selected="selectedStep === index"
          @click="emit('selectStep', index)"
          @remove="emit('removeStep', index)"
        />
      </div>
    </div>
  </main>
</template>
```

#### D. ConfigurationPanel.vue
```vue
<script setup lang="ts">
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps<{
  step: PipelineStep;
  processor: Processor;
}>();

const emit = defineEmits<{
  update: [config: any];
  close: [];
}>();

const localConfig = ref({ ...props.step.config });

watch(() => props.step, (newStep) => {
  localConfig.value = { ...newStep.config };
}, { deep: true });

const saveConfig = () => {
  emit('update', localConfig.value);
};

const renderConfigField = (fieldSchema: any, fieldName: string) => {
  // Render appropriate input based on JSON schema type
  switch (fieldSchema.type) {
    case 'string':
      return fieldSchema.enum ? 'select' : 'input';
    case 'number':
      return 'number';
    case 'boolean':
      return 'checkbox';
    case 'array':
      return 'multi-select';
    case 'object':
      return 'json-editor';
    default:
      return 'input';
  }
};
</script>

<template>
  <aside class="w-96 border-l bg-background overflow-y-auto">
    <div class="p-4 border-b flex items-center justify-between">
      <h3 class="text-lg font-semibold">Configure Step</h3>
      <Button variant="ghost" size="icon" @click="emit('close')">
        <X class="h-4 w-4" />
      </Button>
    </div>
    
    <div class="p-4 space-y-6">
      <!-- Step ID -->
      <div>
        <Label for="step-id">Step ID</Label>
        <Input
          id="step-id"
          v-model="step.id"
          placeholder="Unique step identifier"
        />
        <p class="text-xs text-muted-foreground mt-1">
          Must be unique within the pipeline
        </p>
      </div>
      
      <!-- Processor Info -->
      <div class="p-4 bg-muted rounded-lg">
        <div class="flex items-center gap-2 mb-2">
          <ProcessorIcon :icon="processor.icon" />
          <div>
            <div class="font-medium">{{ processor.name }}</div>
            <div class="text-xs text-muted-foreground">{{ processor.category }}</div>
          </div>
        </div>
        <p class="text-sm">{{ processor.description }}</p>
      </div>
      
      <!-- Dynamic Config Fields -->
      <div v-if="processor.config_schema">
        <h4 class="font-medium mb-3">Configuration</h4>
        <div
          v-for="(fieldSchema, fieldName) in processor.config_schema.properties"
          :key="fieldName"
          class="mb-4"
        >
          <Label :for="fieldName">
            {{ fieldSchema.title || fieldName }}
            <span v-if="processor.config_schema.required?.includes(fieldName)" class="text-destructive">*</span>
          </Label>
          
          <!-- String/Text Input -->
          <Input
            v-if="fieldSchema.type === 'string' && !fieldSchema.enum"
            :id="fieldName"
            v-model="localConfig[fieldName]"
            :placeholder="fieldSchema.description"
          />
          
          <!-- Select Dropdown -->
          <Select
            v-else-if="fieldSchema.enum"
            v-model="localConfig[fieldName]"
          >
            <option v-for="option in fieldSchema.enum" :key="option" :value="option">
              {{ option }}
            </option>
          </Select>
          
          <!-- Number Input -->
          <Input
            v-else-if="fieldSchema.type === 'number'"
            :id="fieldName"
            v-model.number="localConfig[fieldName]"
            type="number"
            :min="fieldSchema.minimum"
            :max="fieldSchema.maximum"
          />
          
          <!-- Boolean Checkbox -->
          <Checkbox
            v-else-if="fieldSchema.type === 'boolean'"
            :id="fieldName"
            v-model="localConfig[fieldName]"
          />
          
          <!-- Array/Multi-select -->
          <div v-else-if="fieldSchema.type === 'array'">
            <TagInput v-model="localConfig[fieldName]" />
          </div>
          
          <p v-if="fieldSchema.description" class="text-xs text-muted-foreground mt-1">
            {{ fieldSchema.description }}
          </p>
        </div>
      </div>
      
      <!-- Credentials Check -->
      <div v-if="processor.requires_credentials">
        <h4 class="font-medium mb-2">Required Credentials</h4>
        <div class="space-y-2">
          <div class="flex items-center gap-2 text-sm">
            <CheckCircle class="h-4 w-4 text-success" />
            <span>OpenAI API Key configured</span>
          </div>
        </div>
      </div>
      
      <!-- Actions -->
      <div class="flex gap-2">
        <Button @click="saveConfig" class="flex-1">
          Save Configuration
        </Button>
        <Button variant="outline" @click="localConfig = {}">
          Reset
        </Button>
      </div>
    </div>
  </aside>
</template>
```

### Phase 4: Testing
**Test Coverage**:
1. Unit tests for `PipelineConfigValidator`
2. Unit tests for `ProcessorDependencyResolver`
3. Feature tests for pipeline API endpoints
4. Browser tests (Dusk) for drag-and-drop UX
5. Integration tests for end-to-end pipeline execution

**Example Tests**:
```php
// tests/Unit/Services/PipelineConfigValidatorTest.php
test('validates unique step IDs', function () {
    $config = [
        'processors' => [
            ['id' => 'ocr', 'type' => 'ocr', 'config' => []],
            ['id' => 'ocr', 'type' => 'classification', 'config' => []], // Duplicate ID
        ],
    ];
    
    $validator = app(PipelineConfigValidator::class);
    $result = $validator->validate($config);
    
    expect($result->isValid())->toBeFalse()
        ->and($result->getErrors())->toContain('Step IDs must be unique');
});

// tests/Browser/PipelineBuilderTest.php
test('user can drag processor to canvas', function () {
    $this->browse(function (Browser $browser) {
        $browser->loginAs($user)
            ->visit(route('campaigns.edit', $campaign))
            ->click('@pipeline-builder-tab')
            ->drag('@processor-ocr', '@pipeline-canvas')
            ->assertSee('Tesseract OCR')
            ->click('@step-0')
            ->assertVisible('@config-panel')
            ->type('language', 'eng')
            ->click('@save-config')
            ->click('@save-pipeline')
            ->assertPathIs(route('campaigns.show', $campaign));
    });
});
```

### Phase 5: Documentation
1. User guide: "Building Your First Pipeline"
2. Processor development guide: "Creating Custom Processors"
3. API documentation for pipeline endpoints
4. Video tutorial: "Drag-and-Drop Pipeline Builder"

### Phase 6: Polish & Optimization
1. Add keyboard shortcuts (Delete key to remove step, Cmd+S to save)
2. Add undo/redo functionality
3. Add pipeline export/import (JSON download/upload)
4. Add pipeline version history
5. Add collaborative editing (real-time with Pusher/Echo)
6. Add pipeline analytics (most-used processors, success rates)

## Technical Considerations

### Database Schema Changes
```sql
-- Add new columns to processors table
ALTER TABLE processors ADD COLUMN icon VARCHAR(255);
ALTER TABLE processors ADD COLUMN documentation_url TEXT;
ALTER TABLE processors ADD COLUMN estimated_cost_per_execution DECIMAL(10,4);

-- Create pipeline_templates table
CREATE TABLE pipeline_templates (
    id CHAR(26) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    description TEXT,
    category VARCHAR(100),
    pipeline_config JSON NOT NULL,
    is_system BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### Performance Optimization
- Cache processor list in Redis (rarely changes)
- Lazy-load processor config schemas
- Debounce validation API calls
- Use virtual scrolling for large processor palettes

### Security
- Validate pipeline_config against JSON schema on backend
- Sanitize user inputs in config values
- Check user permissions before saving campaigns
- Rate-limit validation API endpoint

### Accessibility
- Full keyboard navigation support
- ARIA labels for screen readers
- Focus management during drag-and-drop
- High-contrast mode support

## Success Metrics

### User Experience
- **Time to create pipeline**: < 2 minutes for 3-step pipeline
- **User satisfaction**: > 4.5/5 on usability survey
- **Error rate**: < 5% invalid pipeline configurations saved

### Technical
- **API response time**: < 200ms for validation endpoint
- **Frontend bundle size**: < 500KB for pipeline builder components
- **Test coverage**: > 85% for pipeline-related code

### Business
- **Adoption rate**: 80% of new campaigns use visual builder (vs. manual JSON editing)
- **Template usage**: 50% of pipelines start from templates
- **Support tickets**: 50% reduction in pipeline configuration questions

## Future Enhancements

### Phase 7: Advanced Features (6+ months)
1. **Conditional branching**: Route documents based on classification
2. **Parallel execution**: Run multiple processors simultaneously
3. **Sub-pipelines**: Reusable processor groups
4. **A/B testing**: Compare different pipeline configurations
5. **Pipeline marketplace**: Share/sell custom pipelines
6. **AI-powered suggestions**: "Users who used OCR also added Classification"
7. **Visual debugging**: Inspect document state at each pipeline step
8. **Performance profiling**: Identify bottleneck processors

## References

### Existing Code
- `app/Models/Campaign.php` - Campaign model with pipeline_config
- `app/Models/Processor.php` - Processor model
- `app/Services/Pipeline/ProcessorRegistry.php` - Processor registration
- `app/Services/Pipeline/PipelineOrchestrator.php` - Pipeline execution
- `app/Workflows/DocumentProcessingWorkflow.php` - Laravel Workflow integration
- `tests/Feature/PipelineEndToEndTest.php` - Pipeline testing examples

### Documentation
- `LARAVEL_WORKFLOW_ARCHITECTURE.md` - Workflow system architecture
- `PIPELINE_EXPLANATION.md` - Pipeline framework explanation
- `WARP.md` - Project conventions and patterns

### Inspiration
- **Zapier**: Drag-and-drop workflow builder
- **n8n**: Open-source workflow automation
- **Node-RED**: Visual programming for IoT
- **GitHub Actions**: YAML-based pipeline configuration
- **Azure Logic Apps**: Low-code integration platform

---

**Created**: 2024-12-07  
**Status**: TODO  
**Priority**: Medium  
**Estimated Effort**: 3-4 weeks (full implementation)
