<x-layouts.guest title="Log in" description="Welcome back. Sign in to your account.">
    @if (session('status'))
        <div class="mb-4">
            <x-ui.alert variant="success">{{ session('status') }}</x-ui.alert>
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <x-ui.input
            name="email"
            label="Email address"
            type="email"
            autocomplete="username"
            required
            autofocus
        />

        <div>
            <div class="mb-1.5 flex items-center justify-between">
                <x-ui.label for="password" :required="true">Password</x-ui.label>
                <a href="{{ route('password.request') }}" class="text-xs font-medium text-brand-600 hover:text-brand-700">Forgot password?</a>
            </div>
            <x-ui.input
                name="password"
                type="password"
                autocomplete="current-password"
                required
            />
        </div>

        <label class="flex items-center gap-2 text-sm text-ink-muted">
            <input type="checkbox" name="remember" value="1" class="size-4 rounded border-border-subtle text-brand-600 focus:ring-brand-500">
            Remember me
        </label>

        <x-ui.button type="submit" size="lg" class="w-full">Log in</x-ui.button>
    </form>

    <p class="mt-6 text-center text-sm text-ink-muted">
        Don't have an account?
        <a href="{{ route('register') }}" class="font-medium text-brand-600 hover:text-brand-700">Sign up</a>
    </p>
</x-layouts.guest>
