<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DuplicateReservationException extends Exception
{
    public function render(Request $request): JsonResponse
    {
        return response()->json(
            ['message' => $this->getMessage()],
            Response::HTTP_CONFLICT,
        );
    }
}
