<script setup lang="ts">
import { useEchoPublic } from '@laravel/echo-vue'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import axios from 'axios'
import type { ContactData, KycContactResponse } from '@/types/kyc'

const props = defineProps<{
  success: boolean
  message: string
  transactionId?: string
  status?: string
  document?: {
    uuid: string
    filename: string
  }
}>()

const contactData = ref<ContactData | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)

const fetchContactData = async () => {
  if (!props.transactionId || !props.success) return
  
  loading.value = true
  error.value = null
  
  try {
    const { data } = await axios.get<KycContactResponse>(
      `/api/kyc/${props.transactionId}/contact`
    )
    
    if (data.ready && data.contact) {
      contactData.value = data.contact
    } else {
      error.value = data.message || 'Still processing...'
    }
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to fetch contact data'
  } finally {
    loading.value = false
  }
}

const formatDate = (dateStr: string | null) => {
  if (!dateStr) return 'N/A'
  return new Date(dateStr).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}

// Real-time listener for ContactReady event
const channelName = computed(() => 
  props.transactionId ? `kyc.${props.transactionId}` : null
)

interface ContactReadyPayload {
  contact: ContactData
}

if (channelName.value && props.success) {
  const { listen, stopListening, leaveChannel } = useEchoPublic<ContactReadyPayload>(
    channelName.value,
    '.contact.ready',
    (payload) => {
      console.log('[KYC Complete] Contact ready event received', payload)
      if (payload.contact) {
        contactData.value = payload.contact
        error.value = null
      }
    }
  )
  
  onMounted(() => {
    listen()
    console.log('[KYC Complete] Listening for contact.ready on channel:', channelName.value)
  })
  
  onBeforeUnmount(() => {
    stopListening()
    leaveChannel(true)
  })
}
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

      <div v-if="success" class="space-y-6">
        <div class="rounded-lg bg-white p-6 shadow">
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
        </div>

        <!-- Contact Data Section -->
        <div v-if="!contactData" class="rounded-lg bg-white p-6 shadow">
          <div class="text-center">
            <p class="text-sm text-gray-600 mb-4">
              {{ error || 'Processing your verification. Please wait a moment and refresh to view your captured data.' }}
            </p>
            <button
              @click="fetchContactData"
              :disabled="loading"
              class="inline-flex items-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <svg v-if="loading" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              {{ loading ? 'Checking...' : 'Refresh Data' }}
            </button>
          </div>
        </div>

        <!-- Personal Information Card -->
        <div v-if="contactData" class="rounded-lg bg-white p-6 shadow">
          <h3 class="text-lg font-medium text-gray-900 mb-4">Personal Information</h3>
          <dl class="grid grid-cols-1 gap-x-4 gap-y-4 sm:grid-cols-2">
            <div>
              <dt class="text-sm font-medium text-gray-500">Full Name</dt>
              <dd class="mt-1 text-sm text-gray-900">{{ contactData.name || 'N/A' }}</dd>
            </div>
            <div>
              <dt class="text-sm font-medium text-gray-500">Birth Date</dt>
              <dd class="mt-1 text-sm text-gray-900">{{ formatDate(contactData.birth_date) }}</dd>
            </div>
            <div>
              <dt class="text-sm font-medium text-gray-500">Gender</dt>
              <dd class="mt-1 text-sm text-gray-900">{{ contactData.gender === 'M' ? 'Male' : contactData.gender === 'F' ? 'Female' : 'N/A' }}</dd>
            </div>
            <div>
              <dt class="text-sm font-medium text-gray-500">Verification Status</dt>
              <dd class="mt-1">
                <span 
                  class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                  :class="{
                    'bg-green-100 text-green-800': contactData.kyc_status === 'approved',
                    'bg-yellow-100 text-yellow-800': contactData.kyc_status === 'pending',
                    'bg-red-100 text-red-800': contactData.kyc_status === 'rejected'
                  }"
                >
                  {{ contactData.kyc_status.toUpperCase() }}
                </span>
              </dd>
            </div>
            <div class="sm:col-span-2">
              <dt class="text-sm font-medium text-gray-500">Address</dt>
              <dd class="mt-1 text-sm text-gray-900">{{ contactData.address || 'N/A' }}</dd>
            </div>
          </dl>
        </div>

        <!-- Verification Photos -->
        <div v-if="contactData && (contactData.id_card_urls.length > 0 || contactData.selfie_url)" class="rounded-lg bg-white p-6 shadow">
          <h3 class="text-lg font-medium text-gray-900 mb-4">Verification Photos</h3>
          
          <div class="space-y-4">
            <!-- ID Cards -->
            <div v-if="contactData.id_card_urls.length > 0">
              <h4 class="text-sm font-medium text-gray-700 mb-2">ID Card Images</h4>
              <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div v-for="(url, index) in contactData.id_card_urls" :key="index" class="relative">
                  <img 
                    :src="url" 
                    :alt="`ID Card ${index + 1}`"
                    class="h-auto w-full rounded-lg border border-gray-200 object-cover"
                  />
                  <p class="mt-1 text-xs text-gray-500 text-center">Image {{ index + 1 }}</p>
                </div>
              </div>
            </div>
            
            <!-- Selfie -->
            <div v-if="contactData.selfie_url">
              <h4 class="text-sm font-medium text-gray-700 mb-2">Selfie Photo</h4>
              <div class="max-w-sm mx-auto">
                <img 
                  :src="contactData.selfie_url" 
                  alt="Selfie"
                  class="h-auto w-full rounded-lg border border-gray-200 object-cover"
                />
              </div>
            </div>
          </div>
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
