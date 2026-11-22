<?php

namespace App\Enums;

enum SpaceField: string
{
    case Name = 'name';
    case Email = 'email';
    case CompanyName = 'company_name';
    case SocialUrl = 'social_url';
    case Photo = 'photo';

    public function label(): string
    {
        return match ($this) {
            self::Name => 'Name',
            self::Email => 'Email',
            self::CompanyName => 'Company name',
            self::SocialUrl => 'Social profile URL',
            self::Photo => 'Profile photo',
        };
    }

    /**
     * Name and Email are always collected, whatever the owner configures.
     */
    public function isAlwaysEnabled(): bool
    {
        return $this === self::Name || $this === self::Email;
    }

    /**
     * Name and Email are always required, whatever the owner configures.
     */
    public function isAlwaysRequired(): bool
    {
        return $this === self::Name || $this === self::Email;
    }

    /**
     * The remaining fields can be enabled or disabled by the owner.
     */
    public function isConfigurable(): bool
    {
        return ! $this->isAlwaysEnabled();
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return array<int, string>
     */
    public static function configurableValues(): array
    {
        return array_values(array_map(
            fn (self $field): string => $field->value,
            array_filter(self::cases(), fn (self $field): bool => $field->isConfigurable()),
        ));
    }

    /**
     * The canonical ordering used when the owner has not chosen one.
     *
     * @return array<int, string>
     */
    public static function defaultOrder(): array
    {
        return [self::Name->value, self::Email->value, ...self::configurableValues()];
    }
}
