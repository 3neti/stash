<?php

use LBHurtado\ModelChannel\Tests\Models\User;
use LBHurtado\ModelChannel\Enums\Channel;

it('can retrieve user channels', function () {
    // Arrange
    $user = User::factory()->create();
    $user->channels()->create(['name' => 'email', 'value' => 'user@example.com']);//TODO: Check this out. Why is email value allowed
    $user->channels()->create(['name' => 'mobile', 'value' => '09171234567']);

    // Act
    $channels = $user->channels;

    // Assert
    expect($channels)->toHaveCount(2);
    expect($channels->pluck('name')->toArray())->toBe(['mobile', 'email']);
    expect($channels->pluck('value')->toArray())->toBe(['09171234567', 'user@example.com']);
});

it('can set a valid channel', function () {
    // Arrange
    $user = User::factory()->create();

    // Act
    $user->setChannel('mobile', '09171234567');

    // Assert
    $this->assertDatabaseHas('channels', [
        'name' => 'mobile',
        'value' => '639171234567',
        'model_type' => User::class,
        'model_id' => $user->id,
    ]);
});
//
//it('throws exception when channel is invalid', function () {
//    // Arrange
//    $user = User::factory()->create();
//
//    // Mock isValidChannel to return false
//    $mock = Mockery::mock(User::class . '[isValidChannel]', [$user->getAttributes()]);
//    $mock->shouldReceive('isValidChannel')->with('invalid_channel', 'value')->andReturn(false);
//    $mock->makePartial();
//
//    // Act & Assert
//    $this->expectException(\phpDocumentor\Reflection\Exception::class);
//    $mock->setStatus('invalid_channel', 'value');
//});
//
//it('can force set a channel even if invalid', function () {
//    // Arrange
//    $user = User::factory()->create();
//
//    // Act
//    $user->forceSetChannel('invalid_channel', 'value');
//
//    // Assert
//    $this->assertDatabaseHas('channels', [
//        'name' => 'invalid_channel',
//        'value' => 'value',
//        'model_type' => User::class,
//        'model_id' => $user->id,
//    ]);
//});
//
it('retrieves channels in descending order of id', function () {
    // Arrange
    $user = User::factory()->create();
    $user->forceSetChannel('mobile', '09171234567');
    $user->forceSetChannel('webhook', 'https://example.com/webhook');

    // Act
    $channels = $user->channels;

    // Assert
    expect($channels->pluck('name')->toArray())->toBe(['webhook', 'mobile']);
});

it('returns the mobile and webhook values from the mobile channel', function () {
    // Arrange
    $user = User::factory()->create(); // Create a user

    // Create an email channel for the user
    $user->channels()->create([
        'name' => 'mobile',
        'value' => '09171234567',
    ]);
    $user->channels()->create([
        'name' => 'webhook',
        'value' => 'https://example.com/webhook',
    ]);


    // Act
    $mobile = $user->mobile;
    $webhook = $user->webhook;

    // Assert
    expect($mobile)->toBe('09171234567');
    expect($webhook)->toBe('https://example.com/webhook');
});

it('returns the mobile value from a preloaded channels relationship', function () {
    // Arrange
    $user = User::factory()->create();

    // Create multiple channels, including an email channel
    $user->channels()->createMany([
        ['name' => 'mobile', 'value' => '09171234567'],
        ['name' => 'webhook', 'value' => 'https://example.com/webhook'],
    ]);

    // Reload the user with the channels relation preloaded
    $user = User::with('channels')->find($user->id);

    // Act
    $mobile = $user->mobile; // This should use the preloaded relationship instead of querying the database
    // Assert
    expect($mobile)->toBe('09171234567'); // Assert the mobile attribute returns the correct value
});

it('returns false for disallowed channel names', function () {
    // Arrange
    $user = User::factory()->create();

    // Act & Assert
    expect($user->isValidChannel('email', 'lester@hurtado.ph'))->toBeFalse(); // Disallowed name
    expect($user->isValidChannel('mobile', '09171234567'))->toBeTrue(); // Allowed name
});

it('returns true for allowed channel names with value', function () {
    // Arrange
    $user = User::factory()->create();

    // Act & Assert
    expect($user->isValidChannel('mobile', '9876543210'))->toBeTrue(); // Allowed name with value
    expect($user->isValidChannel('mobile'))->toBeFalse();              // Not allowed name without value
});

it('returns false for allowed channel names invalid values', function () {
    // Arrange
    $user = User::factory()->create();

    // Act & Assert
    expect($user->isValidChannel('mobile', '09876543210'))->toBeTrue(); // Allowed
    expect($user->isValidChannel('mobile', 'not valid'))->toBeFalse();  // Not allowed

    expect($user->isValidChannel('webhook', 'https://google.com'))->toBeTrue();
    expect($user->isValidChannel('webhook', 'invalid'))->toBeFalse();
});

it('allows setting a channel using Channels enum', function () {
    // Arrange
    $user = User::factory()->create();

    // Act
    $user->setChannel(Channel::MOBILE, '9876543210');

    // Assert
    expect($user->channels()->where('name', Channel::MOBILE->value)->exists())->toBeTrue();
});

