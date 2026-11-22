@php
    $theme = app(App\Services\SpaceThemePreview::class)->page($space->theme);
    $fields = $space->fieldOrder();
    $token = app(App\Services\TestimonialSubmissionGuard::class)->issueToken($space);

    $companyNameRequired = $space->isFieldRequired(App\Enums\SpaceField::CompanyName);
    $socialUrlRequired = $space->isFieldRequired(App\Enums\SpaceField::SocialUrl);
    $photoRequired = $space->isFieldRequired(App\Enums\SpaceField::Photo);

    // The configurable fields follow the order the owner chose. Fields
    // the owner left out of the order are appended in the canonical
    // sequence, so a field is never dropped from the form.
    $configurable = App\Enums\SpaceField::configurableValues();

    $ordered = [];

    foreach ($fields as $field) {
        if (in_array($field, $configurable, true) && ! in_array($field, $ordered, true)) {
            $ordered[] = $field;
        }
    }

    foreach ($configurable as $field) {
        if (! in_array($field, $ordered, true)) {
            $ordered[] = $field;
        }
    }
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $space->title }}</title>
    <meta name="description" content="{{ $space->subtitle ?? $space->title }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full {{ $theme['page'] }}">
    <main class="mx-auto flex min-h-screen w-full max-w-2xl flex-col justify-center px-6 py-16">
        <div class="rounded-2xl border p-8 shadow-sm backdrop-blur {{ $theme['card'] }}">
            <h1 class="text-2xl font-semibold tracking-tight {{ $theme['heading'] }}">{{ $space->title }}</h1>

            @if ($space->subtitle)
                <p class="mt-2 text-sm {{ $theme['body'] }}">{{ $space->subtitle }}</p>
            @endif

            @if ($space->request)
                <p class="mt-6 text-sm {{ $theme['body'] }}">{{ $space->request }}</p>
            @endif

            @if (session('status'))
                <div class="mt-6">
                    <x-ui.alert variant="info">{{ session('status') }}</x-ui.alert>
                </div>
            @endif

            @if ($errors->any())
                <div class="mt-6">
                    <x-ui.alert variant="error">
                        {{ $errors->first() }}
                    </x-ui.alert>
                </div>
            @endif

            <form
                method="POST"
                action="{{ route('spaces.testimonials.store', $space->slug) }}"
                enctype="multipart/form-data"
                class="mt-8 space-y-5"
            >
                @csrf

                <input type="hidden" name="{{ App\Http\Requests\Public\StoreTestimonialRequest::TOKEN_ATTRIBUTE }}" value="{{ $token }}">

                <div class="sr-only" aria-hidden="true">
                    <label for="{{ App\Http\Requests\Public\StoreTestimonialRequest::HONEYPOT_ATTRIBUTE }}">Website</label>
                    <input
                        type="text"
                        id="{{ App\Http\Requests\Public\StoreTestimonialRequest::HONEYPOT_ATTRIBUTE }}"
                        name="{{ App\Http\Requests\Public\StoreTestimonialRequest::HONEYPOT_ATTRIBUTE }}"
                        tabindex="-1"
                        autocomplete="off"
                    >
                </div>

                <x-ui.input
                    name="name"
                    label="Name"
                    autocomplete="name"
                    required
                />

                <x-ui.input
                    name="email"
                    type="email"
                    label="Email address"
                    autocomplete="email"
                    required
                />

                @foreach ($ordered as $field)
                    @if ($field === App\Enums\SpaceField::CompanyName->value)
                        @if ($space->isFieldEnabled(App\Enums\SpaceField::CompanyName))
                            <x-ui.input
                                name="company_name"
                                label="Company name"
                                autocomplete="organization"
                                :required="$companyNameRequired"
                            />
                        @endif
                    @elseif ($field === App\Enums\SpaceField::SocialUrl->value)
                        @if ($space->isFieldEnabled(App\Enums\SpaceField::SocialUrl))
                            <x-ui.input
                                name="social_url"
                                type="url"
                                label="Social profile URL"
                                placeholder="https://example.com/your-profile"
                                :required="$socialUrlRequired"
                            />
                        @endif
                    @elseif ($field === App\Enums\SpaceField::Photo->value)
                        @if ($space->isFieldEnabled(App\Enums\SpaceField::Photo))
                            <div>
                                <x-ui.label for="photo" :required="$photoRequired">Photo</x-ui.label>
                                <input
                                    type="file"
                                    id="photo"
                                    name="photo"
                                    accept="image/jpeg,image/png,image/webp"
                                    @if ($photoRequired) required @endif
                                    class="block w-full rounded-lg bg-surface px-3 py-2 text-sm text-ink shadow-sm ring-1 ring-inset ring-border-subtle focus:ring-2 focus:ring-inset focus:ring-brand-500"
                                >
                                <p class="mt-1.5 text-xs text-ink-muted">A JPEG, PNG or WebP image, up to 2 MB.</p>
                                @error('photo')
                                    <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        @endif
                    @endif
                @endforeach

                <div>
                    <x-ui.label for="body" required>Your testimonial</x-ui.label>
                    <textarea
                        id="body"
                        name="body"
                        rows="5"
                        maxlength="2000"
                        required
                        placeholder="Tell us what stood out."
                        class="block w-full rounded-lg border-0 bg-surface px-3 py-2 text-sm text-ink shadow-sm ring-1 ring-inset ring-border-subtle placeholder:text-ink-subtle focus:ring-2 focus:ring-inset focus:ring-brand-500"
                    >{{ old('body') }}</textarea>
                    @error('body')
                        <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                @if ($space->rating_enabled)
                    <div>
                        <x-ui.label for="rating">Rating</x-ui.label>
                        <select
                            id="rating"
                            name="rating"
                            class="block w-full rounded-lg border-0 bg-surface px-3 py-2 text-sm text-ink shadow-sm ring-1 ring-inset ring-border-subtle focus:ring-2 focus:ring-inset focus:ring-brand-500"
                        >
                            <option value="">No rating</option>
                            @for ($star = 1; $star <= 5; $star++)
                                <option value="{{ $star }}" @selected(old('rating') == $star)>{{ $star }} star{{ $star === 1 ? '' : 's' }}</option>
                            @endfor
                        </select>
                        @error('rating')
                            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                @endif

                <label class="inline-flex cursor-pointer items-start gap-2">
                    <input
                        type="checkbox"
                        name="consent"
                        value="1"
                        @checked(old('consent'))
                        class="mt-0.5 size-4 rounded border-border-subtle text-brand-600 focus:ring-brand-500"
                    >
                    <span class="text-sm text-ink">You may share my testimonial publicly on this space.</span>
                </label>

                <div class="flex flex-wrap items-center gap-3 pt-2">
                    <x-ui.button type="submit">Send testimonial</x-ui.button>
                </div>
            </form>
        </div>

        <p class="mt-6 text-center text-xs {{ $theme['muted'] }}">
            Powered by {{ config('app.name', 'Kudosly') }}
        </p>
    </main>
</body>
</html>
