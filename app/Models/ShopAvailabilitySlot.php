<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class ShopAvailabilitySlot extends Model
{
    use BelongsToShop;
    use HasFactory;
    use HasUuids;

    protected $table = 'shop_availability_slots';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'shop_id',
        'day_of_week',
        'opens_at',
        'closes_at',
        'max_concurrent_bookings',
        'is_closed',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'max_concurrent_bookings' => 'integer',
            'is_closed' => 'boolean',
        ];
    }
}
