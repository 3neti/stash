# Quick Wins UI Integration Guide

This guide explains how to integrate the Progress Tracker and Processing Metrics components into the document detail page.

## Components Overview

### 1. ProgressTracker Component
- **Location**: `resources/js/components/ProgressTracker.vue`
- **Purpose**: Displays real-time document processing progress
- **Props**: `documentUuid` (required, string)
- **Updates**: Every 2 seconds via polling

### 2. ProcessingMetrics Component
- **Location**: `resources/js/components/ProcessingMetrics.vue`
- **Purpose**: Displays processor execution metrics (time, status)
- **Props**: `documentId` (required, string)
- **Updates**: Every 3 seconds via polling

---

## Integration Example

### Document Detail Page (Show.vue)

```vue
<script setup lang="ts">
import { ref } from 'vue';
import ProgressTracker from '@/components/ProgressTracker.vue';
import ProcessingMetrics from '@/components/ProcessingMetrics.vue';

interface Document {
    id: string;
    uuid: string;
    status: string;
    // ... other fields
}

interface Props {
    document: Document;
}

defineProps<Props>();
</script>

<template>
    <div class="space-y-6">
        <!-- Document Header -->
        <div class="mb-6">
            <h1 class="text-3xl font-bold">{{ document.original_filename }}</h1>
            <p class="text-gray-600">Status: {{ document.state }}</p>
        </div>

        <!-- Progress Tracking Section -->
        <div v-if="document.state === 'processing'" class="space-y-4">
            <h2 class="text-xl font-semibold">Processing Status</h2>
            <ProgressTracker :document-uuid="document.uuid" />
        </div>

        <!-- Processing Metrics Section -->
        <div class="space-y-4">
            <h2 class="text-xl font-semibold">Execution Metrics</h2>
            <ProcessingMetrics :document-id="document.id" />
        </div>

        <!-- Document Details -->
        <div class="rounded-lg border border-gray-200 bg-white p-6">
            <h2 class="mb-4 text-lg font-semibold">Document Details</h2>
            <dl class="grid grid-cols-2 gap-4">
                <div>
                    <dt class="font-medium text-gray-700">Filename</dt>
                    <dd class="text-gray-900">{{ document.original_filename }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-700">File Size</dt>
                    <dd class="text-gray-900">{{ document.formatted_size }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-700">Uploaded</dt>
                    <dd class="text-gray-900">{{ document.created_at }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-700">Status</dt>
                    <dd class="text-gray-900">{{ document.state }}</dd>
                </div>
            </dl>
        </div>
    </div>
</template>
```

---

## Component Details

### ProgressTracker.vue

#### Props
```typescript
interface Props {
    documentUuid: string;  // Document UUID for API calls
}
```

#### Data Structure
The component fetches and displays:
```json
{
    "status": "processing|completed|failed|pending",
    "percentage_complete": 45,
    "stage_count": 4,
    "completed_stages": 2,
    "current_stage": "OCR Processor",
    "updated_at": "2025-12-01T10:30:00Z"
}
```

#### Display Features
- **Progress Bar**: Visual bar showing percentage complete
- **Stage Info**: Shows "2 / 4 stages completed"
- **Current Stage**: Displays which processor is currently running
- **Status Badge**: Color-coded status indicator (green/red/blue/gray)
- **Error Display**: Shows error messages if API fails
- **Auto-stop**: Stops polling when completed or failed

#### CSS Classes Used
- Tailwind CSS (v4) for styling
- Responsive grid layout
- Color-coded status indicators

---

### ProcessingMetrics.vue

#### Props
```typescript
interface Props {
    documentId: string;  // Document ID for API calls
}
```

#### Data Structure
The component fetches and displays:
```json
[
    {
        "processor_id": "ocr-001",
        "processor": {
            "name": "OCR Processor",
            "category": "ocr"
        },
        "duration_ms": 2500,
        "status": "completed",
        "completed_at": "2025-12-01T10:30:00Z"
    },
    // ... more processors
]
```

#### Display Features
- **Summary Stats**:
  - Total Duration: Sum of all processor times
  - Completed: Count of completed processors
  - Average Time: Average execution time

- **Metrics Table**:
  - Processor name with category
  - Execution status (badge)
  - Duration in ms or seconds
  - Hover effects on rows

#### CSS Classes Used
- Tailwind CSS (v4) for styling
- Grid layout for summary
- Table for detailed metrics
- Color-coded status badges

---

## API Integration

### Progress Endpoint
```
GET /api/documents/{uuid}/progress

Response:
{
    "status": "processing",
    "percentage_complete": 50,
    "stage_count": 4,
    "completed_stages": 2,
    "current_stage": "Text Extraction",
    "updated_at": "2025-12-01T10:30:00Z"
}
```

### Metrics Endpoint
```
GET /api/documents/{uuid}/metrics

Response:
[
    {
        "processor_id": "processor-id",
        "processor": {
            "name": "Processor Name",
            "category": "category"
        },
        "duration_ms": 1500,
        "status": "completed",
        "completed_at": "2025-12-01T10:30:00Z"
    }
]
```

