<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Izabrani termini više nemaju kapacitet ili su blokirani (provera pod lock-om u admin free toku).
 */
final class AdminFreeReservationSlotsUnavailableException extends RuntimeException
{
}
