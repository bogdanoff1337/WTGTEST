<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreImportRequest;
use App\Http\Resources\ImportResource;
use App\Models\Import;
use App\Services\ImportService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ImportController extends Controller
{
    public function store(StoreImportRequest $request, ImportService $service): JsonResponse
    {
        $import = $service->create($request->validated());

        return ImportResource::make($import->load('supplier'))
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function show(Import $import): ImportResource
    {
        return ImportResource::make($import->load('supplier'));
    }
}
