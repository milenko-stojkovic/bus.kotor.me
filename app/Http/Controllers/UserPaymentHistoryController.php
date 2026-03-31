<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class UserPaymentHistoryController extends Controller
{
    public function __invoke(Request $request): View
    {
        // Minimal placeholder: real payment history can be derived from reservations later.
        return view('profile.payments.index');
    }
}

