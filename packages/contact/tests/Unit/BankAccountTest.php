<?php

use LBHurtado\Contact\Classes\BankAccount;

it('can split a valid bank:account string', function () {
    $ba = BankAccount::fromBankAccount('ABC:123456');
    expect($ba->getBankCode())->toBe('ABC')
        ->and($ba->getAccountNumber())->toBe('123456');
});

it('trims whitespace around code and account', function () {
    $ba = BankAccount::fromBankAccount('  XYZ  :  987654321  ');
    expect($ba->getBankCode())->toBe('XYZ')
        ->and($ba->getAccountNumber())->toBe('987654321');
});

it('allows extra colons in the account number', function () {
    $ba = BankAccount::fromBankAccount('BANK:12:34:56');
    expect($ba->getBankCode())->toBe('BANK')
        ->and($ba->getAccountNumber())->toBe('12:34:56');
});

it('throws if no colon present', function () {
    BankAccount::fromBankAccount('NO_COLON_HERE');
})->throws(InvalidArgumentException::class, 'format code:account');

it('throws if code is empty', function () {
    BankAccount::fromBankAccount(':12345');
})->throws(InvalidArgumentException::class, 'cannot be empty');

it('throws if account is empty', function () {
    BankAccount::fromBankAccount('ABC:');
})->throws(InvalidArgumentException::class, 'cannot be empty');


describe('BankAccount', function () {
    it('can return the bank account as a string', function () {
        $ba = new BankAccount('ABC', '123456');
        expect($ba->getBankAccount())->toBe('ABC:123456');
    });

    it('trims and reconstructs bank account from string', function () {
        $ba = BankAccount::fromBankAccount('  XYZ  :  987654321  ');
        expect($ba->getBankAccount())->toBe('XYZ:987654321');
    });

    it('handles account numbers with colons', function () {
        $ba = BankAccount::fromBankAccount('BANK:12:34:56');
        expect($ba->getBankAccount())->toBe('BANK:12:34:56');
    });

    it('throws an exception when no colon is present', function () {
        BankAccount::fromBankAccount('NO_COLON_HERE');
    })->throws(InvalidArgumentException::class, 'format code:account');

    it('throws an exception when bank code is missing', function () {
        BankAccount::fromBankAccount(':123456');
    })->throws(InvalidArgumentException::class, 'cannot be empty');

    it('throws an exception when account number is missing', function () {
        BankAccount::fromBankAccount('ABC:');
    })->throws(InvalidArgumentException::class, 'cannot be empty');

    it('can fallback to default if invalid input is given', function () {
        $ba = BankAccount::fromBankAccountWithFallback('', 'DEF:78910');
        expect($ba->getBankAccount())->toBe('DEF:78910');
    });
});

it('casts to string as bank:account', function () {
    $bankAccount = new BankAccount('ABC', '123456');

    expect((string) $bankAccount)->toBe('ABC:123456');
});

it('trims and casts with spaces', function () {
    $bankAccount = new BankAccount('  XYZ  ', '  7890  ');

    expect((string) $bankAccount)->toBe('  XYZ  :  7890  '); // Raw input is preserved
});

it('handles complex account formats when casted to string', function () {
    $bankAccount = new BankAccount('BANK', '12:34:56');

    expect((string) $bankAccount)->toBe('BANK:12:34:56');
});
