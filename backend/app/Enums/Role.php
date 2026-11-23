<?php

namespace App\Enums;

enum Role: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';

    public function canManage(): bool
    {
        return $this === self::Owner || $this === self::Admin;
    }

    /** Roles that can be assigned through invitations / role changes. */
    public static function assignable(): array
    {
        return [self::Admin->value, self::Member->value];
    }
}
