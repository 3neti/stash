<?php

namespace LBHurtado\ModelChannel\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use LBHurtado\ModelChannel\Enums\Channel;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\Builder;
use \Exception;

trait HasChannels
{
    public function channels(): MorphMany
    {
        return $this->morphMany($this->getChannelModelClassName(), 'model', 'model_type', $this->getModelKeyColumnName())
            ->latest('id');
    }

    public function setChannel(string|Channel $name, string|null $value): self
    {
        // Convert Channels enum to its string value if provided
        $name = $name instanceof Channel ? $name->value : $name;

        if (is_null($value) || $value === '') {
            $this->deleteChannel($name);
            return $this;
        }

        if (! $this->isValidChannel($name, $value)) {
            throw new Exception('Channel name is not valid');
        }

//        if (! $this->isValidChannel($name, $value)) {
//            throw new Exception('Channel name is not valid');
//        }

        return $this->forceSetChannel($name, $value);
    }

    public function forceSetChannel(string|Channel $name, string $value): self
    {
        // Convert Channels enum to string if needed
        $name = $name instanceof Channel ? $name->value : $name;

        // Normalize mobile numbers to E.164 format without "+"
        if ($name === 'mobile') {
            $value = ltrim(phone($value, 'PH')->formatE164(), '+');
        }

        // Check if existing record already matches the intended value
        $existing = $this->channels()->where('name', $name)->latest()->first();

        if ($existing && $existing->value === $value) {
            return $this; // No need to insert duplicate
        }

        // Delete any existing record for this channel name before inserting new one
        $this->channels()->where('name', $name)->delete();

        $this->channels()->create([
            'name' => $name,
            'value' => $value,
        ]);

        return $this;
    }

//    public function forceSetChannel(string|Channel $name, string|null $value): self
//    {
//        // Convert Channels enum to its string value if provided
//        $name = $name instanceof Channel ? $name->value : $name;
//
//        // Normalize phone numbers to E.164 format without "+"
//        if ($name === 'mobile') {
//            $value = ltrim(phone($value, 'PH')->formatE164(), '+');
//        }
//
//        $this->channels()->create([
//            'name' => $name,
//            'value' => $value,
//        ]);
//
//        return $this;
//    }

    protected function deleteChannel(string|Channel $name): void
    {
        $name = $name instanceof Channel ? $name->value : $name;

        $this->channels()
            ->where('name', $name)
            ->delete();
    }

    public function isValidChannel(string|Channel $name, ?string $value = null): bool
    {
        // Convert Channel enum to its string value if provided
        $channel = $name instanceof Channel ? $name : Channel::tryFrom($name);

        // Ensure the channel is valid (exists in the Channel enum)
        if (! $channel instanceof Channel) {
            return false;
        }

        // Perform validation using channel-specific rules
        $validator = Validator::make(['value' => $value], ['value' => $channel->rules()]);

        return ! $validator->fails();
    }

    protected function getChannelTableName(): string
    {
        $modelClass = $this->getChannelModelClassName();

        return (new $modelClass)->getTable();
    }

    protected function getModelKeyColumnName(): string
    {
        return config('model-channel.model_primary_key_attribute') ?? 'model_id';
    }

    protected function getChannelModelClassName(): string
    {
        return config('model-channel.channel_model') ?? \LBHurtado\ModelChannel\Models\Channel::class;
    }

    public function __get($key)
    {
        // Check if the key matches any Channel enum value
        if ($channel = $this->getChannelFromEnum($key)) {
            return $this->getChannelAttribute($channel->value);
        }

        // Delegate to the parent method for non-magic properties
        return parent::__get($key);
    }

    public function __set($key, $value)
    {
        // Check if the key matches any Channel enum value
        if ($channel = $this->getChannelFromEnum($key)) {

            $this->setChannelAttribute($channel->value, $value);
            return;
        }

        // Delegate to the parent method for non-magic properties
        parent::__set($key, $value);
    }

    protected function getChannelAttribute(string $name): ?string
    {
        // Check if channels are already loaded to avoid querying the database
        $channel = $this->relationLoaded('channels')
            ? $this->channels->firstWhere('name', $name) // Searches in the loaded relationship (collection)
            : $this->channels()->where('name', $name)->first(); // Falls back to a database query if not loaded

        return $channel?->value;
    }

