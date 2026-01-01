<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class BookCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection->map(function ($book) {
                return [
                    'title' => $book->title,
                    'authors' => $book->authors->pluck('name')->toArray(),
                    'publisher' => $book->publisher,
                    'year' => $book->year,
                ];
            }),
        ];
    }
}
