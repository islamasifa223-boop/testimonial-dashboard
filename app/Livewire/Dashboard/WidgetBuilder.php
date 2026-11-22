<?php

namespace App\Livewire\Dashboard;

use App\Enums\WidgetAppearance;
use App\Enums\WidgetLayout;
use App\Models\Space;
use App\Models\Testimonial;
use App\Models\Widget;
use App\Services\WidgetAppearance as WidgetAppearanceService;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Builds and edits the widgets of the signed in account.
 *
 * Every space and every widget that reaches this component is looked
 * up through the signed in account, and a widget is additionally
 * authorised through the widget policy. Nothing the browser sends is
 * trusted: the ids are only ever used to find a row the account
 * already owns, and the settings are validated before they are
 * written.
 *
 * The public id of a widget is never read from the browser. It is
 * generated on the server when the widget is created, so a submitted
 * id cannot point an embed at somebody else's widget.
 */
class WidgetBuilder extends Component
{
    /**
     * The most testimonials a widget may show.
     *
     * The limit on the form and the limit the preview falls back to
     * are the same number, so the preview never shows more than the
     * widget will.
     */
    public const LIMIT_MAXIMUM = 20;

    /**
     * The space the widget belongs to.
     *
     * The id is not locked, because the owner picks the space from a
     * dropdown while a widget is being created, and a locked id would
     * refuse the choice the owner just made.
     *
     * Leaving it unlocked does not make it unsafe. The space is
     * always resolved through the account's own spaces, so a space of
     * another account resolves to nothing and is refused by the
     * validation. The space of a saved widget is fixed regardless of
     * what this property holds, because the attributes the form
     * writes do not carry a space.
     */
    public ?int $spaceId = null;

    /**
     * The widget being edited.
     *
     * Null means a new widget is being created. The id is locked, so
     * the browser cannot point the form at a widget it does not own.
     */
    #[Locked]
    public ?int $widgetId = null;

    public string $layout = 'masonry';

    public string $theme = 'light';

    /**
     * The background colour of the widget.
     *
     * An empty string means the widget uses the default surface of its
     * theme.
     */
    public string $background = '';

    /**
     * Whether the widget animates.
     *
     * The property carries no type on purpose. A typed property would
     * coerce the value the browser sent before the validation ever
     * saw it, so a malformed value would be accepted and stored as if
     * it were valid. Untyped, the property holds exactly what the
     * browser sent, and the boolean rule decides whether it is
     * allowed.
     */
    public $animation = true;

    /**
     * How many testimonials the widget shows.
     *
     * Untyped for the same reason as the animation flag: a typed
     * integer property either rejects a malformed value outright, with
     * an error the owner cannot act on, or silently truncates it.
     * Untyped, the integer rule rejects it with a message the owner
     * can act on, and the model normalises the value that is stored.
     */
    public $limit = 6;

    /**
     * The account that is signed in.
     */
    public function user()
    {
        return auth()->user();
    }

    /**
     * The spaces of the account, for the picker.
     *
     * The spaces are ordered by their name, which is the label the
     * account knows a space by in the dashboard.
     *
     * @return Collection<int, Space>
     */
    public function spaces()
    {
        return $this->user()->spaces()->orderBy('name')->get();
    }

    /**
     * The widgets of the account, for the list.
     *
     * The ownership query goes through the space, so the widgets of a
     * soft deleted space are excluded as well.
     *
     * @return Collection<int, Widget>
     */
    public function widgets()
    {
        return Widget::ownedBy($this->user())->with('space')->get();
    }

    /**
     * The widget being edited, if there is one.
     */
    public function widget(): ?Widget
    {
        if ($this->widgetId === null) {
            return null;
        }

        return Widget::ownedBy($this->user())->find($this->widgetId);
    }

