<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class EstimateRequest extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'estimate_requests';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'vehicle_id',
        'service_id',
        'zip_code',
        'issue_description',
        'is_supported_estimate',
        'source_version',
        'participating_shops_count',
        'estimated_labor_hours',
        'estimated_labor_low',
        'estimated_labor_high',
        'estimated_parts_low',
        'estimated_parts_high',
        'estimated_fees',
        'estimated_total_low',
        'estimated_total_high',
        'assumptions_payload',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_supported_estimate' => 'boolean',
            'participating_shops_count' => 'integer',
            'estimated_labor_hours' => 'decimal:2',
            'estimated_labor_low' => 'decimal:2',
            'estimated_labor_high' => 'decimal:2',
            'estimated_parts_low' => 'decimal:2',
            'estimated_parts_high' => 'decimal:2',
            'estimated_fees' => 'decimal:2',
            'estimated_total_low' => 'decimal:2',
            'estimated_total_high' => 'decimal:2',
            'assumptions_payload' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    /**
     * @return HasMany<ShopQuote, $this>
     */
    public function shopQuotes(): HasMany
    {
        return $this->hasMany(ShopQuote::class, 'estimate_request_id');
    }
}
