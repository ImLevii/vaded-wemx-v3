<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Http;

class Marketplace
{
    const API_DOMAIN = 'https://localhost:8000';

    public function getResources($sort_by = 'latest', $category_id = null, $search = null)
    {
        $response = Http::timeout(8)->get(self::API_DOMAIN.'/api/v1/marketplace/resources', [
            'sort_by' => $sort_by,
            'category_id' => $category_id,
            'search' => $search,
        ]);

        if ($response->successful()) {
            return $response->json();
        }

        return [];
    }
}
