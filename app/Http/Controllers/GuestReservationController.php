<?php

namespace App\Http\Controllers;

use App\Services\Reservation\ReservationBookingPageData;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GuestReservationController extends Controller
{
    public function __invoke(Request $request, ReservationBookingPageData $pageData): View
    {
        return view('guest.reserve', $pageData->forGuest($request));
    }
}
