<?php

namespace App\Http\Controllers;

use App\Support\PublicTheme;
use Illuminate\View\View;

class PublicProjectController extends Controller
{
    public function __invoke(): View
    {
        return view(PublicTheme::view('public.project'));
    }
}
