<?php

namespace App\Http\Requests\Dashboard;

use App\Enums\SpaceField;
use App\Enums\SpaceTheme;
use App\Models\User;
use App\Services\SpaceNameNormalizer;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * Shared validation rules for creating and updating a space.
 *
 * The space slug is never accepted from the browser: it is generated on the
 * server by the SpaceSlugGenerator so it can never be forged.
 */
abstract class SpaceRequest extends FormRequest
{
    /**
     * The internal attribute holding the normalised name key.
     */
    public const NAME_KEY_ATTRIBUTE = 'kudosly_name_key';

    /**
     * Determine if the user is authorized to make this request.
     *
     * Ownership is checked by the controller, which knows which space is
     * being updated.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Laravel only ever calls rules(), so this method is the single entry
     * point into the shared rule set.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->baseRules($this->user());
    }

    /**
     * The rules shared by every space request.
     *
     * @return array<string, mixed>
     */
    protected function baseRules(User $user): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'request' => ['nullable', 'string', 'max:2000'],
            'theme' => ['required', Rule::enum(SpaceTheme::class)],
            'rating_enabled' => ['boolean'],

            'company_name_enabled' => ['boolean'],
            'company_name_required' => ['boolean'],
            'social_url_enabled' => ['boolean'],
            'social_url_required' => ['boolean'],
            'photo_enabled' => ['boolean'],
            'photo_required' => ['boolean'],

            'field_order' => ['present', 'array'],
            'field_order.*' => ['string', 'distinct', Rule::in(SpaceField::values())],

            self::NAME_KEY_ATTRIBUTE => ['bail', 'required', 'string', $this->nameRule($user)],
        ];
    }

    /**
     * The rule enforcing that the space name is unique for the account.
     */
    abstract protected function nameRule(User $user): Unique;

    /**
     * Compute the internal values and canonicalise the field order before
     * the rules run, so the value returned by validated() is canonical.
     */
    protected function prepareForValidation(): void
    {
        $name = $this->input('name');

        // A non-string name is rejected by the `name` rules, so the internal
        // key is left empty rather than casting a value that is not a
        // string. Casting an array would emit a conversion warning.
        $nameKey = is_string($name) ? SpaceNameNormalizer::key($name) : '';

        $this->merge([
            self::NAME_KEY_ATTRIBUTE => $nameKey,
            'rating_enabled' => $this->boolean('rating_enabled'),
            'company_name_enabled' => $this->boolean('company_name_enabled'),
            'company_name_required' => $this->boolean('company_name_required'),
            'social_url_enabled' => $this->boolean('social_url_enabled'),
            'social_url_required' => $this->boolean('social_url_required'),
            'photo_enabled' => $this->boolean('photo_enabled'),
            'photo_required' => $this->boolean('photo_required'),
        ]);

        // A field that is switched off can never be required.
        foreach (SpaceField::configurableValues() as $field) {
            if (! $this->boolean("{$field}_enabled")) {
                $this->merge(["{$field}_required" => false]);
            }
        }

        $this->merge(['field_order' => $this->canonicalFieldOrder()]);
    }

    /**
     * The canonical field order.
     *
     * Phase 1 - absent, null or empty: the default order.
     * Phase 2 - contains an unknown field: return the raw input unchanged
     *            so the `in:` rule can reject it.
     * Phase 3 - valid unique array: the submitted order, with any omitted
     *            field appended in the default sequence.
     * Phase 4 - valid array containing duplicates: the submitted order is
     *            preserved VERBATIM (duplicates included) so the `distinct`
     *            rule can reject it. Omitted fields are still appended,
     *            which never introduces a new duplicate.
     */
    protected function canonicalFieldOrder(): mixed
    {
        $submitted = $this->input('field_order');

        // Phase 1
        if ($submitted === null || $submitted === '') {
            return SpaceField::defaultOrder();
        }

        // Not an array: the `array` rule rejects it.
        if (! is_array($submitted)) {
            return $submitted;
        }

        // Phase 2 - unknown field: return the raw input so the rules reject it.
        foreach ($submitted as $field) {
            if (! is_string($field) || ! in_array($field, SpaceField::values(), true)) {
                return $submitted;
            }
        }

        // Phase 3 / 4 - duplicates are preserved, never removed.
        $order = array_values($submitted);

        // Append only fields that are not already present.
        foreach (SpaceField::defaultOrder() as $field) {
            if (! in_array($field, $order, true)) {
                $order[] = $field;
            }
        }

        return $order;
    }

    /**
     * The validated payload with the internal, validation-only name key
     * removed.
     *
     * @return array<string, mixed>
     */
    public function spaceAttributes(): array
    {
        $attributes = $this->validated();

        unset($attributes[self::NAME_KEY_ATTRIBUTE]);

        return $attributes;
    }

    /**
     * The messages shown when validation fails.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            self::NAME_KEY_ATTRIBUTE.'.unique' => 'A space with this name already exists.',
            'field_order.required' => 'The field order is missing.',
        ];
    }

    /**
     * The attributes used in the validation messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'space name',
            'title' => 'title',
            'subtitle' => 'subtitle',
            'request' => 'request message',
            'theme' => 'theme',
            'rating_enabled' => 'rating',
        ];
    }

    /**
     * Move the internal name-key error onto the visible name field.
     *
     * Only a uniqueness failure is transferred: the account already owns a
     * space with this name. Any other failure of the internal attribute is
     * an artefact of the validation itself and is never shown.
     *
     * The internal attribute is always removed from the error bag so it
     * never appears in the response.
     */
    protected function failedValidation(Validator $validator): void
    {
        $failed = $validator->failed();

        $isUniqueFailure = isset($failed[self::NAME_KEY_ATTRIBUTE])
            && array_key_exists('Unique', $failed[self::NAME_KEY_ATTRIBUTE]);

        if ($isUniqueFailure && $validator->errors()->has(self::NAME_KEY_ATTRIBUTE)) {
            $validator->errors()->add(
                'name',
                (string) $validator->errors()->first(self::NAME_KEY_ATTRIBUTE)
            );
        }

        $validator->errors()->forget(self::NAME_KEY_ATTRIBUTE);

        parent::failedValidation($validator);
    }
}
