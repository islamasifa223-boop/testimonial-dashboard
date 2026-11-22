<?php

namespace App\Models;

use App\Enums\TestimonialVisibility;
use Database\Factories\TestimonialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single customer testimonial submitted to a space.
 *
 * Only the customer supplied fields are fillable. The moderation flags
 * (consented, approved, hidden, favorite, hearted) and space_id are never
 * mass assignable: they are set by trusted server side code through direct
 * attribute assignment, or by the relation, or by the factory.
 */
#[Fillable([
    'name',
    'email',
    'company_name',
    'social_url',
    'photo_path',
    'body',
    'rating',
])]
class Testimonial extends Model
{
    /** @use HasFactory<TestimonialFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'consented' => 'boolean',
            'approved' => 'boolean',
            'hidden' => 'boolean',
            'favorite' => 'boolean',
            'hearted' => 'boolean',
            'rating' => 'integer',
        ];
    }

    /**
     * The space this testimonial was submitted to.
     *
     * @return BelongsTo<Space, $this>
     */
    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    /**
     * Every testimonial belonging to the given account.
     *
     * This is the single ownership query for the dashboard. It goes through
     * the space rather than through a direct user id on the testimonial,
     * because the testimonial does not own a user id: it belongs to a space,
     * and the space belongs to the account.
     *
     * The space uses soft deletes, so testimonials of a deleted space are
     * excluded automatically.
     *
     * @param  Builder<Testimonial>  $query
     */
    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->whereHas('space', fn (Builder $query) => $query->where('user_id', $user->getKey()));
    }

    /**
     * Free text search across the name, company and testimonial body.
     *
     * @param  Builder<Testimonial>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function (Builder $query) use ($like): void {
            $query->where('name', 'like', $like)
                ->orWhere('company_name', 'like', $like)
                ->orWhere('body', 'like', $like);
        });
    }

    /**
     * Only the testimonials of the given space.
     *
     * A space of null means no space is selected, so the query is left
     * alone. This is the common case: the inbox shows every space until
     * one is picked in the filter.
     *
     * @param  Builder<Testimonial>  $query
     */
    public function scopeForSpace(Builder $query, ?Space $space): Builder
    {
        if ($space === null) {
            return $query;
        }

        return $query->where('space_id', $space->getKey());
    }

    /**
     * Filter by moderation state.
     *
     * The status is one of all, pending, approved, hidden, on_wall or
     * favorite and selects the testimonials the owner would recognise from
     * that filter in the inbox. The empty status is ignored.
     *
     * @param  Builder<Testimonial>  $query
     */
    public function scopeWithStatus(Builder $query, ?string $status): Builder
    {
        return match ($status) {
            'pending' => $query->where('approved', false),
            'approved' => $query->where('approved', true),
            'hidden' => $query->where('hidden', true),
            'on_wall' => $query->visibleOnWall(),
            'favorite' => $query->where('favorite', true),
            default => $query,
        };
    }

    /**
     * Filter by rating.
     *
     * A rating of zero selects the testimonials that were left without one,
     * since the column is nullable.
     *
     * @param  Builder<Testimonial>  $query
     */
    public function scopeWithRating(Builder $query, ?int $rating): Builder
    {
        if ($rating === null) {
            return $query;
        }

        return $rating === 0
            ? $query->whereNull('rating')
            : $query->where('rating', $rating);
    }

    /**
     * Favourites first, then the newest.
     *
     * The tie break on the creation date keeps the order stable across
     * pages, so a testimonial never moves between pages while paging. The
     * final tie break on the primary key makes the order fully
     * deterministic when two testimonials share a creation timestamp.
     *
     * @param  Builder<Testimonial>  $query
     */
    public function scopeFavoritesFirst(Builder $query): Builder
    {
        return $query->orderByDesc('favorite')->orderByDesc('created_at')->orderByDesc('id');
    }

    /**
     * The testimonials that appear on the public Wall.
     *
     * All four conditions must hold: the testimonial is hearted, approved,
     * not hidden and consented. They are independent of one another, and
     * the heart is only the selection on top of public visibility.
     *
     * @param  Builder<Testimonial>  $query
     */
    public function scopeVisibleOnWall(Builder $query): Builder
    {
        return $query->where('approved', true)
            ->where('hidden', false)
            ->where('consented', true)
            ->where('hearted', true);
    }

    /**
     * The testimonials that may be shown publicly.
     *
     * These are the testimonials that are approved, not hidden and
     * consented. They are the same testimonials the public Wall shows,
     * without the heart: the heart is the owner's own selection for the
     * Wall, and it does not decide whether a testimonial may be shown
     * at all.
     *
     * A widget uses this scope rather than the wall scope, so an owner
     * who has not hearted a testimonial still sees it in the widget.
     *
     * The three conditions are written as SQL rather than taken from
     * the enum, because a query cannot call a PHP method. The scope and
     * isVisiblePublicly() are therefore two expressions of the one rule
     * that TestimonialVisibility holds, and a test pins them together
     * so the two can never be changed apart.
     *
     * @param  Builder<Testimonial>  $query
     */
    public function scopeVisiblePublicly(Builder $query): Builder
    {
        return $query->where('approved', true)
            ->where('hidden', false)
            ->where('consented', true);
    }

    /**
     * Whether this testimonial may be shown publicly.
     *
     * The hidden flag is read through getAttribute() rather than $this->hidden
     * because Eloquent's Model declares a protected $hidden property for
     * serialisation. Inside this class that property is directly accessible,
     * so $this->hidden would return that array instead of the column.
     */
    public function isPubliclyVisible(): bool
    {
        return TestimonialVisibility::isPubliclyVisible(
            (bool) $this->approved,
            (bool) $this->getAttribute('hidden'),
            (bool) $this->consented,
        );
    }

    /**
     * Whether this testimonial may be shown publicly.
     *
     * This is the same rule as isPubliclyVisible(), named separately so
     * the widget and the wall cannot drift apart: the wall adds the
     * heart on top of this rule, and this rule is what a widget uses.
     *
     * The rule itself is held once in TestimonialVisibility, and this
     * method asks that rule. The query scope visiblePublicly() writes
     * the same three conditions in SQL, because a query cannot call a
     * PHP method, so the two are kept in step by a test.
     *
     * The hidden flag is read through getAttribute() for the same reason
     * as in isPubliclyVisible(): $this->hidden would return Eloquent's
     * protected $hidden property instead of the column.
     */
    public function isVisiblePublicly(): bool
    {
        return TestimonialVisibility::isVisiblePublicly(
            (bool) $this->approved,
            (bool) $this->getAttribute('hidden'),
            (bool) $this->consented,
        );
    }

    /**
     * Whether this testimonial appears on the public Wall of Love.
     *
     * The Wall is a stricter surface than public visibility alone: the
     * testimonial must also carry a heart, which is the owner's own
     * selection. The heart does not make a testimonial visible on its
     * own, so all four flags are still required.
     *
     * The hidden flag is read through getAttribute() for the same reason
     * as in isPubliclyVisible(): $this->hidden would return Eloquent's
     * protected $hidden property instead of the column.
     */
    public function isOnWall(): bool
    {
        return TestimonialVisibility::isOnWall(
            (bool) $this->approved,
            (bool) $this->getAttribute('hidden'),
            (bool) $this->consented,
            (bool) $this->hearted,
        );
    }

    /**
     * The customer's email is never part of a public payload.
     *
     * @return array<int, string>
     */
    public function publicAttributes(): array
    {
        return [
            'name',
            'company_name',
            'social_url',
            'photo_path',
            'body',
            'rating',
            'hearted',
            'created_at',
        ];
    }
}
