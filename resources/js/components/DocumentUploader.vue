<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useForm } from '@inertiajs/vue3';
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

const form = useForm({
    documents: [] as File[],
});

const canAddMore = computed(() => selectedFiles.value.length < props.maxFiles);

const handleFileSelect = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const files = Array.from(target.files || []);
    
    const remaining = props.maxFiles - selectedFiles.value.length;
    const filesToAdd = files.slice(0, remaining);
    
    selectedFiles.value.push(...filesToAdd);
};

const removeFile = (index: number) => {
    selectedFiles.value.splice(index, 1);
};

const uploadDocuments = () => {
    form.documents = selectedFiles.value;
    
    form.post(`/api/campaigns/${props.campaignId}/documents`, {
        onSuccess: () => {
            selectedFiles.value = [];
            if (fileInput.value) {
                fileInput.value.value = '';
            }
            emit('uploaded');
        },
    });
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
    <Card>
        <CardHeader>
            <CardTitle>Upload Documents</CardTitle>
            <CardDescription>
                Upload up to {{ maxFiles }} documents at once
            </CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
            <div
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
                    type="file"
                    multiple
                    class="hidden"
                    :disabled="!canAddMore"
                    @change="handleFileSelect"
                />
            </div>

            <div v-if="selectedFiles.length > 0" class="space-y-2">
                <div
                    v-for="(file, index) in selectedFiles"
                    :key="index"
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

            <div v-if="form.errors.documents" class="text-sm text-destructive">
                {{ form.errors.documents }}
            </div>

            <Button
                :disabled="selectedFiles.length === 0 || form.processing"
                @click="uploadDocuments"
                class="w-full"
            >
                {{ form.processing ? 'Uploading...' : 'Upload Documents' }}
            </Button>
        </CardContent>
    </Card>
</template>
