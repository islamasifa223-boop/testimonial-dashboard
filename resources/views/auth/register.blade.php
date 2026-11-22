<x-layouts.guest title="Create your account" description="Start collecting customer testimonials in minutes.">
    <form method="POST" action="{{ route('register') }}" class="space-y-5">
        @csrf

        <x-ui.input
            name="name"
            label="Full name"
            type="text"
            autocomplete="name"
            required
            autofocus
        />

        <x-ui.input
            name="email"
            label="Email address"
            type="email"
            autocomplete="username"
            required
        />

        <x-ui.input
            name="password"
            label="Password"
            type="password"
            autocomplete="new-password"
            required
        />

        <x-ui.input
            name="password_confirmation"
            label="Confirm password"
            type="password"
            autocomplete="new-password"
            required
        />

        <x-ui.button type="submit" size="lg" class="w-full">Create account</x-ui.button>
    </form>

    <p class="mt-6 text-center text-sm text-ink-muted">
        Already have an account?
        <a href="{{ route('login') }}" class="font-medium text-brand-600 hover:text-brand-700">Log in</a>
    </p>
</x-layouts.guest>
