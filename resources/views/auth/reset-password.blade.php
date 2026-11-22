<x-layouts.guest title="Reset password" description="Choose a new password for your account.">
    <form method="POST" action="{{ route('password.store') }}" class="space-y-5">
        @csrf

        <input type="hidden" name="token" value="{{ $token }}">

        <x-ui.input
            name="email"
            label="Email address"
            type="email"
            :value="$email ?? old('email')"
            autocomplete="username"
            required
            autofocus
        />

        <x-ui.input
            name="password"
            label="New password"
            type="password"
            autocomplete="new-password"
            required
        />

        <x-ui.input
            name="password_confirmation"
            label="Confirm new password"
            type="password"
            autocomplete="new-password"
            required
        />

        <x-ui.button type="submit" size="lg" class="w-full">Reset password</x-ui.button>
    </form>
</x-layouts.guest>
