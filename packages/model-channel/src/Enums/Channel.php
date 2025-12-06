<?php

namespace LBHurtado\ModelChannel\Enums;

use Illuminate\Support\Facades\Config;

enum Channel: string
{
    case MOBILE = 'mobile';
    // case EMAIL = 'email'; // Removed: Conflicts with User model's email attribute
    case WEBHOOK = 'webhook';
    case SLACK = 'slack';

    public function rules(): array
    {
        // Dynamically retrieve rules from the configuration file
        $rules = Config::get('model-channel.rules.' . $this->value);

        // Throw an exception if no rule is defined for the channel
        if (is_null($rules)) {
            throw new \RuntimeException("Validation rules are not defined for the [{$this->value}] channel.");
        }

        return $rules;
    }
}
