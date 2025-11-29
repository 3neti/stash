<?php

namespace LBHurtado\ModelChannel\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use LBHurtado\ModelChannel\Enums\Channel;

interface ChannelsInterface
{
    public function channels(): MorphMany;
    public function setChannel(string|Channel $name, string $value): self;
    public function forceSetChannel(string|Channel $name, string $value): self;
    public function isValidChannel(string|Channel $name, ?string $value = null): bool;

}
