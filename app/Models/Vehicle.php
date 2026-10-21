<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class Vehicle extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'vehicles';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'year',
        'make',
        'model',
        'submodel',
        'engine',
        'transmission',
        'drivetrain',
        'fuel_type',
        'nhtsa_make_id',
        'nhtsa_model_id',
        'spec_payload',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'nhtsa_make_id' => 'integer',
            'nhtsa_model_id' => 'integer',
            'spec_payload' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
