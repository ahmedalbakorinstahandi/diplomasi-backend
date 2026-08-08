<?php

namespace App\Http\Services\AiNegotiator\StateMachine;

class SessionStateException extends \RuntimeException
{
    public static function invalidTransition(string $from, string $to): self
    {
        return new self("invalid_transition:{$from}->{$to}");
    }

    public static function sessionAlreadyTerminal(string $state): self
    {
        return new self("session_already_terminal:{$state}");
    }
}
