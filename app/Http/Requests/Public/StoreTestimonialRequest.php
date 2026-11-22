<?php

namespace App\Http\Requests\Public;

use App\Enums\SpaceField;
use App\Models\Space;
use App\Services\TestimonialSubmissionGuard;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use ReflectionProperty;

class StoreTestimonialRequest extends FormRequest
{
    public const TOKEN_ATTRIBUTE = 'kudosly_submission_token';

    public const HONEYPOT_ATTRIBUTE = 'website';

    /**
     * The attributes that are written to a testimonial.
     *
     * @var array<int, string>
     */
    public const STORED_ATTRIBUTES = [
        'name', 'email', 'company_name', 'social_url', 'body', 'rating',
    ];

    public const SPAM_MESSAGE = 'We could not accept this submission. Please take a moment and try again.';

    public function authorize(): bool
    {
        return true;
    }

    public function space(): Space
    {
        return Space::where('slug', $this->route('slug'))->firstOrFail();
    }

    public function rules(): array
    {
        $space = $this->space();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'company_name' => [$this->isRequired(SpaceField::CompanyName) ? 'required' : 'nullable', 'string', 'max:255'],
            'social_url' => [$this->isRequired(SpaceField::SocialUrl) ? 'required' : 'nullable', 'string', 'url', 'max:2048'],
            'body' => ['required', 'string', 'max:2000'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'photo' => [$this->isRequired(SpaceField::Photo) ? 'required' : 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'consent' => ['nullable', 'boolean'],
            self::TOKEN_ATTRIBUTE => ['required', 'string'],
            self::HONEYPOT_ATTRIBUTE => ['nullable', 'string'],
        ];
    }

    /**
     * Whether the given field has to be filled in by the visitor.
     *
     * A field that is switched off can never be required, so a disabled
     * field always produces a nullable rule.
     */
    protected function isRequired(SpaceField $field): bool
    {
        $space = $this->space();

        return $space->isFieldEnabled($field) && $space->isFieldRequired($field);
    }

    protected function prepareForValidation(): void
    {
        $space = $this->space();

        $attributes = [];

        if (! $space->isFieldEnabled(SpaceField::CompanyName)) {
            $attributes['company_name'] = null;
        }

        if (! $space->isFieldEnabled(SpaceField::SocialUrl)) {
            $attributes['social_url'] = null;
        }

        if (! $space->isFieldEnabled(SpaceField::Photo)) {
            // An uploaded file lives in the request's files bag, which
            // merge() never touches. The file has to be removed from that
            // bag as well, or photo() would still return it and the file
            // would be stored even though the space does not collect
            // photos.
            $this->removeUploadedPhoto();

            $attributes['photo'] = null;
        }

        if (! $space->rating_enabled) {
            $attributes['rating'] = null;
        }

        if ($attributes !== []) {
            $this->merge($attributes);
        }
    }

    /**
     * Remove the uploaded photo from the request.
     *
     * The files bag is emptied of the photo, and the array of converted
     * files that the request caches is invalidated, so the removal holds
     * whether or not the files were converted before this call.
     */
    protected function removeUploadedPhoto(): void
    {
        $this->files->remove('photo');

        $this->resetConvertedFiles();
    }

    /**
     * Clear the request's cached array of converted files.
     *
     * The request converts its files once and caches the result, so the
     * cache has to be dropped for a removed file to stay removed.
     */
    protected function resetConvertedFiles(): void
    {
        $property = new ReflectionProperty(Request::class, 'convertedFiles');

        $property->setValue($this, null);
    }

    /**
     * The attributes that are written to a testimonial.
     */
    public function testimonialAttributes(): array
    {
        return array_intersect_key(
            $this->validated(),
            array_flip(self::STORED_ATTRIBUTES)
        );
    }

    /**
     * The photo to store, or null when the space does not collect photos.
     */
    public function photo(): ?UploadedFile
    {
        $photo = $this->file('photo');

        return $photo instanceof UploadedFile ? $photo : null;
    }

    public function consented(): bool
    {
        return $this->boolean('consent');
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $honeypot = $this->input(self::HONEYPOT_ATTRIBUTE);

            if (is_string($honeypot) && trim($honeypot) !== '') {
                $validator->errors()->add('body', self::SPAM_MESSAGE);

                return;
            }

            $token = $this->input(self::TOKEN_ATTRIBUTE);

            $guard = app(TestimonialSubmissionGuard::class);

            if (! $guard->consumeToken(is_string($token) ? $token : null, $this->space())) {
                $validator->errors()->add('body', self::SPAM_MESSAGE);
            }
        });
    }

    /**
     * The URL a failed validation redirects to.
     *
     * The default falls back to the referer, which a visitor does not
     * always send. A submission that is rejected would then leave the
     * public page and land on the home page, so the redirect always
     * points back at the Space the visitor was on.
     */
    public function getRedirectUrl(): string
    {
        return $this->redirector->getUrlGenerator()->route('spaces.show', $this->route('slug'));
    }

    public function messages(): array
    {
        return [
            'body.max' => 'Your testimonial must be 2000 characters or fewer.',
            'photo.max' => 'The photo must be 2 MB or smaller.',
            'rating.min' => 'Please choose a rating between 1 and 5.',
            'rating.max' => 'Please choose a rating between 1 and 5.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'name',
            'email' => 'email address',
            'company_name' => 'company name',
            'social_url' => 'social profile URL',
            'body' => 'testimonial',
            'rating' => 'rating',
            'photo' => 'photo',
        ];
    }
}
