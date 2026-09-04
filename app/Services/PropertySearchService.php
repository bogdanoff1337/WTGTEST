<?php

namespace App\Services;

use App\Models\Offer;
use App\Models\Property;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class PropertySearchService
{
    /**
     * @param  array<string, mixed>  $criteria
     * @return LengthAwarePaginator<int, Property>
     */
    public function search(array $criteria): LengthAwarePaginator
    {
        $checkIn = CarbonImmutable::parse($criteria['check_in'])->toDateString();
        $checkOut = CarbonImmutable::parse($criteria['check_out'])->toDateString();
        $guests = (int) $criteria['guests'];

        return Property::query()
            ->when($criteria['city'] ?? null, fn (Builder $query, string $city) => $query->where('city', $city))
            ->whereExists($this->bookableOffers($checkIn, $checkOut, $guests))
            ->addSelect([
                'best_offer_id' => $this->cheapestOffer('offers.id', $checkIn, $checkOut, $guests),
                'best_offer_price' => $this->cheapestOffer('offers.price', $checkIn, $checkOut, $guests),
            ])
            ->with('bestOffer.supplier')
            ->orderBy('best_offer_price')
            ->orderBy('properties.id')
            ->paginate($criteria['per_page'] ?? 15)
            ->withQueryString();
    }

    /** @return Builder<Offer> */
    private function bookableOffers(string $checkIn, string $checkOut, int $guests): Builder
    {
        return Offer::query()
            ->bookable($checkIn, $checkOut, $guests)
            ->whereColumn('offers.property_id', 'properties.id');
    }

    /** @return Builder<Offer> */
    private function cheapestOffer(string $column, string $checkIn, string $checkOut, int $guests): Builder
    {
        return $this->bookableOffers($checkIn, $checkOut, $guests)
            ->select($column)
            ->orderBy('offers.price')
            ->orderBy('offers.id')
            ->limit(1);
    }
}
