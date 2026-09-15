<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Public self-service household creation (RegistrationController) is
 * gated behind REGISTRATION_ENABLED — and defaults CLOSED when unset,
 * the opposite of Turnstile/Mailer's "degrade gracefully if
 * unconfigured" pattern. Those features fail safe by doing less (no bot
 * check, no email sent); registration failing "safe" the same way would
 * mean strangers can create new households on this server the moment
 * this one line of .env is left blank, which is exactly the outcome
 * this flag exists to prevent by default. Set REGISTRATION_ENABLED=true
 * in .env to reopen it.
 */
final class PublicRegistration
{
    public static function isOpen(): bool
    {
        return filter_var($_ENV['REGISTRATION_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }
}
