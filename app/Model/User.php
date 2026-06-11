<?php

declare(strict_types=1);

namespace App\Model;

use Hyperf\DbConnection\Model\Model;

/**
 * @property int $id
 * @property string $username
 * @property string $password
 * @property string $nickname
 * @property string $email
 * @property int $level
 * @property int $exp
 * @property int $coins
 * @property int $vip_level
 * @property string $unlocked_avatars
 * @property int $status
 * @property int $last_login_time
 * @property string $last_login_ip
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class User extends Model
{
    const STATUS_ACTIVE = 1;
    const STATUS_LOCKED = 2;

    protected ?string $table = 'users';

    protected array $fillable = [
        'username',
        'password',
        'nickname',
        'email',
        'level',
        'exp',
        'coins',
        'vip_level',
        'unlocked_avatars',
        'status',
        'last_login_time',
        'last_login_ip'
    ];

    protected array $casts = [
        'id' => 'integer',
        'level' => 'integer',
        'exp' => 'integer',
        'coins' => 'integer',
        'vip_level' => 'integer',
        'status' => 'integer',
        'last_login_time' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
}
