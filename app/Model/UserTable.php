<?php

declare(strict_types=1);

namespace App\Model;



/**
 */
class UserTable extends Model
{
    public bool $timestamps = true;
    protected string $primaryKey = 'uid';
    /**
     * The table associated with the model.
     */
    protected ?string $table = 'user_table';

    /**
     * The attributes that are mass assignable.
     */
    protected array $fillable = [
        'uid',
        'appId',
        'promoterId',
        'channelId',
        'registerTime',
        'bankCard',
        'currCnt'
    ];

    /**
     * The attributes that should be cast to native types.
     */
    protected array $casts = [
        'registerTime' => 'datetime',
        'currCnt' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
}
