<x-layouts.app title="Settings">
    <div class="space-y-8">
        <x-dashboard.section-heading
            title="Account settings"
            description="Update your profile details and change your password."
        />

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <x-ui.card title="Profile information" description="Your name and email address.">
                    <form method="POST" action="{{ route('dashboard.settings.profile.update') }}" class="space-y-5">
                        @csrf
                        @method('PATCH')

                        <x-ui.input
                            name="name"
                            label="Full name"
                            type="text"
                            :value="$user->name"
                            autocomplete="name"
                            required
                        />

                        <x-ui.input
                            name="email"
                            label="Email address"
                            type="email"
                            :value="$user->email"
                            autocomplete="username"
                            required
                        />

                        <div class="flex justify-end">
                            <x-ui.button type="submit">Save changes</x-ui.button>
                        </div>
                    </form>
                </x-ui.card>

                <x-ui.card title="Change password" description="Choose a new password for your account.">
                    <form method="POST" action="{{ route('dashboard.settings.password.update') }}" class="space-y-5">
                        @csrf
                        @method('PUT')

                        <x-ui.input
                            name="current_password"
                            label="Current password"
                            type="password"
                            autocomplete="current-password"
                            bag="updatePassword"
                            required
                        />

                        <x-ui.input
                            name="password"
                            label="New password"
                            type="password"
                            autocomplete="new-password"
                            bag="updatePassword"
                            required
                        />

                        <x-ui.input
                            name="password_confirmation"
                            label="Confirm new password"
                            type="password"
                            autocomplete="new-password"
                            bag="updatePassword"
                            required
                        />

                        <div class="flex justify-end">
                            <x-ui.button type="submit">Update password</x-ui.button>
                        </div>
                    </form>
                </x-ui.card>
            </div>

            <div class="space-y-6">
                <x-ui.card title="Account">
                    <dl class="space-y-3 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-ink-muted">Plan</dt>
                            <dd><x-dashboard.plan-badge :plan="$user->plan" /></dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-ink-muted">Spaces</dt>
                            <dd class="font-medium text-ink">{{ $user->spaces()->count() }} / {{ $user->spacesLimit() }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-ink-muted">Member since</dt>
                            <dd class="font-medium text-ink">{{ $user->created_at?->format('M Y') }}</dd>
                        </div>
                    </dl>

                    <a href="{{ route('dashboard.billing') }}" class="mt-5 block text-sm font-medium text-brand-600 hover:text-brand-700">
                        View plan and billing &rarr;
                    </a>
                </x-ui.card>

                <x-ui.card title="Security">
                    <p class="text-sm text-ink-muted">
                        Keep your password secure and avoid sharing it with others.
                    </p>
                    <p class="mt-4 text-xs text-ink-subtle">
                        Session management arrives in a later phase.
                    </p>
                </x-ui.card>
            </div>
        </div>
    </div>
</x-layouts.app>