---

## Styling Customization

### Tailwind Classes Used

#### Colors
- `bg-blue-500`: Active progress bar
- `bg-gray-100`: Background
- `text-gray-900`: Primary text
- `text-gray-600`: Secondary text
- `border-gray-200`: Borders

#### Layout
- `space-y-3`: Vertical spacing
- `grid grid-cols-2`: Two-column layout
- `gap-4`: Gap between grid items
- `rounded-lg`: Rounded corners
- `p-4`: Padding

#### Typography
- `font-semibold`: Bold headers
- `text-sm`: Small text
- `text-lg`: Large text

---

## Status Indicators

### Progress Status Colors
| Status | Color | Indicator |
|--------|-------|-----------|
| Pending | Gray | Waiting to process |
| Processing | Blue | Currently running |
| Completed | Green | Successfully finished |
| Failed | Red | Error occurred |

### Processor Status Badges
| Status | Badge Class |
|--------|------------|
| completed | `bg-green-100 text-green-800` |
| running | `bg-blue-100 text-blue-800` |
| failed | `bg-red-100 text-red-800` |
| default | `bg-gray-100 text-gray-800` |

---

## Polling Behavior

### ProgressTracker
- Polls every 2 seconds
- Stops when `status` is "completed" or "failed"
- Cleans up interval on component unmount
- Continues polling on error

### ProcessingMetrics
- Polls every 3 seconds
- Continues polling for full lifecycle
- Handles 404 gracefully (no data yet)
- Cleans up interval on component unmount

---

## Error Handling

### API Errors
Both components display error messages:
```vue
<div class="rounded-md bg-red-50 p-3 text-sm text-red-700">
    {{ error }}
</div>
```

### Network Errors
- Components catch fetch errors
- Display user-friendly error message
- Polling continues despite errors

### Missing Data
- Progress: Returns default "no_job" status
- Metrics: Returns empty array (shown as "No processing metrics available yet")

---

## Loading States

### ProgressTracker
- Initial load shows "Loading progress..."
- Hides after first successful fetch

### ProcessingMetrics
- Initial load shows "Loading metrics..."
- Shows empty state if no metrics available

---

## Example Usage in Page

### Full Document Detail Page
```vue
<template>
    <div class="min-h-screen bg-gray-50 py-8">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-4xl font-bold text-gray-900">
                    {{ document.original_filename }}
                </h1>
                <p class="mt-2 text-lg text-gray-600">
                    Uploaded {{ formatDate(document.created_at) }}
                </p>
            </div>

            <!-- Progress Section (only show if processing) -->
            <div v-if="document.state === 'processing'" class="mb-8">
                <ProgressTracker :document-uuid="document.uuid" />
            </div>

            <!-- Metrics Section (always show) -->
            <div class="mb-8">
                <ProcessingMetrics :document-id="document.id" />
            </div>

            <!-- Content Section -->
            <div class="rounded-lg border border-gray-200 bg-white p-8">
                <h2 class="mb-6 text-2xl font-semibold text-gray-900">
                    Processing Results
                </h2>

                <!-- Show results based on status -->
                <div v-if="document.state === 'completed'" class="space-y-4">
                    <!-- Show processed content -->
                </div>

                <div v-else-if="document.state === 'failed'" class="rounded-md bg-red-50 p-4">
                    <p class="text-red-900">{{ document.error_message }}</p>
                </div>

                <div v-else class="text-center text-gray-600">
                    <p>Processing... Check progress above</p>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import ProgressTracker from '@/components/ProgressTracker.vue';
import ProcessingMetrics from '@/components/ProcessingMetrics.vue';

interface Props {
    document: any;
}

defineProps<Props>();

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};
</script>
```

---

## Best Practices

1. **Conditional Display**
   - Show ProgressTracker only during "processing" state
   - Always show ProcessingMetrics (empty state handled)

2. **Responsive Design**
   - Components use responsive Tailwind classes
   - Grid layouts adapt to screen size
   - Works on mobile and desktop

3. **Performance**
   - Polling stops automatically when complete
   - Intervals cleaned up on unmount
   - Minimal re-renders with Vue reactivity

4. **Accessibility**
   - Semantic HTML structure
   - Proper color contrast ratios
   - Clear status indicators

5. **User Experience**
   - Real-time feedback during processing
   - Clear error messages
   - Progress visibility

---

## Troubleshooting

### Components Not Showing
- Check if document UUID and ID are correct
- Verify API endpoints are accessible
- Check browser console for errors

### Polling Not Stopping
- Ensure component unmounts properly
- Check if status updates are working
- Verify API response format

### Styling Issues
- Ensure Tailwind CSS is properly loaded
- Check for CSS conflicts
- Verify color classes match theme

### API Errors
- Check authentication/authorization
- Verify document exists in database
- Check API route registration

---

## Summary

The Progress Tracker and Processing Metrics components provide real-time visibility into document processing operations. Integrate them into your document detail page to:

- ✅ Show real-time progress updates
- ✅ Display processor execution metrics
- ✅ Provide users with clear status feedback
- ✅ Enable better monitoring and debugging
