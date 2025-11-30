<?php

namespace LBHurtado\ModelChannel\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\ModelChannel\Database\Factories\ChannelFactory;

class Channel extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'value',
    ];

    public static function newFactory(): ChannelFactory
    {
        return ChannelFactory::new();
    }
}
