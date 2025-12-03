<?php

namespace LBHurtado\Contact\Traits;

use LBHurtado\Contact\Classes\BankAccount;
use InvalidArgumentException;

trait HasBankAccount
{
    /**
     * @return string  either the bank-code part of `bank_account` or the default config value
     */
    public function getBankCode(): string
    {
        try {
            return BankAccount::fromBankAccount($this->bank_account)->getBankCode();
        } catch (InvalidArgumentException $e) {
            return config('contact.default.bank_code');
        }
    }

    /**
     * @return string  either the account-number part of `bank_account` or the model's mobile
     */
    public function getAccountNumber(): string
    {
        try {
            return BankAccount::fromBankAccount($this->bank_account)->getAccountNumber();
        } catch (InvalidArgumentException $e) {
            return $this->mobile;
        }
    }
}