    /**
     * The space the builder is working on.
     *
     * The space is read from the widget when a widget is being edited,
     * and never from the browser. A widget belongs to the space it was
     * created for, so the space of an existing widget is not something
     * the owner may move it to.
     *
     * When a widget id is set but the widget cannot be resolved, the
     * space is null. Falling back to the space id there would let the
     * browser point the preview at a space of its own choosing, so the
     * fallback is only used while a new widget is being created.
     */
    public function space(): ?Space
    {
        if ($this->widgetId !== null) {
            $widget = $this->widget();

            return $widget?->space;
        }

        if ($this->spaceId === null) {
            return null;
        }

        return $this->user()->spaces()->find($this->spaceId);
    }

    /**
     * Whether a saved widget is being edited.
     *
     * The space of a saved widget is fixed, so the space picker is
     * only shown for a widget that is being created.
     */
    public function editingExistingWidget(): bool
    {
        return $this->widgetId !== null;
    }

    /**
     * The testimonials the preview shows.
     *
     * The preview shows exactly what the public widget shows: the
     * testimonials that are approved, not hidden and consented. The
     * heart does not matter here, because the heart selects a
     * testimonial for the Wall and not for the widget.
     *
     * The order is the order of the public widget, so the preview and
     * the widget show the same testimonials in the same order.
     *
     * @return Collection<int, Testimonial>
     */
    public function previewTestimonials(): Collection
    {
        $space = $this->space();

        if ($space === null) {
            return new Collection;
        }

        return $space->testimonials()
            ->visiblePublicly()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($this->previewLimit())
            ->get();
    }

    /**
     * The CSS custom properties the preview is rendered with.
     *
     * The preview is not a saved widget yet, so the appearance is
     * built from the settings on the form rather than from a row. The
     * appearance service is the same one the public renderer uses, so
     * the preview and the widget agree on the colours.
     *
     * @return array<string, string>
     */
    public function previewAppearance(): array
    {
        return app(WidgetAppearanceService::class)->for($this->previewWidget());
    }

    /**
     * The widget the preview is rendered from.
     *
     * It is never saved. It only carries the settings that are on the
     * form, so the preview shows the settings the owner is choosing
     * rather than the settings that were last saved.
     */
    public function previewWidget(): Widget
    {
        $widget = new Widget([
            'layout' => WidgetLayout::tryFrom($this->layout) ?? WidgetLayout::Masonry,
            'theme' => WidgetAppearance::tryFrom($this->theme) ?? WidgetAppearance::Light,
            'background' => $this->background === '' ? null : $this->background,
            'animation' => $this->previewAnimation(),
            'limit' => $this->previewLimit(),
        ]);

        $widget->space()->associate($this->space());

        return $widget;
    }

    /**
     * How many testimonials the preview shows.
     *
     * The value on the form is whatever the browser sent, so it is
     * normalised here rather than cast. A value that is not a whole
     * number falls back to one, and a value above the maximum falls
     * back to the maximum, so the preview always shows something and
     * never shows more than the widget will.
     */
    protected function previewLimit(): int
    {
        $limit = filter_var($this->limit, FILTER_VALIDATE_INT);

        if ($limit === false || $limit < 1) {
            return 1;
        }

        return $limit > self::LIMIT_MAXIMUM ? self::LIMIT_MAXIMUM : $limit;
    }

