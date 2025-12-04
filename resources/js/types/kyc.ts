export interface ContactData {
  id: string
  name: string | null
  birth_date: string | null
  address: string | null
  gender: string | null
  kyc_status: 'approved' | 'rejected' | 'pending'
  kyc_completed_at: string | null
  id_card_urls: string[]
  selfie_url: string | null
}

export interface KycContactResponse {
  ready: boolean
  message?: string
  contact?: ContactData
}
