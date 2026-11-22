<?php

namespace App\Models;

use App\Enums\PlanName;
use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'label',
    'price_monthly_cents',
    'stripe_price_id',
    'max_spaces',
    'max_testimonials_per_space',
    'features',
])]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'name' => PlanName::class,
            'price_monthly_cents' => 'integer',
            'max_spaces' => 'integer',
            'max_testimonials_per_space' => 'integer',
            'features' => 'array',
        ];
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function isFree(): bool
    {
        return $this->name === PlanName::Free;
    }

    public function isPro(): bool
    {
        return $this->name === PlanName::Pro;
    }

    public function priceMonthly(): string
    {
        return number_format($this->price_monthly_cents / 100, 2);
    }

    /**
     * The Stripe Price ID this plan is billed against.
     *
     * This is the only authoritative source for matching a Stripe
     * subscription to this plan. It is null until an environment-specific
     * Price ID has been configured.
     */
    public function stripePriceId(): ?string
    {
        $priceId = $this->stripe_price_id;

        return is_string($priceId) && trim($priceId) !== '' ? trim($priceId) : null;
    }
}
