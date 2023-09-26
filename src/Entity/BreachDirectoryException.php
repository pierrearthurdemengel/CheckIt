<?php

namespace App\Exception;

use Exception;

class BreachDirectoryException extends Exception
{
    public function __construct(string $message, int $code = 500)
    {
        parent::__construct($message, $code);
    }
}
