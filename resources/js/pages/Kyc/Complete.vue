<script setup lang="ts">
defineProps<{
  success: boolean
  message: string
  transactionId?: string
  status?: string
  document?: {
    uuid: string
    filename: string
  }
}>()
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-gray-50 px-4 py-12 sm:px-6 lg:px-8">
    <div class="w-full max-w-md space-y-8">
      <div>
        <div v-if="success" class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-green-100">
          <svg class="h-10 w-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
        </div>
        <div v-else class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-red-100">
          <svg class="h-10 w-10 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </div>
        
        <h2 class="mt-6 text-center text-3xl font-bold tracking-tight text-gray-900">
          {{ success ? 'KYC Verification Submitted' : 'Verification Error' }}
        </h2>
        
        <p class="mt-2 text-center text-sm text-gray-600">
          {{ message }}
        </p>
      </div>

      <div v-if="success" class="rounded-lg bg-white p-6 shadow">
        <dl class="space-y-4">
          <div v-if="transactionId">
            <dt class="text-sm font-medium text-gray-500">Transaction ID</dt>
            <dd class="mt-1 text-sm font-mono text-gray-900">{{ transactionId }}</dd>
          </div>
          
          <div v-if="status">
            <dt class="text-sm font-medium text-gray-500">Status</dt>
            <dd class="mt-1">
              <span 
                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                :class="{
                  'bg-green-100 text-green-800': status === 'auto_approved',
                  'bg-yellow-100 text-yellow-800': status === 'pending' || status === 'processing',
                  'bg-red-100 text-red-800': status === 'rejected'
                }"
              >
                {{ status.replace('_', ' ').toUpperCase() }}
              </span>
            </dd>
          </div>

          <div v-if="document">
            <dt class="text-sm font-medium text-gray-500">Document</dt>
            <dd class="mt-1 text-sm text-gray-900">{{ document.filename }}</dd>
          </div>
        </dl>

        <div class="mt-6 border-t border-gray-200 pt-6">
          <p class="text-sm text-gray-600">
            We will process your verification and update the document status shortly. 
            You may close this window.
          </p>
        </div>
      </div>

      <div v-if="!success" class="text-center">
        <a 
          href="/" 
          class="inline-flex items-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
        >
          Return to Home
        </a>
      </div>
    </div>
  </div>
</template>
