<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class TransportResourceCollection extends ResourceCollection
{
    public $collects = TransportResource::class;

    public function toArray($request): array
    {
        return [
            'data' => $this->collection,
        ];
    }

    public function with($request): array
    {
        return [
            'meta' => [
                'current_page' => $this->currentPage(),
                'from' => $this->firstItem(),
                'to' => $this->lastItem(),
                'per_page' => $this->perPage(),
                'last_page' => $this->lastPage(),
                'total' => $this->total(),
            ]
        ];
    }
}
