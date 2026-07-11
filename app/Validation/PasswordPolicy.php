<?php

declare(strict_types=1);

namespace App\Validation;

final class PasswordPolicy
{
    public const MIN_LENGTH = 12;

    public static function isValid(string $password): bool
    {
        return mb_strlen($password) >= self::MIN_LENGTH;
    }

    public static function message(): string
    {
        return 'Password must be at least ' . self::MIN_LENGTH . ' characters.';
    }
}
