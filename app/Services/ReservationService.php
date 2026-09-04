<?php

namespace App\Services;

use App\Exceptions\DuplicateReservationException;
use App\Exceptions\OfferNotBookableException;
use App\Models\Offer;
use App\Models\Reservation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class ReservationService
{
    /** @param array<string, mixed> $data */
    public function reserve(Offer $offer, array $data): Reservation
    {
        try {
            return DB::transaction(function () use ($offer, $data): Reservation {
                $locked = Offer::query()->whereKey($offer->getKey())->lockForUpdate()->firstOrFail();

                if ($locked->expires_at->isPast()) {
                    throw new OfferNotBookableException('This offer has expired.');
                }

                if ($locked->available_units < 1) {
                    throw new OfferNotBookableException('This offer has no available units left.');
                }

                $locked->decrement('available_units');

                return $locked->reservations()->create([
                    'client_reference' => $data['client_reference'],
                    'customer_name' => $data['customer_name'],
                    'customer_email' => $data['customer_email'],
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            throw new DuplicateReservationException('A reservation with this client reference already exists.');
        }
    }
}
