<?php

namespace App\Models;

use Database\Factories\OfferFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Offer extends Model
{
    /** @use HasFactory<OfferFactory> */
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'property_id',
        'import_id',
        'external_id',
        'check_in',
        'check_out',
        'max_guests',
        'price',
        'currency',
        'total_units',
        'available_units',
        'expires_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'expires_at' => 'datetime',
        'max_guests' => 'integer',
        'price' => 'integer',
        'total_units' => 'integer',
        'available_units' => 'integer',
    ];

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<Property, $this> */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /** @return BelongsTo<Import, $this> */
    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }

    /** @return HasMany<Reservation, $this> */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /** @param Builder<Offer> $query */
    public function scopeBookable(Builder $query, string $checkIn, string $checkOut, int $guests): void
    {
        $query->where('offers.check_in', $checkIn)
            ->where('offers.check_out', $checkOut)
            ->where('offers.max_guests', '>=', $guests)
            ->where('offers.available_units', '>', 0)
            ->where('offers.expires_at', '>', now());
    }
}