    protected function setChannelAttribute(string $name, string|null $value): void
    {
        $this->setChannel($name, $value);
    }

    private function getChannelFromEnum(string $key): ?Channel
    {
        // Attempt to match the key to any value in the Channels enum
        foreach (Channel::cases() as $channel) {
            if ($channel->value === $key) {
                return $channel;
            }
        }

        return null; // No match
    }

    public static function findByChannel(string $channelName, string $channelValue): ?self
    {
        return static::whereHas('channels', function (Builder $q) use ($channelName, $channelValue) {
            $q->where('name', $channelName);

            if ($channelName === 'mobile') {
                try {
                    $e164 = ltrim(phone($channelValue, 'PH')->formatE164(), '+');
                    $national = preg_replace('/\D+/', '', $channelValue);
                    $dialable = phone($channelValue, 'PH')->formatForMobileDialingInCountry('PH');
                    $dialable = preg_replace('/\D+/', '', $dialable);

                    $q->where(function ($sub) use ($e164, $national, $dialable) {
                        $sub->where('value', $e164)
                            ->orWhere('value', 'LIKE', "%{$national}%")
                            ->orWhere('value', 'LIKE', "%{$dialable}%");
                    });
                } catch (\Throwable $e) {
                    // nothing will match
                    $q->whereRaw('0 = 1');
                }
            } else {
                $q->where('value', $channelValue);
            }
        })->first();
    }

//    public static function findByChannel(string $channelName, string $channelValue): ?static
//    {
//        return static::whereHas('channels', function ($query) use ($channelName, $channelValue) {
//            if ($channelName === 'mobile') {
//                try {
//                    // Normalize the phone number to E.164 without "+"
//                    $phoneE164WithoutPlus = ltrim(phone($channelValue, 'PH')->formatE164(), '+');
//
//                    // Optional fallback formats
//                    $phone_normalized = preg_replace('/[^0-9]/', '', $channelValue); // Strip non-numeric
//                    $phone_national = str_replace(' ', '', phone($channelValue, 'PH')->formatForMobileDialingInCountry('PH'));
//
//                    // Additional fallback: remove leading '0's for LIKE
//                    $phone_normalized_like_fallback = ltrim($phone_normalized, '0');
//                } catch (\Exception $e) {
//                    // Return no result if the phone number cannot be normalized
//                    return $query->whereRaw('1 = 0'); // No match
//                }
//                // Match the normalized E.164 format or fallback formats
//                $query->where('name', $channelName)
//                    ->where(function ($subQuery) use ($phoneE164WithoutPlus, $phone_normalized, $phone_national, $phone_normalized_like_fallback) {
//                        $subQuery->where('value',  '=',  $phoneE164WithoutPlus) // Strict match for E.164
//                        ->orWhere('value', 'LIKE', '%' . $phone_normalized . '%')    // Relaxed match for normalized
//                        ->orWhere('value', 'LIKE', '%' . $phone_national . '%')     // Relaxed match for national
//                        ->orWhere('value', 'LIKE', '%' . $phone_normalized_like_fallback . '%'); // Fallback with leading "0"s removed
//                    });
//                dd($query->first());
//            } else {
//                // Default matching behavior for non-mobile channels
//                $query->where('name', $channelName)->where('value', $channelValue);
//            }
//        })->first();
//    }

    public static function __callStatic($method, $parameters)
    {
        // Check if the method starts with "findBy"
        if (str_starts_with($method, 'findBy')) {
            // Dynamically determine the channel name
            $channelName = strtolower(str_replace('findBy', '', $method));

            // Ensure there are at least two parameters: the channel value
            if (!isset($parameters[0])) {
                throw new \InvalidArgumentException('Channel value is required');
            }
            $channelValue = $parameters[0];

            // Use the existing findByChannel method to find the model
            return static::findByChannel($channelName, $channelValue);
        }

        // If the method does not match "findBy", pass it to the parent (if necessary)
        return parent::__callStatic($method, $parameters);
    }
}
