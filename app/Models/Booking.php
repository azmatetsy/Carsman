<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BookingStatus;
use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Booking extends Model
{
    use BelongsToShop;
    use HasFactory;
    use HasUuids;

    protected $table = 'bookings';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'shop_id',
        'customer_id',
        'vehicle_id',
        'service_id',
        'shop_quote_id',
        'idempotency_key',
        'status',
        'preferred_date',
        'preferred_time_slot',
        'scheduled_at',
        'alternate_proposed_at',
        'customer_name',
        'customer_email',
        'customer_phone',
        'issue_description',
        'shop_notes',
        'cancellation_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'preferred_date' => 'date',
            'scheduled_at' => 'datetime',
            'alternate_proposed_at' => 'datetime',
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
     * @return BelongsTo<ShopQuote, $this>
     */
    public function shopQuote(): BelongsTo
    {
        return $this->belongsTo(ShopQuote::class, 'shop_quote_id');
    }

    /**
     * @return HasMany<BookingStatusEvent, $this>
     */
    public function statusEvents(): HasMany
    {
        return $this->hasMany(BookingStatusEvent::class, 'booking_id');
    }
}
