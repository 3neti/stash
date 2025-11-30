# Component Integration for Browser Tests

This guide explains how to add `data-testid` attributes to Vue components to support stable browser testing.

## Why data-testid?

Relying on CSS classes or element positions for selectors is fragile:
- Design changes break tests
- Selectors become coupled to styling
- Hard to understand test intent

Using `data-testid` attributes:
- Decouples tests from CSS/styling
- Clarifies test intent
- Survives design refactors
- Easy to find in code

## Convention

Use kebab-case for `data-testid` values:

```vue
<!-- Good -->
<button data-testid="create-campaign">Create</button>
<input data-testid="campaign-name" v-model="form.name" />
<div data-testid="loading-spinner">...</div>

<!-- Bad -->
<button data-testid="createCampaign">Create</button>
<button data-testid="btn">Create</button>
```

## Common Components

### Buttons and Links

```vue
<!-- Buttons -->
<button data-testid="create-button">Create</button>
<button data-testid="save-button">Save</button>
<button data-testid="delete-button">Delete</button>
<button data-testid="cancel-button">Cancel</button>
<button data-testid="submit-button">Submit</button>

<!-- Links -->
<Link data-testid="back-link" :href="route('campaigns.index')">Back</Link>
<a data-testid="edit-link" :href="route('campaigns.edit', campaign.id)">Edit</a>

<!-- Confirm Buttons -->
<button data-testid="confirm-delete">Yes, Delete</button>
<button data-testid="cancel-delete">Cancel</button>
```

### Forms and Inputs

```vue
<!-- Text inputs -->
<input data-testid="campaign-name" v-model="form.name" />
<input data-testid="campaign-description" v-model="form.description" />

<!-- Dropdowns -->
<select data-testid="campaign-status">
  <option>Select Status</option>
</select>

<!-- Checkboxes -->
<input type="checkbox" data-testid="confirm-delete-checkbox" />

<!-- Textareas -->
<textarea data-testid="campaign-description"></textarea>

<!-- Form Elements -->
<form data-testid="campaign-form">
  <!-- form content -->
</form>
```

### Navigation and Layout

```vue
<!-- Sidebar/Navigation -->
<nav data-testid="main-navigation">
  <a data-testid="nav-dashboard">Dashboard</a>
  <a data-testid="nav-campaigns">Campaigns</a>
  <a data-testid="nav-documents">Documents</a>
</nav>

<!-- User Menu -->
<div data-testid="user-menu">
  <button data-testid="logout-button">Logout</button>
</div>

<!-- Breadcrumbs -->
<nav data-testid="breadcrumbs">
  <a data-testid="breadcrumb-dashboard">Dashboard</a>
  <span data-testid="breadcrumb-current">Campaigns</span>
</nav>
```

### Content Display

```vue
<!-- Cards -->
<div data-testid="campaign-card">
  <h3 data-testid="campaign-title">{{ campaign.name }}</h3>
  <p data-testid="campaign-description">{{ campaign.description }}</p>
  <span data-testid="campaign-status">{{ campaign.status }}</span>
</div>

<!-- Lists -->
<ul data-testid="campaigns-list">
  <li data-testid="campaign-item" v-for="campaign in campaigns">
    {{ campaign.name }}
  </li>
</ul>

<!-- Tables -->
<table data-testid="documents-table">
  <tbody>
    <tr data-testid="document-row" v-for="doc in documents">
      <td data-testid="document-name">{{ doc.name }}</td>
      <td data-testid="document-status">{{ doc.status }}</td>
    </tr>
  </tbody>
</table>

<!-- Status Indicators -->
<span data-testid="processing-status" :class="statusClass">
  {{ status }}
</span>
```

### Modals and Dialogs

```vue
<!-- Modal -->
<div data-testid="delete-modal">
  <h2 data-testid="modal-title">Confirm Delete</h2>
  <p data-testid="modal-message">Are you sure?</p>
  <button data-testid="confirm-delete">Delete</button>
  <button data-testid="modal-cancel">Cancel</button>
</div>

<!-- Alert/Notification -->
<div data-testid="success-alert">
  Campaign created successfully!
</div>
```

### Loading and Empty States

```vue
<!-- Loading -->
<div data-testid="loading-spinner">
  Loading...
</div>

<!-- Empty State -->
<div data-testid="empty-state">
  <p data-testid="empty-message">No campaigns found.</p>
  <a data-testid="create-first-button">Create your first campaign</a>
</div>
```

## File-Specific Examples

### Dashboard.vue

```vue
<script setup lang="ts">
import StatsCard from '@/components/StatsCard.vue';

interface Props {
    stats: DashboardStats;
}

defineProps<Props>();
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <!-- Stats Grid -->
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <StatsCard
                    data-testid="total-campaigns-stat"
                    title="Total Campaigns"
                    :value="stats.campaigns.total"
                    :icon="FolderOpen"
                />
                <StatsCard
                    data-testid="total-documents-stat"
                    title="Total Documents"
                    :value="stats.documents.total"
                    :icon="FileText"
                />
                <StatsCard
                    data-testid="processing-documents-stat"
                    title="Processing"
                    :value="stats.documents.processing"
                    :icon="Clock"
                />
                <StatsCard
                    data-testid="completed-documents-stat"
                    title="Completed"
                    :value="stats.documents.completed"
                    :icon="CheckCircle"
                />
            </div>

            <!-- Quick Actions -->
            <div class="grid gap-4 md:grid-cols-2">
                <div data-testid="quick-actions">
                    <a
                        data-testid="create-campaign-button"
                        :href="route('campaigns.create')"
                    >
                        Create Campaign
                    </a>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
```