    /**
     * Whether the preview animates.
     *
     * A value the browser sent that is not a boolean falls back to the
     * default, so an invalid value on the form can never make the
     * preview throw.
     */
    protected function previewAnimation(): bool
    {
        $animation = filter_var($this->animation, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $animation ?? true;
    }

    /**
     * The rules the settings are validated against.
     *
     * The space is only validated when a widget is being created. The
     * space of a saved widget is fixed, so there is nothing to
     * validate and nothing the browser could change.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'layout' => ['required', Rule::enum(WidgetLayout::class)],
            'theme' => ['required', Rule::enum(WidgetAppearance::class)],
            'background' => ['nullable', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if (! app(WidgetAppearanceService::class)->isValidBackground(is_string($value) ?
  $value : null)) {
                    $fail('The background must be a hex colour such as #1f2937.');
                }
            }],
            'animation' => ['required', 'boolean'],
            'limit' => ['required', 'integer', 'min:1', 'max:'.self::LIMIT_MAXIMUM],
        ];

        if (! $this->editingExistingWidget()) {
            $rules['spaceId'] = [
                'required',
                'integer',
                Rule::exists('spaces', 'id')->where('user_id', $this->user()->getKey()),
            ];
        }

        return $rules;
    }

    /**
     * The messages the rules are shown with.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'spaceId.required' => 'Choose the space the widget shows.',
            'layout.required' => 'Choose a layout.',
            'theme.required' => 'Choose a colour mode.',
            'limit.min' => 'Show at least one testimonial.',
            'limit.max' => 'Show at most twenty testimonials.',
        ];
    }

    /**
     * Start a new widget for the given space.
     */
    public function createWidget(?int $spaceId = null): void
    {
        if ($spaceId !== null) {
            $space = $this->user()->spaces()->find($spaceId);

            if ($space === null) {
                $this->addError('spaceId', 'Choose the space the widget shows.');

                return;
            }

            $this->spaceId = $space->getKey();
        }

        $this->widgetId = null;

        $this->layout = WidgetLayout::Masonry->value;
        $this->theme = WidgetAppearance::Light->value;
        $this->background = '';
        $this->animation = true;
        $this->limit = 6;

        $this->resetErrorBag();
    }

    /**
     * Load a widget into the form.
     */
    public function editWidget(int $widgetId): void
    {
        $widget = Widget::ownedBy($this->user())->with('space')->find($widgetId);

        if ($widget === null) {
            return;
        }

        $this->authorize('update', $widget);

        $this->widgetId = $widget->getKey();
        $this->spaceId = $widget->space?->getKey();

        $this->layout = $widget->layout->value;
        $this->theme = $widget->theme->value;
        $this->background = (string) $widget->background;
        $this->animation = (bool) $widget->animation;
        $this->limit = (int) $widget->limit;

        $this->resetErrorBag();
    }

    /**
     * Save the widget.
     *
     * A new widget is created for the chosen space. An existing widget
     * is updated, and its space is left exactly as it was: the space
     * of a saved widget is not something that may be moved.
     *
     * The public id is never written from the form. It is generated on
     * the server when the widget is created, and it is left alone when
     * the widget is updated.
     */
    public function save(): void
    {
        $validated = $this->validate();

        $attributes = [
            'layout' => $validated['layout'],
            'theme' => $validated['theme'],
            'background' => $validated['background'] === '' ? null : $validated['background'],
            'animation' => $validated['animation'],
            'limit' => $validated['limit'],
        ];

        if ($this->widgetId !== null) {
            $widget = Widget::ownedBy($this->user())->find($this->widgetId);

            if ($widget === null) {
                return;
            }

            $this->authorize('update', $widget);

            // The space is not in the attributes, so the widget keeps
            // the space it was created for.
            $widget->update($attributes);

            return;
        }

        $space = $this->user()->spaces()->find($validated['spaceId']);

        if ($space === null) {
            $this->addError('spaceId', 'Choose the space the widget shows.');

            return;
        }

        // The public id is not in the attributes, so it is generated by
        // the model when the widget is created.
        $widget = new Widget($attributes);
        $widget->space()->associate($space);
        $widget->save();

        $this->widgetId = $widget->getKey();
    }

    /**
     * Delete the widget being edited.
     */
    public function deleteWidget(): void
    {
        if ($this->widgetId === null) {
            return;
        }

        $widget = Widget::ownedBy($this->user())->find($this->widgetId);

        if ($widget === null) {
            return;
        }

        $this->authorize('delete', $widget);

        $widget->delete();

        $this->widgetId = null;
        $this->resetErrorBag();
    }

    public function render()
    {
        return view('livewire.dashboard.widget-builder');
    }
}
