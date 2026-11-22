<?php

namespace App\Http\Requests\Dashboard;

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
     * Ownership is checked by the controller, which knows which testimonial
     * is being updated.
     */
    public function authorize(): bool
    {
        return true;
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
