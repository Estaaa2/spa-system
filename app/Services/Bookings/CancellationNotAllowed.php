<?php

namespace App\Services\Bookings;

use RuntimeException;

/** A customer cancel that the rule refuses. The message is shown to the customer. */
class CancellationNotAllowed extends RuntimeException
{
}
