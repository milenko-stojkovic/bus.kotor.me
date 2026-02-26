<?php

namespace App\Exceptions;

use Exception;

/** Baca se u checkout transakciji kada nema slobodnog kapaciteta za slot (race condition). */
class NoCapacityException extends Exception
{
}
