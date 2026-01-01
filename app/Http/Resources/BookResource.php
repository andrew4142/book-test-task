<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'edition' => $this->edition,
            'publisher' => $this->publisher,
            'year' => $this->year,
            'format' => $this->format,
            'pages' => $this->pages,
            'country' => $this->country,
            'isbn' => $this->isbn,
            'authors' => AuthorResource::collection($this->whenLoaded('authors')),
            'genres' => GenreResource::collection($this->whenLoaded('genres')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
