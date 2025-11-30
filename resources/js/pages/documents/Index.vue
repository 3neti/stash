<script setup lang="ts">
import ProcessingStatusBadge from '@/components/ProcessingStatusBadge.vue';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Document, DocumentFilters } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { FileText } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface Props {
    documents: {
        data: Document[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: DocumentFilters;
}

const props = defineProps<Props>();

const search = ref(props.filters.search || '');

watch(search, (value) => {
    router.get(
        '/documents',
        { search: value },
        { preserveState: true, replace: true }
    );
});

const formatFileSize = (bytes: number): string => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Documents', href: '/documents' },
];
</script>

<template>
    <Head title="Documents" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">Documents</h1>
            </div>

            <div class="flex items-center gap-4">
                <Input
                    v-model="search"
                    data-testid="search-documents-input"
                    placeholder="Search documents..."
                    class="max-w-sm"
                />
            </div>

            <div data-testid="documents-list" class="space-y-2">
                <Link
                    v-for="document in documents.data"
                    :key="document.id"
                    data-testid="document-row"
                    :href="`/documents/${document.uuid}`"
                    class="flex items-center justify-between rounded-lg border p-4 transition-colors hover:bg-accent"
                >
                    <div class="flex items-center gap-3 flex-1">
                        <FileText class="h-5 w-5 text-muted-foreground" />
                        <div class="flex-1">
                            <p data-testid="document-name" class="font-medium">{{ document.original_filename }}</p>
                            <div class="flex items-center gap-4 text-sm text-muted-foreground">
                                <span>{{ document.mime_type }}</span>
                                <span>{{ formatFileSize(document.size_bytes) }}</span>
                                <span v-if="document.campaign">{{ document.campaign.name }}</span>
                            </div>
                        </div>
                    </div>
                    <ProcessingStatusBadge :status="document.status" />
                </Link>
            </div>

            <div v-if="documents.data.length === 0" data-testid="empty-state" class="text-center py-12">
                <p class="text-muted-foreground">No documents found</p>
            </div>
        </div>
    </AppLayout>
</template>
