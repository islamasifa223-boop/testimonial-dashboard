<x-layouts.app title="Edit testimonial">
    <div class="mx-auto max-w-3xl space-y-8">
        <x-dashboard.section-heading
            title="Edit testimonial"
            :description="'Submitted to '.$testimonial->space->name.' by '.$testimonial->name.'.'"
        />

        <form
            method="POST"
            action="{{ route('dashboard.testimonials.update', $testimonial) }}"
            class="space-y-8"
        >
            @csrf
            @method('PUT')

            <x-ui.card>
                <x-slot:title>Testimonial</x-slot:title>
                <x-slot:description>The text the customer wrote. You can edit the wording and the rating, but nothing else.</x-slot:description>

                <div class="space-y-5">
                    <div>
                        <x-ui.label for="body" required>Testimonial</x-ui.label>
                        <textarea
                            id="body"
                            name="body"
                            rows="6"
                            required
                            data-character-counter="body"
                            data-character-counter-max="2000"
                            aria-describedby="body-hint body-counter@error('body') body-error@enderror"
                            @error('body') aria-invalid="true" @enderror
                            placeholder="What the customer said."
                            class="block w-full rounded-lg border-0 bg-surface px-3 py-2 text-sm text-ink shadow-sm ring-1 ring-inset ring-border-subtle placeholder:text-ink-subtle focus:ring-2 focus:ring-inset focus:ring-brand-500"
                        >{{ old('body', $testimonial->body) }}</textarea>
                        <p id="body-hint" class="mt-1.5 text-xs text-ink-muted">Up to 2000 characters. The wording is yours to adjust, but keep it faithful to what the customer said.</p>
                        <p id="body-counter" class="mt-1 text-xs text-ink-subtle" data-character-counter-output="body" aria-live="polite">{{ mb_strlen((string) old('body', $testimonial->body)) }} of 2000 characters used</p>
                        @error('body')
                            <p id="body-error" class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    @if ($testimonial->space->rating_enabled)
                        <div>
                            <x-ui.label for="rating">Rating</x-ui.label>
                            <select
                                id="rating"
                                name="rating"
                                class="block w-full rounded-lg border-0 bg-surface px-3 py-2 text-sm text-ink shadow-sm ring-1 ring-inset ring-border-subtle focus:ring-2 focus:ring-inset focus:ring-brand-500"
                                @error('rating') aria-invalid="true" aria-describedby="rating-error" @enderror
                            >
                                <option value="">No rating</option>
                                @for ($star = 1; $star <= 5; $star++)
                                    <option value="{{ $star }}" @selected((string) old('rating', $testimonial->rating) === (string) $star)>{{ $star }} star{{ $star === 1 ? '' : 's' }}</option>
                                @endfor
                            </select>
                            @error('rating')
                                <p id="rating-error" class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    @else
                        <dl>
                            <dt class="text-sm font-medium text-ink">Rating</dt>
                            <dd class="mt-1.5 rounded-lg bg-surface-muted px-3 py-2 text-sm text-ink-muted">
                                {{ $testimonial->rating ? $testimonial->rating.' out of 5' : 'No rating' }}
                            </dd>
                            <dd class="mt-1.5 text-xs text-ink-muted">This space does not collect ratings, so the rating cannot be changed.</dd>
                        </dl>
                    @endif
                </div>
            </x-ui.card>

            <x-ui.card>
                <x-slot:title>Customer details</x-slot:title>
                <x-slot:description>Collected from the customer when they submitted the testimonial. These cannot be edited.</x-slot:description>

                <dl class="divide-y divide-border-subtle">
                    <div class="flex items-center justify-between gap-4 py-3 first:pt-0">
                        <dt class="text-sm text-ink-muted">Name</dt>
                        <dd class="text-sm font-medium text-ink">{{ $testimonial->name }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 py-3">
                        <dt class="text-sm text-ink-muted">Email address</dt>
                        <dd class="text-sm font-medium text-ink">{{ $testimonial->email }}</dd>
                    </div>
                    @if ($testimonial->company_name)
                        <div class="flex items-center justify-between gap-4 py-3">
                            <dt class="text-sm text-ink-muted">Company</dt>
                            <dd class="text-sm font-medium text-ink">{{ $testimonial->company_name }}</dd>
                        </div>
                    @endif
                    @if ($testimonial->social_url)
                        <div class="flex items-center justify-between gap-4 py-3">
                            <dt class="text-sm text-ink-muted">Social profile</dt>
                            <dd class="text-sm font-medium text-ink">
                                <a href="{{ $testimonial->social_url }}" target="_blank" rel="noopener noreferrer" class="hover:text-brand-700">Open link</a>
                            </dd>
                        </div>
                    @endif
                    <div class="flex items-center justify-between gap-4 py-3">
                        <dt class="text-sm text-ink-muted">Submitted</dt>
                        <dd class="text-sm font-medium text-ink">{{ $testimonial->created_at->format('j M Y, H:i') }}</dd>
                    </div>
                </dl>
            </x-ui.card>

            <div class="flex flex-wrap items-center gap-3">
                <x-ui.button type="submit">Save changes</x-ui.button>
                <x-ui.button
                    href="{{ route('dashboard.inbox') }}"
                    variant="ghost"
                >Cancel</x-ui.button>
            </div>
        </form>
    </div>
</x-layouts.app>
