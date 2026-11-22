<x-layouts.guest title="Forgot password" description="Enter your email address and we'll send you a reset link.">
    @if (session('status'))
        <div class="mb-4">
            <x-ui.alert variant="success">{{ session('status') }}</x-ui.alert>
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf

        <x-ui.input
            name="email"
            label="Email address"
            type="email"
            autocomplete="username"
            required
            autofocus
        />

        <x-ui.button type="submit" size="lg" class="w-full">Email password reset link</x-ui.button>
    </form>

    <p class="mt-6 text-center text-sm text-ink-muted">
        <a href="{{ route('login') }}" class="font-medium text-brand-600 hover:text-brand-700">Back to log in</a>
    </p>
</x-layouts.guest>
