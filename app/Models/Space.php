<?php

namespace App\Models;

use App\Enums\SpaceField;
use App\Enums\SpaceTheme;
use App\Services\SpaceNameNormalizer;
use Database\Factories\SpaceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'name',
    'slug',
    'title',
    'subtitle',
    'request',
    'theme',
    'rating_enabled',
    'company_name_enabled',
    'company_name_required',
    'social_url_enabled',
    'social_url_required',
    'photo_enabled',
    'photo_required',
    'field_order',
])]
class Space extends Model
{
    /** @use HasFactory<SpaceFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'theme' => SpaceTheme::class,
            'rating_enabled' => 'boolean',
            'company_name_enabled' => 'boolean',
            'company_name_required' => 'boolean',
            'social_url_enabled' => 'boolean',
            'social_url_required' => 'boolean',
            'photo_enabled' => 'boolean',
            'photo_required' => 'boolean',
            'field_order' => 'array',
        ];
    }

    /**
     * The normalised name is derived, never accepted from the browser.
     */
    protected static function booted(): void
    {
        static::saving(function (self $space): void {
            $space->name_key = SpaceNameNormalizer::key((string) $space->name);
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Every testimonial submitted to this space.
     *
     * @return HasMany<Testimonial, $this>
     */
    public function testimonials(): HasMany
    {
        return $this->hasMany(Testimonial::class);
    }

    /**
     * Every widget that shows this space's testimonials.
     *
     * @return HasMany<Widget, $this>
     */
    public function widgets(): HasMany
    {
        return $this->hasMany(Widget::class);
    }

    /**
     * The public collection path for this space.
     */
    public function publicPath(): string
    {
        return '/s/'.$this->slug;
    }

    /**
     * The public Wall of Love path for this space.
     */
    public function wallPath(): string
    {
        return '/wall/'.$this->slug;
    }

    /**
     * The order the fields are shown in.
     *
     * @return array<int, string>
     */
    public function fieldOrder(): array
    {
        return $this->field_order ?: SpaceField::defaultOrder();
    }

    /**
     * Whether this space collects the given field.
     */
    public function isFieldEnabled(SpaceField $field): bool
    {
        return match ($field) {
            SpaceField::Name, SpaceField::Email => true,
            SpaceField::CompanyName => (bool) $this->company_name_enabled,
            SpaceField::SocialUrl => (bool) $this->social_url_enabled,
            SpaceField::Photo => (bool) $this->photo_enabled,
        };
    }

    /**
     * Whether the given field must be filled in.
     */
    public function isFieldRequired(SpaceField $field): bool
    {
        return match ($field) {
            SpaceField::Name, SpaceField::Email => true,
            SpaceField::CompanyName => (bool) $this->company_name_required,
            SpaceField::SocialUrl => (bool) $this->social_url_required,
            SpaceField::Photo => (bool) $this->photo_required,
        };
    }
}
