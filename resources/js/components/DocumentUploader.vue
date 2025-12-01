<script setup lang="ts">
import axios from 'axios';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Upload, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Props {
    campaignId: string;
    maxFiles?: number;
}

const props = withDefaults(defineProps<Props>(), {
    maxFiles: 10,
});

const emit = defineEmits<{
    uploaded: [];
}>();

const fileInput = ref<HTMLInputElement | null>(null);
const selectedFiles = ref<File[]>([]);
const uploading = ref(false);
const error = ref<string | null>(null);

const canAddMore = computed(() => selectedFiles.value.length < props.maxFiles);

const handleFileSelect = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const files = Array.from(target.files || []);

    const remaining = props.maxFiles - selectedFiles.value.length;
    const filesToAdd = files.slice(0, remaining);

    selectedFiles.value.push(...filesToAdd);
    error.value = null;
};

const removeFile = (index: number) => {
    selectedFiles.value.splice(index, 1);
};

const uploadDocuments = async () => {
    if (selectedFiles.value.length === 0 || uploading.value) return;

    uploading.value = true;
    error.value = null;

    try {
        const formData = new FormData();
        selectedFiles.value.forEach((file) => {
            // Backend accepts both `documents[]` and `files[]`; prefer `documents[]`
            formData.append('documents[]', file);
        });

        await axios.post(
            `/api/campaigns/${props.campaignId}/documents`,
            formData,
            {
                headers: {
                    'Content-Type': 'multipart/form-data',
                },
            }
        );

        selectedFiles.value = [];
        if (fileInput.value) {
            fileInput.value.value = '';
        }
        emit('uploaded');
    } catch (err: any) {
        error.value = err?.response?.data?.message || 'Upload failed. Please try again.';
        console.error('Upload error:', err);
    } finally {
        uploading.value = false;
    }
};

const formatFileSize = (bytes: number): string => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
};
</script>

<template>
    <Card data-testid="document-uploader">
        <CardHeader>
            <CardTitle>Upload Documents</CardTitle>
            <CardDescription>
                Upload up to {{ maxFiles }} documents at once
            </CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
            <div
                data-testid="file-dropzone"
                class="flex items-center justify-center rounded-lg border-2 border-dashed p-8"
                :class="canAddMore ? 'cursor-pointer hover:border-primary' : 'opacity-50'"
                @click="canAddMore && fileInput?.click()"
            >
                <div class="text-center">
                    <Upload class="mx-auto h-12 w-12 text-muted-foreground" />
                    <p class="mt-2 text-sm font-medium">
                        {{ canAddMore ? 'Click to select files' : 'Maximum files reached' }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ selectedFiles.length }} / {{ maxFiles }} files selected
                    </p>
                </div>
                <input
                    ref="fileInput"
                    data-testid="file-input"
                    type="file"
                    multiple
                    class="hidden"
                    :disabled="!canAddMore"
                    @change="handleFileSelect"
                />
            </div>

            <div v-if="selectedFiles.length > 0" data-testid="files-list" class="space-y-2">
                <div
                    v-for="(file, index) in selectedFiles"
                    :key="index"
                    data-testid="file-item"
                    class="flex items-center justify-between rounded-lg border p-3"
                >
                    <div class="flex-1">
                        <p class="text-sm font-medium">{{ file.name }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{ formatFileSize(file.size) }}
                        </p>
                    </div>
                    <Button
                        variant="ghost"
                        size="sm"
                        @click="removeFile(index)"
                    >
                        <X class="h-4 w-4" />
                    </Button>
                </div>
            </div>

            <div v-if="error" class="text-sm text-destructive">
                {{ error }}
            </div>

            <Button
                data-testid="upload-button"
                :disabled="selectedFiles.length === 0 || uploading"
                @click="uploadDocuments"
                class="w-full"
            >
                {{ uploading ? 'Uploading...' : 'Upload Documents' }}
            </Button>
        </CardContent>
    </Card>
</template>
