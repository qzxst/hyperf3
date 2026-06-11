<?php

declare(strict_types=1);

namespace App\Model;



/**
 */
class OrderTable extends Model
{
    public bool $timestamps = true;
    protected string $primaryKey = 'providerOrderId';
    /**
     * The table associated with the model.
     */
    protected ?string $table = 'order_table';

    /**
     * The attributes that are mass assignable.
     */
    protected array $fillable = [
        'providerOrderId',
        'appId',
        'uid',
        'channelId',
        'gid',
        'price',
        'discount',
        'unit',
        'type',
        'num',
        'createTime',
        'sendTime',
        'refundTime',
        'status',
        'try',
        'remark',
        'extInfo',
        'channelFee'
    ];

    /**
     * The attributes that should be cast to native types.
     */
    protected array $casts = [
        'price' => 'float',
        'discount' => 'float',
        'type' => 'integer',
        'num' => 'integer',
        'createTime' => 'datetime',
        'sendTime' => 'datetime',
        'refundTime' => 'datetime',
        'try' => 'integer',
        'extInfo' => 'array',
        'channelFee' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
}
