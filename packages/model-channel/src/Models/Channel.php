<?php

namespace LBHurtado\ModelChannel\Models;

use LBHurtado\ModelChannel\Database\Factories\ChannelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Channel extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'value'
    ];

    public static function newFactory(): ChannelFactory
    {
        return ChannelFactory::new();
    }
}