### CampaignCard.vue

```vue
<script setup lang="ts">
import { Campaign } from '@/types/campaign';

interface Props {
    campaign: Campaign;
}

defineProps<Props>();
</script>

<template>
    <Card data-testid="campaign-card">
        <CardHeader>
            <CardTitle data-testid="campaign-name">
                {{ campaign.name }}
            </CardTitle>
            <CardDescription data-testid="campaign-description">
                {{ campaign.description }}
            </CardDescription>
        </CardHeader>
        <CardContent>
            <Badge data-testid="campaign-status" :variant="statusVariant">
                {{ campaign.status }}
            </Badge>

            <div class="mt-2 flex gap-2">
                <Link
                    data-testid="view-campaign-link"
                    :href="route('campaigns.show', campaign.id)"
                >
                    View
                </Link>
                <Link
                    data-testid="edit-campaign-link"
                    :href="route('campaigns.edit', campaign.id)"
                >
                    Edit
                </Link>
                <button
                    data-testid="delete-campaign-button"
                    @click="deleteCampaign"
                >
                    Delete
                </button>
            </div>
        </CardContent>
    </Card>
</template>
```

### ProcessingStatusBadge.vue

```vue
<script setup lang="ts">
import { Badge } from '@/components/ui/badge';

interface Props {
    status: string;
}

defineProps<Props>();
</script>

<template>
    <Badge data-testid="processing-status" :variant="variant">
        {{ label }}
    </Badge>
</template>
```

### DocumentUploader.vue

```vue
<script setup lang="ts">
interface Props {
    campaignId: string;
}

defineProps<Props>();
</script>

<template>
    <div data-testid="document-uploader">
        <div data-testid="file-dropzone">
            <input
                data-testid="file-input"
                type="file"
                multiple
                @change="handleUpload"
            />
        </div>
        <div data-testid="upload-progress" v-if="uploading">
            Uploading {{ uploadProgress }}%
        </div>
        <div data-testid="upload-errors" v-if="errors.length">
            <p v-for="error in errors" :key="error" data-testid="error-message">
                {{ error }}
            </p>
        </div>
    </div>
</template>
```

## Testing These Components

Once you've added `data-testid` attributes:

```php
// tests/Browser/Campaigns/CampaignTest.php

test('user can view campaign card', function () {
    $campaign = Campaign::factory()->create([
        'name' => 'My Campaign',
    ]);

    loginTestUser()
        ->visit('/campaigns')
        ->assertVisible('[data-testid="campaign-card"]')
        ->assertSee('My Campaign')
        ->assertVisible('[data-testid="view-campaign-link"]');
});

test('user can interact with status badge', function () {
    $document = Document::factory()->create([
        'status' => 'processing',
    ]);

    loginTestUser()
        ->visit('/documents/'.$document->uuid)
        ->assertSee('processing')
        ->assertVisible('[data-testid="processing-status"]');
});
```

## Best Practices

### 1. Be Specific, Not Fragile
```vue
<!-- Good: Specific, descriptive -->
<button data-testid="save-campaign-button">Save</button>
<button data-testid="delete-campaign-button">Delete</button>

<!-- Bad: Generic, ambiguous -->
<button data-testid="button">Save</button>
<button data-testid="action">Delete</button>
```

### 2. Mirror Test Structure
```vue
<!-- If you're testing different states, name accordingly -->
<div data-testid="loading-state" v-if="loading">Loading...</div>
<div data-testid="empty-state" v-else-if="!items.length">No items</div>
<div data-testid="items-list" v-else>...</div>
```

### 3. Use for Complex Components
```vue
<!-- Complex component that needs deep interaction -->
<form data-testid="campaign-form">
    <input data-testid="campaign-name-input" />
    <select data-testid="campaign-status-select" />
    <button data-testid="submit-campaign-form">Save</button>
</form>
```

### 4. Avoid Redundancy
```vue
<!-- Don't add data-testid to everything -->

<!-- Good: Test container and key elements -->
<div data-testid="campaign-card">
    <h3>{{ campaign.name }}</h3>
    <p data-testid="campaign-status">{{ campaign.status }}</p>
    <button data-testid="edit-button">Edit</button>
</div>

<!-- Bad: Over-testing -->
<div data-testid="campaign-card">
    <h3 data-testid="campaign-name">{{ campaign.name }}</h3>
    <p data-testid="campaign-description">{{ campaign.description }}</p>
    <p data-testid="campaign-status">{{ campaign.status }}</p>
    <button data-testid="edit-button">Edit</button>
    <button data-testid="delete-button">Delete</button>
</div>
```

## Next Steps

1. Add `data-testid` attributes to all Vue components
2. Update browser tests to use these attributes
3. Run browser tests: `php artisan pest --testsuite=Browser`
4. Verify all tests pass
5. Add new browser tests for new features
