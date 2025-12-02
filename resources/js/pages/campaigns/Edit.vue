<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Campaign } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';

interface Props {
    campaign: Campaign;
}

const props = defineProps<Props>();

const campaignTypes = ['template', 'custom', 'meta'];

const form = useForm({
    name: props.campaign.name,
    description: props.campaign.description || '',
    type: props.campaign.type,
    state: props.campaign.state,
});

const submit = () => {
    form.put(`/campaigns/${props.campaign.id}`);
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Campaigns', href: '/campaigns' },
    { title: props.campaign.name, href: `/campaigns/${props.campaign.id}` },
    { title: 'Edit', href: `/campaigns/${props.campaign.id}/edit` },
];
</script>

<template>
    <Head :title="`Edit ${campaign.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <h1 class="text-2xl font-bold">Edit Campaign</h1>

            <Card class="max-w-2xl">
                <CardHeader>
                    <CardTitle>Campaign Details</CardTitle>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submit" class="space-y-4">
                        <div class="space-y-2">
                            <Label for="name">Name</Label>
                            <Input
                                id="name"
                                v-model="form.name"
                                placeholder="Enter campaign name"
                                required
                            />
                            <p v-if="form.errors.name" class="text-sm text-destructive">
                                {{ form.errors.name }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="description">Description</Label>
                            <textarea
                                id="description"
                                v-model="form.description"
                                placeholder="Enter campaign description"
                                rows="4"
                                class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-base ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                            />
                            <p v-if="form.errors.description" class="text-sm text-destructive">
                                {{ form.errors.description }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="type">Type</Label>
                            <select
                                id="type"
                                v-model="form.type"
                                required
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-base ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                            >
                                <option value="">Select campaign type</option>
                                <option v-for="campaignType in campaignTypes" :key="campaignType" :value="campaignType">
                                    {{ campaignType.charAt(0).toUpperCase() + campaignType.slice(1) }}
                                </option>
                            </select>
                            <p v-if="form.errors.type" class="text-sm text-destructive">
                                {{ form.errors.type }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label>State</Label>
                            <div class="grid grid-cols-2 gap-2">
                                <label class="flex items-center gap-2">
                                    <input
                                        type="radio"
                                        v-model="form.state"
                                        value="draft"
                                        class="h-4 w-4"
                                    />
                                    <span>Draft</span>
                                </label>
                                <label class="flex items-center gap-2">
                                    <input
                                        type="radio"
                                        v-model="form.state"
                                        value="active"
                                        class="h-4 w-4"
                                    />
                                    <span>Active</span>
                                </label>
                                <label class="flex items-center gap-2">
                                    <input
                                        type="radio"
                                        v-model="form.state"
                                        value="paused"
                                        class="h-4 w-4"
                                    />
                                    <span>Paused</span>
                                </label>
                                <label class="flex items-center gap-2">
                                    <input
                                        type="radio"
                                        v-model="form.state"
                                        value="archived"
                                        class="h-4 w-4"
                                    />
                                    <span>Archived</span>
                                </label>
                            </div>
                            <p v-if="form.errors.state" class="text-sm text-destructive">
                                {{ form.errors.state }}
                            </p>
                        </div>

                        <div class="flex gap-2">
                            <Button type="submit" :disabled="form.processing">
                                {{ form.processing ? 'Saving...' : 'Save Changes' }}
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                @click="$inertia.visit(`/campaigns/${campaign.id}`)"
                            >
                                Cancel
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
