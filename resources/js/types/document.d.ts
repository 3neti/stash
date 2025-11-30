import type { Campaign } from './campaign';

export interface Document {
    id: string;
    uuid: string;
    campaign_id: string;
    original_filename: string;
    mime_type: string;
    size_bytes: number;
    status: 'pending' | 'processing' | 'completed' | 'failed';
    metadata: Record<string, unknown> | null;
    campaign?: Campaign;
    document_job?: DocumentJob;
    created_at: string;
    updated_at: string;
}

export interface DocumentJob {
    id: string;
    document_id: string;
    status: 'pending' | 'processing' | 'completed' | 'failed';
    started_at: string | null;
    completed_at: string | null;
    failed_at: string | null;
    error_message: string | null;
    attempts: number;
    created_at: string;
    updated_at: string;
}

export interface DocumentFilters {
    status?: string;
    search?: string;
}
