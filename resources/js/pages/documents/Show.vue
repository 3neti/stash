<script setup lang="ts">
import ProcessingStatusBadge from '@/components/ProcessingStatusBadge.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Document } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { Calendar, FileText, FolderOpen } from 'lucide-vue-next';

interface Props {
    document: Document;
}

const props = defineProps<Props>();

const formatFileSize = (bytes: number): string => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
};

const formatDate = (date: string): string => {
    return new Date(date).toLocaleString();
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Documents', href: '/documents' },
    { title: props.document.original_filename, href: `/documents/${props.document.uuid}` },
];
</script>

<template>
    <Head :title="document.original_filename" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <div class="flex items-start justify-between">
                <div>
                    <div class="flex items-center gap-3">
                        <FileText class="h-6 w-6 text-muted-foreground" />
                        <h1 class="text-2xl font-bold">{{ document.original_filename }}</h1>
                    </div>
                    <div class="flex items-center gap-4 mt-2 text-sm text-muted-foreground">
                        <span>{{ document.mime_type }}</span>
                        <span>{{ formatFileSize(document.size_bytes) }}</span>
                    </div>
                </div>
                <ProcessingStatusBadge :status="document.status" />
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Document Information</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div>
                            <p class="text-sm font-medium">UUID</p>
                            <p class="text-sm text-muted-foreground font-mono">{{ document.uuid }}</p>
                        </div>
                        <div v-if="document.campaign">
                            <p class="text-sm font-medium">Campaign</p>
                            <Link
                                :href="`/campaigns/${document.campaign.id}`"
                                class="flex items-center gap-2 text-sm text-primary hover:underline"
                            >
                                <FolderOpen class="h-4 w-4" />
                                {{ document.campaign.name }}
                            </Link>
                        </div>
                        <div>
                            <p class="text-sm font-medium">Created</p>
                            <p class="text-sm text-muted-foreground">
                                <Calendar class="inline h-3 w-3 mr-1" />
                                {{ formatDate(document.created_at) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-sm font-medium">Updated</p>
                            <p class="text-sm text-muted-foreground">
                                <Calendar class="inline h-3 w-3 mr-1" />
                                {{ formatDate(document.updated_at) }}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <Card v-if="document.document_job">
                    <CardHeader>
                        <CardTitle>Processing Job</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div>
                            <p class="text-sm font-medium">Status</p>
                            <ProcessingStatusBadge :status="document.document_job.status" />
                        </div>
                        <div>
                            <p class="text-sm font-medium">Attempts</p>
                            <p class="text-sm text-muted-foreground">
                                {{ document.document_job.attempts }}
                            </p>
                        </div>
                        <div v-if="document.document_job.started_at">
                            <p class="text-sm font-medium">Started At</p>
                            <p class="text-sm text-muted-foreground">
                                {{ formatDate(document.document_job.started_at) }}
                            </p>
                        </div>
                        <div v-if="document.document_job.completed_at">
                            <p class="text-sm font-medium">Completed At</p>
                            <p class="text-sm text-muted-foreground">
                                {{ formatDate(document.document_job.completed_at) }}
                            </p>
                        </div>
                        <div v-if="document.document_job.failed_at">
                            <p class="text-sm font-medium">Failed At</p>
                            <p class="text-sm text-muted-foreground">
                                {{ formatDate(document.document_job.failed_at) }}
                            </p>
                        </div>
                        <div v-if="document.document_job.error_message">
                            <p class="text-sm font-medium">Error Message</p>
                            <p class="text-sm text-destructive">
                                {{ document.document_job.error_message }}
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Card v-if="document.metadata">
                <CardHeader>
                    <CardTitle>Metadata</CardTitle>
                </CardHeader>
                <CardContent>
                    <pre class="text-sm bg-muted p-4 rounded-lg overflow-auto">{{
                        JSON.stringify(document.metadata, null, 2)
                    }}</pre>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
