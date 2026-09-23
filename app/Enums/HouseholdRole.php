<?php

namespace App\Enums;

enum HouseholdRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';
    case Advisor = 'advisor';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * @return array<HouseholdPermission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Owner => HouseholdPermission::cases(),
            self::Admin => [
                HouseholdPermission::UpdateHousehold,
                HouseholdPermission::CreateInvitation,
                HouseholdPermission::CancelInvitation,
            ],
            self::Member => [],
            self::Advisor => [],
        };
    }

    /**
     * An advisor is a least-privilege, read-only collaborator.
     */
    public function isReadOnly(): bool
    {
        return $this === self::Advisor;
    }

    public function hasPermission(HouseholdPermission $permission): bool
    {
        return in_array($permission, $this->permissions());
    }

    public function level(): int
    {
        return match ($this) {
            self::Owner => 3,
            self::Admin => 2,
            self::Member => 1,
            self::Advisor => 0,
        };
    }

    public function isAtLeast(HouseholdRole $role): bool
    {
        return $this->level() >= $role->level();
    }

    /**
     * @return array<array{value: string, label: string}>
     */
    public static function assignable(): array
    {
        return collect(self::cases())
            ->filter(fn (self $role) => $role !== self::Owner)
            ->map(fn (self $role) => ['value' => $role->value, 'label' => $role->label()])
            ->values()
            ->toArray();
    }
}
