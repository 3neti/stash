<?php

namespace LBHurtado\ModelChannel\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use LBHurtado\ModelChannel\Models\Channel;

class ChannelFactory extends Factory
{
    protected $model = Channel::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'value' => $this->faker->phoneNumber(),
        ];
    }
}
