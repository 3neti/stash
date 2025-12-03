<?php

namespace LBHurtado\Contact\Traits;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Propaganistas\LaravelPhone\PhoneNumber;

trait HasMobile
{
    protected function mobile(): Attribute
    {
        $default_country = config('contact.default.country');

        return Attribute::make(
            get: function ($value, $attributes) use ($default_country) {
                $country = $attributes['country'] ?? $default_country;

                return phone($value, $country)->formatForMobileDialingInCountry($country);
            },
            set: function ($value, $attributes) use ($default_country) {
                $country = $attributes['country'] ?? $default_country;

                return phone($value, $country)->formatForMobileDialingInCountry($country);
            }
        );
    }

    static public function fromPhoneNumber(PhoneNumber $phoneNumber): ?static
    {
        $mobile = $phoneNumber->formatForMobileDialingInCountry($phoneNumber->getCountry());
        $country = $phoneNumber->getCountry();

        return static::firstOrCreate(compact('mobile', 'country'));
    }
}
