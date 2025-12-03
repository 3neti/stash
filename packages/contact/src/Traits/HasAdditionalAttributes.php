<?php

namespace LBHurtado\Contact\Traits;

trait HasAdditionalAttributes
{
    const NAME_FIELD = 'name';
    const EMAIL_FIELD = 'email';
    const BIRTH_DATE = 'birth_date';
    const ADDRESS_FIELD = 'address';
    const GROSS_MONTHLY_INCOME_FIELD = 'gross_monthly_income';
    
    // KYC fields
    const KYC_TRANSACTION_ID = 'kyc_transaction_id';
    const KYC_STATUS = 'kyc_status';
    const KYC_ONBOARDING_URL = 'kyc_onboarding_url';
    const KYC_SUBMITTED_AT = 'kyc_submitted_at';
    const KYC_COMPLETED_AT = 'kyc_completed_at';
    const KYC_REJECTION_REASONS = 'kyc_rejection_reasons';

    public function initializeHasAdditionalAttributes(): void
    {
        $this->mergeFillable([
            self::NAME_FIELD,
            self::EMAIL_FIELD,
            self::BIRTH_DATE,
            self::ADDRESS_FIELD,
            self::GROSS_MONTHLY_INCOME_FIELD,
            self::KYC_TRANSACTION_ID,
            self::KYC_STATUS,
            self::KYC_ONBOARDING_URL,
            self::KYC_SUBMITTED_AT,
            self::KYC_COMPLETED_AT,
            self::KYC_REJECTION_REASONS,
        ]);
    }

    // Setters and Getters for each field

    public function setNameAttribute(?string $value): self
    {
        if ($value !== null) {
            $this->getAttribute('meta')->set(self::NAME_FIELD, $value);
        }

        return $this;
    }

    public function getNameAttribute(): ?string
    {
        return $this->getAttribute('meta')->get(self::NAME_FIELD) ?? '';
    }

    public function setEmailAttribute(?string $value): self
    {
        if ($value !== null) {
            $this->getAttribute('meta')->set(self::EMAIL_FIELD, $value);
        }

        return $this;
    }

    public function getEmailAttribute(): ?string
    {
        return $this->getAttribute('meta')->get(self::EMAIL_FIELD) ?? '';
    }

    public function setBirthDateAttribute(?string $value): self
    {
        if ($value !== null) {
            $this->getAttribute('meta')->set(self::BIRTH_DATE, $value);
        }

        return $this;
    }

    public function getBirthDateAttribute(): ?string
    {
        return $this->getAttribute('meta')->get(self::BIRTH_DATE) ?? '';
    }

    public function setAddressAttribute(?string $value): self
    {
        if ($value !== null) {
            $this->getAttribute('meta')->set(self::ADDRESS_FIELD, $value);
        }

        return $this;
    }

    public function getAddressAttribute(): ?string
    {
        return $this->getAttribute('meta')->get(self::ADDRESS_FIELD) ?? '';
    }

    public function setGrossMonthlyIncomeAttribute(?string $value): self
    {
        if ($value !== null) {
            $this->getAttribute('meta')->set(self::GROSS_MONTHLY_INCOME_FIELD, $value);
        }

        return $this;
    }

    public function getGrossMonthlyIncomeAttribute(): ?string
    {
        return $this->getAttribute('meta')->get(self::GROSS_MONTHLY_INCOME_FIELD) ?? '';
    }

    // KYC Attributes

    public function setKycTransactionIdAttribute(?string $value): self
    {
        if ($value !== null) {
            $this->getAttribute('meta')->set(self::KYC_TRANSACTION_ID, $value);
        }
        return $this;
    }

    public function getKycTransactionIdAttribute(): ?string
    {
        return $this->getAttribute('meta')->get(self::KYC_TRANSACTION_ID);
    }

    public function setKycStatusAttribute(?string $value): self
    {
        if ($value !== null) {
            $this->getAttribute('meta')->set(self::KYC_STATUS, $value);
        }
        return $this;
    }

    public function getKycStatusAttribute(): ?string
    {
        return $this->getAttribute('meta')->get(self::KYC_STATUS);
    }

    public function setKycOnboardingUrlAttribute(?string $value): self
    {
        if ($value !== null) {
            $this->getAttribute('meta')->set(self::KYC_ONBOARDING_URL, $value);
        }
        return $this;
    }

    public function getKycOnboardingUrlAttribute(): ?string
    {
        return $this->getAttribute('meta')->get(self::KYC_ONBOARDING_URL);
    }

    public function setKycSubmittedAtAttribute(?string $value): self
    {
        if ($value !== null) {
            $this->getAttribute('meta')->set(self::KYC_SUBMITTED_AT, $value);
        }
        return $this;
    }

    public function getKycSubmittedAtAttribute(): ?string
    {
        return $this->getAttribute('meta')->get(self::KYC_SUBMITTED_AT);
    }

    public function setKycCompletedAtAttribute(?string $value): self
    {
        if ($value !== null) {
            $this->getAttribute('meta')->set(self::KYC_COMPLETED_AT, $value);
        }
        return $this;
    }

    public function getKycCompletedAtAttribute(): ?string
    {
        return $this->getAttribute('meta')->get(self::KYC_COMPLETED_AT);
    }

    public function setKycRejectionReasonsAttribute(?array $value): self
    {
        if ($value !== null) {
            $this->getAttribute('meta')->set(self::KYC_REJECTION_REASONS, $value);
        }
        return $this;
    }

    public function getKycRejectionReasonsAttribute(): ?array
    {
        return $this->getAttribute('meta')->get(self::KYC_REJECTION_REASONS);
    }
}
