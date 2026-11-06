<?php

namespace App\Services\Import;

use RuntimeException;

/** The uploaded file cannot be read in the chosen bank format (message is shown to the user). */
class InvalidStatementException extends RuntimeException {}
