<?php

namespace App\Models;

use App\Enums\WidgetAppearance;
use App\Enums\WidgetLayout;
use Database\Factories\WidgetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * An embeddable widget that shows a space's testimonials on another
 * website.
 *
 * Only the appearance settings are fillable. The space and the public
 * id are never mass assignable: the space is set through the relation,
 * and the public id is generated on the server. A submitted public id
 * would let an account point an embed at somebody else's widget.
 */
#[Fillable([
    'layout',
    'theme',
    'background',
    'animation',
    'limit',
])]
class Widget extends Model
{
    /** @use HasFactory<WidgetFactory> */
    use HasFactory;

    /**
     * How many characters the public id has.
     *
     * The id is a random string rather than the primary key, so a widget
     * cannot be found by counting up from one.
     */
    public const PUBLIC_ID_LENGTH = 32;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'layout' => WidgetLayout::class,
            'theme' => WidgetAppearance::class,
            'animation' => 'boolean',
            'limit' => 'integer',
        ];
    }

    /**
     * The public id is generated on the server, never taken from the
     * browser.
     *
     * It is generated when the widget is created rather than when it is
     * saved, because a widget that exists without a public id has no
     * embed URL and could never be embedded.
     */
    protected static function booted(): void
    {
        static::creating(function (self $widget): void {
            if ($widget->public_id === null || $widget->public_id === '') {
                $widget->public_id = Str::random(self::PUBLIC_ID_LENGTH);
            }
        });
    }

    /**
     * The space this widget belongs to.
     *
     * @return BelongsTo<Space, $this>
     */
    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    /**
     * Every widget belonging to the given account.
     *
     * This is the ownership query for the dashboard. It goes through the
     * space rather than through a direct user id on the widget, because
     * the widget does not own a user id: it belongs to a space, and the
     * space belongs to the account.
     *
     * The space uses soft deletes, so widgets of a deleted space are
     * excluded automatically.
     *
     * @param  Builder<Widget>  $query
     */
    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->whereHas('space', fn (Builder $query) => $query->where('user_id', $user->getKey()));
    }

    /**
     * The path the widget is embedded at.
     *
     * The public id is the only secret in the path, so it is the part
     * that has to stay unguessable.
     */
    public function embedPath(): string
    {
        return '/widget/'.$this->public_id;
    }

    /**
     * Whether the widget shows a background of its own.
     *
     * A widget without a background uses the default of its theme, so
     * the owner never has to pick a colour they are happy with.
     */
    public function hasBackground(): bool
    {
        return $this->background !== null && $this->background !== '';
    }
}
