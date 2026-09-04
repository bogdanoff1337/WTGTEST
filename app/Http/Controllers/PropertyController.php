<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchPropertiesRequest;
use App\Http\Resources\PropertyCollection;
use App\Services\PropertySearchService;

class PropertyController extends Controller
{
    public function index(SearchPropertiesRequest $request, PropertySearchService $service): PropertyCollection
    {
        return new PropertyCollection($service->search($request->validated()));
    }
}
