<?php

use Spatie\SchemalessAttributes\SchemalessAttributes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Propaganistas\LaravelPhone\PhoneNumber;
use LBHurtado\Contact\Models\Contact;

uses(RefreshDatabase::class);

it('has attributes', function () {
    $contact = Contact::factory()->create();
    expect($contact)->toBeInstanceOf(Contact::class);
    expect($contact->mobile)->toBeString();
    expect($contact->country)->toBeString();
    expect($contact->bank_code)->toBeString();
    expect($contact->account_number)->toBeString();
});

it('defaults country to PH when not provided', function () {
    // Create without specifying country
    /** @var Contact $c */
    $c = Contact::create([
        'mobile' => '09171234567',
        'country' => '',      // empty
    ]);

    $default_country = config('contact.default.country');
    expect($c->country)->toBe($default_country);
});

it('respects an explicit country attribute', function () {
    /** @var Contact $c */
    $c = Contact::create([
        'mobile'  => '09171234567',
        'country' => 'PH',
    ]);

    expect($c->country)->toBe('PH');
});

it('formats mobile on set using the HasMobile mutator and persists it normalized', function () {
    $raw = ' (0917) 123-4567 ';
    /** @var Contact $c */
    $c = Contact::create([
        'mobile'  => $raw,
        'country' => '',
    ]);

    $default_country = config('contact.default.country');

    $expected = phone($raw, $default_country)
        ->formatForMobileDialingInCountry($default_country);

    // Stored value in DB should equal the normalized version
    $this->assertDatabaseHas('contacts', [
        'id'     => $c->id,
        'mobile' => $expected,
    ]);
});

it('formats mobile on get using the HasMobile accessor', function () {
    $raw = '09171234567';
    $formatted = phone($raw, 'PH')->formatForMobileDialingInCountry('PH');

    // Bypass creation logic to insert raw, then load and read accessor
    \DB::table('contacts')->insert([
        'mobile'  => $formatted,
        'country' => 'PH',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $c = Contact::first();
    expect($c->mobile)->toBe($formatted);
});

it('works with a non-default country (e.g. US)', function () {
    $raw = '202-555-0133';
    $c = Contact::create([
        'mobile'  => $raw,
        'country' => 'US',
    ]);

    $expected = phone($raw, 'US')
        ->formatForMobileDialingInCountry('US');

    expect($c->country)->toBe('US');
    expect($c->mobile)->toBe($expected);
})->skip();


use Illuminate\Support\Str;
use Illuminate\Support\Facades\Config;

uses()->group('contact');

beforeEach(function () {
    // ensure default config in case you rely on it
    Config::set('contact.default.country', 'PH');
    Config::set('contact.default.bank_account', 'DEFAULTBANK');
});

it('splits bank_account into bank_code and account_number', function () {
    $contact = Contact::make([
        'mobile' => '09171234567',
        'country' => 'PH',
        // simulate a manual override
        'bank_account' => 'MYBANK:1234567890',
    ]);

    // no need to save—the accessor works right away
    expect($contact->bank_code)->toBe('MYBANK')
        ->and($contact->account_number)->toBe('1234567890');
});

//it('falls back gracefully when no colon is present', function () {
//    $contact = Contact::make([
//        'mobile' => '09171234567',
//        'country' => 'PH',
//        'bank_account' => 'JUSTONESTRING',
//    ]);
//
//    expect($contact->bank_code)->toBe('JUSTONESTRING')
//        ->and($contact->account_number)->toBe('');
//});

it('booted creating() ensures default bank_account is applied', function () {
    // clear any existing bank_account override
    $c = Contact::create([
        'mobile' => '09171234567',
        'country' => null,
    ]);

    // after create(), country should be default and bank_account built
    expect($c->country)->toBe('PH');
    // default bank_account prefix from config + ":" + mobile
    expect($c->bank_account)->toBe('GXCHPHM2XXX:09171234567');
    // and our getters still work
    expect($c->bank_code)->toBe('GXCHPHM2XXX')
        ->and($c->account_number)->toBe('09171234567');
});

it('can be persisted using PhoneNumber object', function () {
    $phone = new PhoneNumber('09171234567', 'PH');
    $contact = Contact::fromPhoneNumber($phone);
    expect($contact)->toBeInstanceOf(Contact::class);
    expect($contact->mobile)->toBe('09171234567');
    expect($contact->country)->toBe('PH');
});

it('has meta attributes', function () {
    $contact = Contact::factory()->create();
    expect($contact->meta)->toBeInstanceOf(SchemalessAttributes::class);

    expect($contact->name)->toBeEmpty();
    $name = 'Juan de la Cruz';
    $contact->name = $name;
    $contact->save();

    $c1 = Contact::find($contact->id);
    expect($c1->name)->toBe($name);
});
