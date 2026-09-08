<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class PublicProjectController extends Controller
{
    public function __invoke(): View
    {
        return view('public.project');
    }
}
