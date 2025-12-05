<script setup lang="ts">
interface Props {
  verified: boolean
  error?: string
  document?: {
    uuid: string
    filename: string
    original_filename: string
    mime_type: string
    size_kb: number
  }
  signature?: {
    transaction_id: string
    signed_at: string
    qr_watermarked: boolean
    verification_url: string
    processor: string
    status: string
  }
  signed_document?: {
    filename: string
    size_kb: number
    download_url: string
  }
  signature_mark_url?: string
  kyc?: {
    transaction_id: string
    status: string
    completed_at: string
  }
}

const props = defineProps<Props>()

const formatDate = (dateStr: string) => {
  return new Date(dateStr).toLocaleString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-gray-50 px-4 py-12 sm:px-6 lg:px-8">
    <div class="w-full max-w-3xl space-y-8">
      <!-- Header -->
      <div class="text-center">
        <div v-if="verified" class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-green-100">
          <svg class="h-12 w-12 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
          </svg>
        </div>
        <div v-else class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-red-100">
          <svg class="h-12 w-12 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
        </div>
        
        <h1 class="mt-6 text-3xl font-bold tracking-tight text-gray-900">
          {{ verified ? 'Document Verified' : 'Verification Failed' }}
        </h1>
        
        <p v-if="error" class="mt-2 text-sm text-red-600">
          {{ error }}
        </p>
        <p v-else-if="verified" class="mt-2 text-sm text-gray-600">
          This document has been digitally signed and its authenticity has been verified.
        </p>
      </div>

      <!-- Verification Details -->
      <div v-if="verified && document" class="space-y-6">
        <!-- Document Information -->
        <div class="rounded-lg bg-white p-6 shadow">
          <h2 class="mb-4 text-lg font-medium text-gray-900">Document Information</h2>
          <dl class="grid grid-cols-1 gap-x-4 gap-y-4 sm:grid-cols-2">
            <div>
              <dt class="text-sm font-medium text-gray-500">Original Filename</dt>
              <dd class="mt-1 text-sm text-gray-900">{{ document.original_filename }}</dd>
            </div>
            <div>
              <dt class="text-sm font-medium text-gray-500">Document ID</dt>
              <dd class="mt-1 text-sm font-mono text-gray-900">{{ document.uuid }}</dd>
            </div>
            <div>
              <dt class="text-sm font-medium text-gray-500">File Type</dt>
              <dd class="mt-1 text-sm text-gray-900">{{ document.mime_type }}</dd>
            </div>
            <div>
              <dt class="text-sm font-medium text-gray-500">File Size</dt>
              <dd class="mt-1 text-sm text-gray-900">{{ document.size_kb }} KB</dd>
            </div>
          </dl>
        </div>

        <!-- Signature Information -->
        <div v-if="signature" class="rounded-lg bg-white p-6 shadow">
          <div class="mb-4 flex items-center justify-between">
            <h2 class="text-lg font-medium text-gray-900">Digital Signature</h2>
            <span class="inline-flex items-center rounded-full bg-green-100 px-3 py-1 text-sm font-medium text-green-800">
              ✓ {{ signature.status }}
            </span>
          </div>
          
          <dl class="grid grid-cols-1 gap-x-4 gap-y-4 sm:grid-cols-2">
            <div>
              <dt class="text-sm font-medium text-gray-500">Transaction ID</dt>
              <dd class="mt-1 text-sm font-mono text-gray-900">{{ signature.transaction_id }}</dd>
            </div>
            <div>
              <dt class="text-sm font-medium text-gray-500">Signed At</dt>
              <dd class="mt-1 text-sm text-gray-900">{{ formatDate(signature.signed_at) }}</dd>
            </div>
            <div>
              <dt class="text-sm font-medium text-gray-500">Processor</dt>
              <dd class="mt-1 text-sm text-gray-900">{{ signature.processor }}</dd>
            </div>
            <div v-if="signature.qr_watermarked">
              <dt class="text-sm font-medium text-gray-500">QR Watermark</dt>
              <dd class="mt-1">
                <span class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800">
                  Embedded
                </span>
              </dd>
            </div>
          </dl>

          <!-- Signature Mark Preview -->
          <div v-if="signature_mark_url" class="mt-6">
            <h3 class="text-sm font-medium text-gray-700 mb-3">Digital Signature Mark</h3>
            <div class="max-w-md">
              <img 
                :src="signature_mark_url" 
                alt="Signature Mark"
                class="h-auto w-full rounded-lg border border-gray-200 object-cover"
              />
            </div>
          </div>
        </div>

        <!-- Signed Document Download -->
        <div v-if="signed_document" class="rounded-lg bg-white p-6 shadow">
          <h2 class="mb-4 text-lg font-medium text-gray-900">Signed Document</h2>
          
          <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 p-4">
            <div class="flex items-center space-x-3">
              <svg class="h-10 w-10 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd" />
              </svg>
              <div>
                <p class="text-sm font-medium text-gray-900">{{ signed_document.filename }}</p>
                <p class="text-xs text-gray-500">{{ signed_document.size_kb }} KB</p>
              </div>
            </div>
            
            <a 
              :href="signed_document.download_url"
              class="inline-flex items-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
              download
            >
              <svg class="-ml-0.5 mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
              </svg>
              Download
            </a>
          </div>
        </div>

        <!-- Security Notice -->
        <div class="rounded-lg bg-blue-50 p-6">
          <div class="flex">
            <div class="flex-shrink-0">
              <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
              </svg>
            </div>
            <div class="ml-3 flex-1">
              <h3 class="text-sm font-medium text-blue-800">About This Verification</h3>
              <div class="mt-2 text-sm text-blue-700">
                <p>
                  This verification page confirms that the document was digitally signed using our Electronic Signature Framework with KYC verification. The signature includes a QR watermark that can be scanned to verify authenticity at any time.
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Return Link -->
      <div class="text-center">
        <a 
          href="/" 
          class="inline-flex items-center text-sm font-medium text-blue-600 hover:text-blue-500"
        >
          <svg class="mr-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
          </svg>
          Return to Home
        </a>
      </div>
    </div>
  </div>
</template>
