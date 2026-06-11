<?php

declare(strict_types=1);

namespace App\Model;

use Hyperf\DbConnection\Model\Model;

/**
 * @property int $id
 * @property string $room_name
 * @property int $owner_id
 * @property int $max_players
 * @property int $current_players
 * @property string $password
 * @property int $status
 * @property string $game_mode
 * @property int $created_time
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Room extends Model
{
    const STATUS_ACTIVE = 1;
    const STATUS_CLOSED = 2;
    const STATUS_FULL = 3;

    protected ?string $table = 'rooms';

    protected array $fillable = [
        'room_name',
        'owner_id',
        'max_players',
        'current_players',
        'password',
        'status',
        'game_mode',
        'created_time'
    ];

    protected array $casts = [
        'id' => 'integer',
        'owner_id' => 'integer',
        'max_players' => 'integer',
        'current_players' => 'integer',
        'status' => 'integer',
        'created_time' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
}
