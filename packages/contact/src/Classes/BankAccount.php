<?php

namespace LBHurtado\Contact\Classes;

use InvalidArgumentException;
use Illuminate\Support\Str;

class BankAccount
{
    public function __construct(
        protected string $bank_code,
        protected string $account_number
    ) {}

    public function getBankCode(): string
    {
        return $this->bank_code;
    }

    public function getAccountNumber(): string
    {
        return $this->account_number;
    }

    public function getBankAccount(): string
    {
        return $this->bank_code . ':' . $this->account_number;
    }

    public function __toString(): string
    {
        return $this->getBankAccount();
    }

    public static function fromBankAccount(string $bank_account): BankAccount
    {
        $raw = trim($bank_account);

        // only one “:” guaranteed, extra colons remain part of the account_number
        if (! Str::contains($raw, ':')) {
            throw new InvalidArgumentException("Bank account must be in the format code:account");
        }

        [$code, $acct] = explode(':', $raw, 2);

        $code = trim($code);
        $acct = trim($acct);

        if ($code === '' || $acct === '') {
            throw new InvalidArgumentException("Bank code and account number cannot be empty");
        }

        return new static($code, $acct);
    }

    public static function fromBankAccountWithFallback(string $raw, string $fallback): self
    {
        try {
            return static::fromBankAccount($raw);
        } catch (InvalidArgumentException $e) {
            return static::fromBankAccount($fallback);
        }
    }
}
