<x-layouts.public title="Choose new password">
<section class="mx-auto max-w-md px-4 py-14 sm:px-6">
    <h1 class="mb-6 text-2xl font-bold">Choose a new password</h1>
    <form method="POST" action="{{ route('password.update') }}" class="card space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-field name="email" label="Email" type="email" :value="$email" />
        <x-field name="password" label="New password (min 10 characters)" type="password" autocomplete="new-password" />
        <x-field name="password_confirmation" label="Confirm password" type="password" autocomplete="new-password" />
        <button class="btn-primary w-full" type="submit">Update password</button>
    </form>
</section>
</x-layouts.public>
