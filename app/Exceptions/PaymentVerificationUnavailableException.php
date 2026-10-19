<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class PaymentVerificationUnavailableException extends HttpException
{
    public function __construct(string $message = 'Payment verification is temporarily unavailable. Please retry.')
    {
        parent::__construct(503, $message);
    }
}