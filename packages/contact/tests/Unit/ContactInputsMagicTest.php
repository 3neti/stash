<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Contact\Tests\Models\User;

uses(RefreshDatabase::class);


/** This test is does not mean that Contact will be implementing InputInterface. */
/** We are just using the User model in the Test */

beforeEach(function () {
    // Make sure default country/bank_code are set
    config()->set('contact.default.country', 'PH');
    config()->set('contact.default.bank_code', 'DEF');
});

it('returns the real mobile column via __get', function () {
    $user = User::factory()->create([
        'mobile'       => '09170000001',
    ]);

    // Even if we later add an input record named "mobile", $user->mobile should still be the column value:
    $user->inputs()->create(['name' => 'mobile', 'value' => '6391710001']);

    expect($user->mobile)->toBe('09170000001');
});

it('allows reading other inputs via magic __get only when not a real attribute', function () {
    $user = User::factory()->create();

    // No "signature" column on users table, so magic should kick in:
    $user->inputs()->create(['name' => 'signature', 'value' => 'sig_block']);

    // __get('signature') should pull from inputs relation:
    expect($user->signature)->toBe('sig_block');
});

it('persists inputs when you assign via magic __set', function () {
    $user = User::factory()->create();

    // Assign via magic setter; signature is not a real attribute so will go through forceSetInput
    $user->signature = 'my_sig';
    $user->save();

    // It should have created an input record:
    $this->assertDatabaseHas('inputs', [
        'model_type' => User::class,
        'model_id'   => $user->getKey(),
        'name'       => 'signature',
        'value'      => 'my_sig',
    ]);

    // And magic __get returns it:
    expect($user->signature)->toBe('my_sig');
});

it('setting a real attribute via magic __set still updates the attribute', function () {
    $user = User::factory()->create(['mobile' => '09170000001']);

    // mobile is a real attribute, so __set should defer to Eloquent and update the model column:
    $user->mobile = '09170009999';
    $user->save();

    // No input record should be created for "mobile":
    $this->assertDatabaseMissing('inputs', [
        'model_type' => User::class,
        'model_id'   => $user->getKey(),
        'name'       => 'mobile',
    ]);

    // And the model's mobile attribute should reflect the change:
    expect($user->mobile)->toBe('09170009999');
});

it('input() helper returns the raw input value regardless of column collisions', function () {
    $user = User::factory()->create(['mobile' => '09170000001']);
    $user->forceSetInput('mobile', '639171234567');

    // input('mobile') should retrieve the input‐table value:
    expect($user->input('mobile'))->toBe('639171234567');

    // But magic $user->mobile stays the column:
    expect($user->mobile)->toBe('09170000001');
});
