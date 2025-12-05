<script setup lang="ts">
interface SignedDocument {
  filename: string
  size_kb: number
  mime_type: string
  download_url: string
  qr_watermarked: boolean
  signed_at: string
  verification_url?: string
  signature_mark_url?: string
}

interface Props {
  signedDocument: {
    document_job_id: string
    transaction_id: string
    signed_document: SignedDocument
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

const copyToClipboard = (text: string) => {
  navigator.clipboard.writeText(text)
}
</script>

<template>
  <div class="rounded-lg bg-white p-6 shadow">
    <div class="mb-4 flex items-center justify-between">
      <h3 class="text-lg font-medium text-gray-900">✅ Document Digitally Signed</h3>
      <span v-if="signedDocument.signed_document.qr_watermarked" class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">
        QR Watermarked
      </span>
    </div>

    <p class="mb-6 text-sm text-gray-600">
      Your document has been successfully signed with electronic verification.
    </p>

    <!-- Document Card -->
    <div class="mb-4 rounded-lg border border-gray-200 bg-gray-50 p-4">
      <div class="flex items-start justify-between">
        <div class="flex items-start space-x-3">
          <!-- PDF Icon -->
          <div class="flex-shrink-0">
            <svg class="h-10 w-10 text-red-600" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd" />
            </svg>
          </div>
          
          <!-- Document Info -->
          <div>
            <p class="text-sm font-medium text-gray-900">{{ signedDocument.signed_document.filename }}</p>
            <p class="mt-1 text-xs text-gray-500">{{ signedDocument.signed_document.size_kb }} KB • {{ signedDocument.signed_document.mime_type }}</p>
          </div>
        </div>

        <!-- Download Button -->
        <a 
          :href="signedDocument.signed_document.download_url"
          class="inline-flex items-center rounded-md border border-transparent bg-blue-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
          download
        >
          <svg class="-ml-0.5 mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
          </svg>
          Download
        </a>
      </div>
    </div>

    <!-- Signature Details -->
    <dl class="grid grid-cols-1 gap-x-4 gap-y-4 sm:grid-cols-2">
      <div>
        <dt class="text-sm font-medium text-gray-500">Transaction ID</dt>
        <dd class="mt-1 flex items-center text-sm font-mono text-gray-900">
          <span class="truncate">{{ signedDocument.transaction_id }}</span>
          <button 
            @click="copyToClipboard(signedDocument.transaction_id)"
            class="ml-2 text-gray-400 hover:text-gray-600"
            title="Copy to clipboard"
          >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
            </svg>
          </button>
        </dd>
      </div>

      <div>
        <dt class="text-sm font-medium text-gray-500">Signed At</dt>
        <dd class="mt-1 text-sm text-gray-900">{{ formatDate(signedDocument.signed_document.signed_at) }}</dd>
      </div>
    </dl>

    <!-- Verification Section -->
    <div v-if="signedDocument.signed_document.verification_url" class="mt-6 rounded-lg bg-blue-50 p-4">
      <div class="flex">
        <div class="flex-shrink-0">
          <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
          </svg>
        </div>
        <div class="ml-3 flex-1">
          <h4 class="text-sm font-medium text-blue-800">Signature Verification</h4>
          <p class="mt-1 text-sm text-blue-700">
            This document can be independently verified using the QR code watermark or the verification URL below.
          </p>
          <div class="mt-3">
            <a 
              :href="signedDocument.signed_document.verification_url" 
              target="_blank"
              class="inline-flex items-center text-sm font-medium text-blue-600 hover:text-blue-500"
            >
              Verify Signature
              <svg class="ml-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
              </svg>
            </a>
          </div>
        </div>
      </div>
    </div>

    <!-- Signature Mark Preview -->
    <div v-if="signedDocument.signed_document.signature_mark_url" class="mt-4">
      <h4 class="text-sm font-medium text-gray-700 mb-2">Digital Signature Mark</h4>
      <div class="max-w-sm">
        <img 
          :src="signedDocument.signed_document.signature_mark_url" 
          alt="Signature Mark"
          class="h-auto w-full rounded-lg border border-gray-200 object-cover"
        />
      </div>
    </div>
  </div>
</template>
