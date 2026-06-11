<?php

declare(strict_types=1);

namespace App\Model;



/**
 */
class Ltv90dStat extends Model
{

    public bool $timestamps = true;
    /**
     * The table associated with the model.
     */
    protected ?string $table = 'ltv_90d_stats';

    /**
     * The attributes that are mass assignable.
     */
    protected array $fillable = [
        'stat_date',
        'register_date',
        'promoter_id',
        'channel_id',
        'register_count',
        'day1_ltv',
        'day3_ltv',
        'day7_ltv',
        'day30_ltv',
        'day60_ltv',
        'day90_ltv',
        'total_revenue',
        'avg_ltv'
    ];

    /**
     * The attributes that should be cast to native types.
     */
    protected array $casts = [
        'stat_date' => 'date',
        'register_date' => 'date',
        'register_count' => 'integer',
        'day1_ltv' => 'float',
        'day3_ltv' => 'float',
        'day7_ltv' => 'float',
        'day30_ltv' => 'float',
        'day60_ltv' => 'float',
        'day90_ltv' => 'float',
        'total_revenue' => 'float',
        'avg_ltv' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
}
