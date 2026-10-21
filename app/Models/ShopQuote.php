<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QuoteStatus;
use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ShopQuote extends Model
{
    use BelongsToShop;
    use HasFactory;
    use HasUuids;

    protected $table = 'shop_quotes';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'shop_id',
        'estimate_request_id',
        'customer_id',
        'vehicle_id',
        'service_id',
        'status',
        'customer_name',
        'customer_email',
        'customer_phone',
        'customer_issue_notes',
        'labor_subtotal',
        'parts_subtotal',
        'shop_fees',
        'tax_estimate',
        'total_amount',
        'scope_notes',
        'revision_number',
        'valid_until',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => QuoteStatus::class,
            'labor_subtotal' => 'decimal:2',
            'parts_subtotal' => 'decimal:2',
            'shop_fees' => 'decimal:2',
            'tax_estimate' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'revision_number' => 'integer',
            'valid_until' => 'date',
        ];
    }

    /**
     * @return BelongsTo<EstimateRequest, $this>
     */
    public function estimateRequest(): BelongsTo
    {
        return $this->belongsTo(EstimateRequest::class, 'estimate_request_id');
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
}
