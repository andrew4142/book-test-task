<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'edition' => 'nullable|string|max:255',
            'publisher' => 'nullable|string|max:255',
            'year' => 'nullable|date',
            'format' => 'nullable|string|max:255',
            'pages' => 'nullable|integer|min:1',
            'country' => 'nullable|string|max:255',
            'isbn' => 'required|string|unique:books,isbn',
            'author_ids' => 'nullable|array',
            'author_ids.*' => 'integer|exists:authors,id',
            'genre_ids' => 'nullable|array',
            'genre_ids.*' => 'integer|exists:genres,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Title is required.',
            'isbn.required' => 'ISBN is required.',
            'isbn.unique' => 'Book with this ISBN already exists.',
            'pages.min' => 'Pages must be at least 1.',
        ];
    }
}
