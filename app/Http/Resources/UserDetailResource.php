<?php

namespace App\Http\Resources;

use App\Support\BarCode;
use App\Support\BarCodeImage;
use Illuminate\Http\Request;

class UserDetailResource extends UserResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);

        $data['bar_code_svg'] = $this->bar_code !== null && BarCode::validate($this->bar_code)
            ? BarCodeImage::render($this->bar_code)
            : null;

        return $data;
    }
}
