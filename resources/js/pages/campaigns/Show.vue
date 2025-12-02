<script setup lang="ts">
import DocumentUploader from '@/components/DocumentUploader.vue';
import PipelineVisualizer from '@/components/PipelineVisualizer.vue';
import ProcessingStatusBadge from '@/components/ProcessingStatusBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Campaign, Document } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Edit, FileText, Trash2 } from 'lucide-vue-next';

interface Props {
    campaign: Campaign & {
        documents?: Document[];
    };
}

const props = defineProps<Props>();

const deleteCampaign = () => {
    if (confirm('Are you sure you want to delete this campaign?')) {
        router.delete(`/campaigns/${props.campaign.id}`);
    }
};

const handleUploaded = () => {
    router.reload();
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Campaigns', href: '/campaigns' },
    { title: props.campaign.name, href: `/campaigns/${props.campaign.id}` },
];
</script>

<template>
    <Head :title="campaign.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <div class="flex items-start justify-between">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl font-bold">{{ campaign.name }}</h1>
                        <Badge :variant="campaign.state === 'active' ? 'default' : 'secondary'">
                            {{ campaign.state }}
                        </Badge>
                    </div>
                    <p v-if="campaign.description" class="text-muted-foreground mt-1">
                        {{ campaign.description }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <Button as-child variant="outline" size="sm">
                        <Link :href="`/campaigns/${campaign.id}/edit`">
                            <Edit class="mr-2 h-4 w-4" />
                            Edit
                        </Link>
                    </Button>
                    <Button variant="destructive" size="sm" @click="deleteCampaign">
                        <Trash2 class="mr-2 h-4 w-4" />
                        Delete
                    </Button>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <PipelineVisualizer :pipeline-config="campaign.pipeline_config" />
                <DocumentUploader :campaign-id="campaign.id" @uploaded="handleUploaded" />
            </div>

            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <CardTitle>Documents</CardTitle>
                        <span class="text-sm text-muted-foreground">
                            {{ campaign.documents?.length || 0 }} total
                        </span>
                    </div>
                </CardHeader>
                <CardContent>
                    <div v-if="campaign.documents && campaign.documents.length > 0" class="space-y-2">
                        <Link
                            v-for="document in campaign.documents"
                            :key="document.id"
                            :href="`/documents/${document.uuid}`"
                            class="flex items-center justify-between rounded-lg border p-4 transition-colors hover:bg-accent"
                        >
                            <div class="flex items-center gap-3">
                                <FileText class="h-5 w-5 text-muted-foreground" />
                                <div>
                                    <p class="font-medium">{{ document.original_filename }}</p>
                                    <p class="text-sm text-muted-foreground">
                                        {{ document.mime_type }}
                                    </p>
                                </div>
                            </div>
                            <ProcessingStatusBadge :status="document.status" />
                        </Link>
                    </div>
                    <div v-else class="text-center py-8">
                        <p class="text-muted-foreground">No documents yet</p>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
