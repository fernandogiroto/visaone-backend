<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProcessCatalog;

class CatalogController extends Controller
{
    public function __invoke(ProcessCatalog $catalog)
    {
        return response()->json([
            'visa_types' => $catalog->visaTypes(),
            'countries' => $catalog->countries(),
        ]);
    }
}
