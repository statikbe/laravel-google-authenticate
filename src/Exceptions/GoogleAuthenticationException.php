<?php

namespace Statikbe\GoogleAuthenticate\Exceptions;

use Throwable;

class GoogleAuthenticationException extends \Exception {

    public function __construct(string $message = '', int $code = 403, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
