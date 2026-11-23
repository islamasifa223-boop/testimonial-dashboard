<?php

namespace App\Http\Requests\Dashboard;

use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared validation for the owner editing a testimonial.
 *
 * The owner may change the testimonial text and the rating. Nothing else.
 *
 * The customer details and the sharing consent belong to the customer and
 * are never editable by the owner: the consent in particular is the
 * customer's own decision and must not be reachable from a dashboard form.
 *
 * The moderation flags - approved, hidden, favorite and hearted - are also
 * not editable here. They have their own actions and their own policy
 * abilities, and they are not mass assignable on the model either.
 */
class UpdateTestimonialRequest extends FormRequest
{
    /**
     * The attributes that are written to a testimonial.
     *
     * This is the complete list of what an owner edit may change.
     *
     * @var array<int, string>
     */
    public const STORED_ATTRIBUTES = [
        'body', 'rating',
    ];

    /**
     * Determine if the user is authorized to make this request.
     *
     * A testimonial is only ever edited by the account that owns the space
     * it belongs to. The check lives here rather than in the controller so
     * that it runs before the payload is validated: a validation failure
     * would otherwise tell a non-owner that the id refers to a real
     * testimonial.
     */
    public function authorize(): bool
    {
        $testimonial = $this->route('testimonial');

        // The controller takes the id as an integer, so the route parameter
        // is the raw id rather than a model. It is resolved here so that
        // the ownership check can be made before the payload is validated.
        if (! $testimonial instanceof Testimonial) {
            $testimonial = Testimonial::query()->whereKey($testimonial)->first();
        }

        if (! $testimonial instanceof Testimonial) {
            // A testimonial that does not exist is not the account's to
            // edit. The controller reports it as missing.
            return false;
        }

        $user = $this->user();

        if (! $user instanceof User) {
            return false;
        }

        return $user->spaces()->whereKey($testimonial->space_id)->exists();
    }

    /**
     * Report a refusal as a missing testimonial rather than as a forbidden
     * one.
     *
     * A 403 would confirm that the id belongs to a real testimonial, which
     * would let an account probe for ids it does not own. The same answer
     * is given for a testimonial that does not exist at all.
     */
    protected function failedAuthorization(): void
    {
        abort(404);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The rules mirror the public submission rules exactly, so a
     * testimonial that was accepted when it was submitted can always be
     * saved again after an edit.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:2000'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
        ];
    }

    /**
     * Trim the testimonial text before the rules run.
     *
     * A body of nothing but spaces would otherwise pass the required rule
     * and leave an empty testimonial behind.
     *
     * The rating is not touched: an empty rating arrives as null through
     * the framework and means the testimonial has no rating.
     */
    protected function prepareForValidation(): void
    {
        $body = $this->input('body');

        $this->merge([
            'body' => is_string($body) ? trim($body) : $body,
        ]);
    }

    /**
     * The attributes that are written to a testimonial.
     *
     * Only the text and the rating are ever written. Every other field on
     * the request is ignored, whether or not it was submitted.
     *
     * @return array<string, mixed>
     */
    public function testimonialAttributes(): array
    {
        return array_intersect_key(
            $this->validated(),
            array_flip(self::STORED_ATTRIBUTES)
        );
    }

    /**
     * The messages shown when validation fails.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.max' => 'The testimonial must be 2000 characters or fewer.',
            'rating.min' => 'Please choose a rating between 1 and 5.',
            'rating.max' => 'Please choose a rating between 1 and 5.',
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
            'body' => 'testimonial',
            'rating' => 'rating',
        ];
    }
}