it('throws exception for disallowed channel names', function () {
    // Arrange
    $user = User::factory()->create();

    // Act & Assert
    expect(fn () => $user->setChannel('email', 'test@example.com'))
        ->toThrow(Exception::class, 'Channel name is not valid');
});

it('allows setting a channel using a string', function () {
    // Arrange
    $user = User::factory()->create();

    // Act
    $user->setChannel('webhook', 'https://example.com/webhook');

    // Assert
    expect($user->channels()->where('name', 'webhook')->exists())->toBeTrue();
});

it('sets the mobile and webhook attributes and stores it as a channel', function () {
    // Arrange
    $user = User::factory()->create();

//    dd(config('model-channel.rules'));
    // Act
    $user->mobile = '9876543210'; // Using the setter
    $user->webhook = 'https://example.com/webhook';

    // Assert
    $channel = $user->channels()->where('name', 'mobile')->first();

    // Ensure the channel was created
    expect($channel)->not()->toBeNull()
        ->and($channel->value)->toBe('639876543210');

    // Assert
    $channel = $user->channels()->where('name', 'webhook')->first();

    // Ensure the channel was created
    expect($channel)->not()->toBeNull()
        ->and($channel->value)->toBe('https://example.com/webhook');

});

dataset('formatted_mobiles', function () {
    return [
        ['9171234567'],
        ['09171234567'],
        ['0917 123 4567'],
        ['639171234567'],
        ['+639171234567'],
        ['+63 (917) 123-4567'],
    ];
});

it('can find a user based on the mobile channel', function (string $mobile) {
    // Arrange
    $user = User::factory()->create(); // Create a user

    // Assign a mobile channel to the user
    $user->mobile = '9171234567';
    $user->save();

    // Act
    $foundUser = User::findByMobile($mobile);

    // Assert
    expect($foundUser)->not()->toBeNull(); // Ensure a user is found
    expect($foundUser->id)->toBe($user->id); // Ensure the correct user is found
})->with('formatted_mobiles');

dataset('inconsistent_mobiles', function () {
    return [
        'E.164 strict match' => ['+639171234567', '639171234567', true],
        'Normalized LIKE match' => ['09171234567', '9171234567', true], // Leading "0" stripped
//        'National LIKE match' => ['+63 (917) 123-4567', '0917 123 4567', true], // Spaces ignored TODO: check mo ito edge case naman
        'Leading 0 stripped' => ['09171234567', '917123456789', true], // Additional digits with match
        'No match' => ['+639171234567', '9123456789', false], // Completely different number
    ];
});

it('properly resolves phone matches with strict and relaxed conditions', function (string $input, string $storedValue, bool $expectedResult) {
    // Arrange
    $user = User::factory()->create();
    $user->channels()->create([
        'name' => 'mobile',
        'value' => $storedValue,
    ]);

    // Act
    $foundUser = User::findByMobile($input);

    // Assert
    if ($expectedResult) {
        expect($foundUser)->not()->toBeNull();
        expect($foundUser->id)->toBe($user->id)
        ;
    } else {
        expect($foundUser)->toBeNull();
    }
})->with('inconsistent_mobiles')->skip();

it('adds a channel and deletes it when null is passed', function () {
    $user = User::factory()->create();
    $user->setChannel(Channel::MOBILE, '09171234567');

    expect($user->channels)->toHaveCount(1);
    expect($user->mobile)->toBe('639171234567');

    // Set to null: should delete the channel
    $user->setChannel(Channel::MOBILE, null);

    expect($user->channels()->where('name', 'mobile')->exists())->toBeFalse();
});

it('deletes the channel when empty string is passed', function () {
    $user = User::factory()->create();

    $user->mobile = '09171234567';

    expect($user->mobile)->toBe('639171234567');

    $user->mobile = '';

    expect($user->mobile)->toBeNull();
    expect($user->channels()->where('name', 'mobile')->exists())->toBeFalse();
});

it('does not add duplicate records for the same name-value pair', function () {
    $user = User::factory()->create();

    $user->setChannel(Channel::MOBILE, '09171234567');
    $user->setChannel(Channel::MOBILE, '09171234567');

    expect($user->channels()->where('name', 'mobile')->count())->toBe(1);
});

it('updates the value if the channel value changes', function () {
    $user = User::factory()->create();

    $user->setChannel(Channel::MOBILE, '09171234567');
    $user->setChannel(Channel::MOBILE, '09181234567');

    expect($user->channels()->where('name', 'mobile')->count())->toBe(1);
    expect($user->mobile)->toBe('639181234567');
});

it('preserves other channel values when updating a specific one', function () {
    $user = User::factory()->create();

    $user->setChannel(Channel::MOBILE, '09171234567');
    $user->setChannel(Channel::WEBHOOK, 'http://example.com');

    expect($user->channels()->count())->toBe(2);

    $user->setChannel(Channel::MOBILE, '09181234567');

    expect($user->channels()->count())->toBe(2);
    expect($user->mobile)->toBe('639181234567');
    expect($user->webhook)->toBe('http://example.com');
});
