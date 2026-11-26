<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

class StoreBookImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'extensions:xlsx', 'mimes:xlsx', 'max:'.config('book_imports.max_file_size_kb')],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $file = $this->file('file');

            if ($file instanceof UploadedFile && mb_strlen($file->getClientOriginalName()) > 255) {
                $validator->errors()->add('file', __('book_imports.errors.filename_too_long'));
            }
        }];
    }
}
