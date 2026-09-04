<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReservationRequest;
use App\Http\Resources\ReservationResource;
use App\Models\Offer;
use App\Services\ReservationService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ReservationController extends Controller
{
    public function store(StoreReservationRequest $request, Offer $offer, ReservationService $service): JsonResponse
    {
        $reservation = $service->reserve($offer, $request->validated());

        return ReservationResource::make($reservation->load('offer.supplier'))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
