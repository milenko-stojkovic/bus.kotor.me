<?php

namespace App\Http\Controllers\Limo;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

class LimoEntryController extends Controller
{
    public function entry(): View
    {
        return view('limo.entry');
    }

    public function health(): JsonResponse
    {
        return response()->json(['status' => 'ok', 'scope' => 'limo']);
    }
}
