<?php

namespace LBHurtado\Contact\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use LBHurtado\Contact\Models\Contact;

class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition(): array
    {
        return [
            'mobile' => $this->faker->numerify('0917#######'),
            'country' => 'PH',
        ];
    }
}
