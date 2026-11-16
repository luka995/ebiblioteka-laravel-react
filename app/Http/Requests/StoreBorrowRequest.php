<?php

namespace App\Http\Requests;

use App\Support\BarCode;
use Illuminate\Foundation\Http\FormRequest;

class StoreBorrowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        if ($this->filled('barcode')) {
            $data['barcode'] = BarCode::normalizeScanInput((string) $this->input('barcode'));
        }

        if ($this->filled('user_barcode')) {
            $data['user_barcode'] = BarCode::normalizeScanInput((string) $this->input('user_barcode'));
        }

        if ($this->filled('username')) {
            $data['username'] = trim((string) $this->input('username'));
        }

        $this->merge($data);
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', 'exists:users,id', 'required_without_all:user_barcode,username'],
            'user_barcode' => ['nullable', 'string', 'max:32', 'required_without_all:user_id,username'],
            'username' => ['nullable', 'string', 'max:255', 'required_without_all:user_id,user_barcode'],

            'book_copy_id' => ['nullable', 'integer', 'exists:book_copies,id', 'required_without:barcode'],
            'barcode' => ['nullable', 'string', 'max:32', 'required_without:book_copy_id'],

            'valid_till' => ['nullable', 'date', 'after_or_equal:today'],
            'confirm_reservation_exchange' => ['sometimes', 'boolean'],
        ];
    }
}
