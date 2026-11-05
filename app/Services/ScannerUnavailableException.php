<?php

namespace App\Services;

use RuntimeException;

/**
 * Thrown when the OCR tools (Tesseract, Poppler) are missing or cannot be
 * started on this server. The scanner turns it into the "unavailable"
 * status so the administrator sees "Scanner offline" instead of an error
 * that looks like a problem with the owner's file.
 */
class ScannerUnavailableException extends RuntimeException
{
}
