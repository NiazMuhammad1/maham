<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CityResource;
use App\Http\Resources\DistrictResource;
use App\Models\City;
use Illuminate\Http\JsonResponse;

/**
 * "Select City" / "Select District" dropdowns on the home address screen.
 */
class CityController extends Controller
{
    public function index(): JsonResponse
    {
        $nameColumn = app()->getLocale() === 'ar' ? 'name_ar' : 'name_en';

        $cities = City::active()->orderBy('sort_order')->orderBy($nameColumn)->get();

        return $this->success(CityResource::collection($cities));
    }

    public function districts(City $city): JsonResponse
    {
        abort_unless($city->is_active, 404);

        $nameColumn = app()->getLocale() === 'ar' ? 'name_ar' : 'name_en';

        $districts = $city->districts()->active()->orderBy('sort_order')->orderBy($nameColumn)->get();

        return $this->success(DistrictResource::collection($districts));
    }
}
