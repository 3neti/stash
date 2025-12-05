<?php

namespace LBHurtado\ModelChannel\Models;

use LBHurtado\ModelChannel\Database\Factories\ChannelFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Channel extends Model
{
    use HasFactory, HasUlids;

    protected $connection = 'tenant';

    protected $fillable = [
        'name',
        'value',
        'tenant_id'
    ];

    public static function newFactory(): ChannelFactory
    {
        return ChannelFactory::new();
    }
}
