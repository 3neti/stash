<?php

use LBHurtado\Contact\Data\ContactData;
use LBHurtado\Contact\Models\Contact;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

// Ensure default country for repeatability
beforeEach(function () {
    config()->set('contact.default.country', 'PH');
    config()->set('contact.default.bank_code', 'TESTBANK');
});

test('fromModel maps properties correctly', function () {
    // Create a contact with explicit bank_account
    $contact = Contact::factory()->create([
        'mobile'       => '09171234567',
        'country'      => 'PH',
        'bank_account' => 'BANKX:000111222',
    ]);

    $dto = ContactData::fromModel($contact);

    expect($dto)
        ->mobile->toBe('09171234567')
        ->country->toBe('PH')
        ->bank_account->toBe('BANKX:000111222')
        ->bank_code->toBe('BANKX')
        ->account_number->toBe('000111222')
    ;
});

test('fromModel uses defaults when bank_account not provided', function () {
    // Omit bank_account, factory should apply default in boot
    $contact = Contact::factory()->create([
        'mobile'  => '09170000001',
        'country' => null,
        'bank_account' => null,
    ]);

    // Model boot sets country to default and bank_account to default:mobile
    expect($contact->country)->toBe('PH');
    $expectedBankAccount = 'TESTBANK:09170000001';
    expect($contact->bank_account)->toBe($expectedBankAccount);

    $dto = ContactData::fromModel($contact);

    expect($dto->bank_account)->toBe($expectedBankAccount)
        ->and($dto->bank_code)->toBe('TESTBANK')
        ->and($dto->account_number)->toBe('09170000001');
});

test('toArray and json serialization include correct keys', function () {
    $contact = Contact::factory()->create([
        'mobile'       => '09171234567',
        'country'      => 'PH',
        'bank_account' => 'USBANK:123456789',
    ]);

    $dto = ContactData::fromModel($contact);

    $array = $dto->toArray();
    $json  = $dto->toJson();

    expect($array)
        ->toBeArray()
        ->toHaveKeys(['mobile', 'country', 'bank_account', 'bank_code', 'account_number'])
        ->and($array['mobile'])->toBe('09171234567')
        ->and($array['country'])->toBe('PH')
        ->and($array['bank_account'])->toBe('USBANK:123456789')
        ->and($array['bank_code'])->toBe('USBANK')
        ->and($array['account_number'])->toBe('123456789');

    // JSON should decode to same array
    $decoded = json_decode($json, true);
    expect($decoded)->toEqual($array);
});

