<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\PlanName;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * The plan this account is entitled to.
     *
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return HasMany<Space, $this>
     */
    public function spaces(): HasMany
    {
        return $this->hasMany(Space::class);
    }

    /**
     * Every new account starts on the Free plan.
     *
     * The plan is assigned here rather than through mass assignment so an
     * account can never set its own plan.
     */
    protected static function booted(): void
    {
        static::creating(function (self $user): void {
            if ($user->plan_id === null) {
                $user->plan_id = Plan::query()
                    ->where('name', PlanName::Free)
                    ->value('id');
            }
        });
    }

    public function onFreePlan(): bool
    {
        return $this->plan?->isFree() ?? true;
    }

    public function onProPlan(): bool
    {
        return $this->plan?->isPro() ?? false;
    }

    /**
     * Maximum number of spaces allowed for this account.
     */
    public function spacesLimit(): int
    {
        return $this->plan?->max_spaces ?? 0;
    }

    /**
     * Maximum number of testimonials allowed per space for this account.
     */
    public function testimonialsLimit(): int
    {
        return $this->plan?->max_testimonials_per_space ?? 0;
    }
}
