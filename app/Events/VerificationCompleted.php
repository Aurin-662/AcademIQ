<?php

namespace App\Events;

class VerificationCompleted
{
    public function __construct(public string $requestId) {}
}
