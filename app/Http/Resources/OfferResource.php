<?php

namespace App\Http\Resources;

use App\Models\Offer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Offer */
class OfferResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'supplier' => $this->supplier->code,
            'check_in' => $this->check_in,
            'check_out' => $this->check_out,
            'max_guests' => $this->max_guests,
            'price' => $this->price,
            'currency' => $this->currency,
            'available_units' => $this->available_units,
            'expires_at' => $this->expires_at->toIso8601String(),
        ];
    }
}
