<?php

declare(strict_types=1);

namespace LBHurtado\ModelChannel\Tests\Models;

use LBHurtado\ModelChannel\Database\Factories\UserFactory;
use LBHurtado\ModelChannel\Contracts\ChannelsInterface;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use LBHurtado\ModelChannel\Traits\HasChannels;
use Illuminate\Notifications\Notifiable;

/**
 * Class User.
 *
 * @property int        $id
 * @property string     $name
 * @property string     $email
 * @property Merchant   $merchant
 *
 * @method int getKey()
 */
class User extends Authenticatable implements ChannelsInterface
{
    use HasChannels;
    use HasFactory;
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'mobile'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    public static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
