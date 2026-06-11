<?php

declare(strict_types=1);

namespace App\Model;



/**
 */
class LtvStatsLog extends Model
{
    public bool $timestamps = true;
    /**
     * The table associated with the model.
     */
    protected ?string $table = 'ltv_stats_log';

    /**
     * The attributes that are mass assignable.
     */
    protected array $fillable = [
        'stat_date',
        'start_time',
        'end_time',
        'status',
        'processed_count',
        'error_message'
    ];

    /**
     * The attributes that should be cast to native types.
     */
    protected array $casts = [
        'stat_date' => 'date',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'processed_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
}
