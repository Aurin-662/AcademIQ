<x-layouts.public title="Create account">
<section class="mx-auto max-w-md px-4 py-14 sm:px-6" x-data="{ role: '{{ old('role', 'student') }}' }">
    <h1 class="mb-2 text-2xl font-bold">Create account</h1>
    <p class="mb-6 text-sm text-muted">Students and employers can register here. Admin, issuer and verification-portal accounts are provisioned by KUET.</p>
    <form method="POST" action="{{ route('register') }}" class="card space-y-4">
        @csrf
        <fieldset>
            <legend class="label">I am a</legend>
            <div class="flex gap-4 text-sm">
                <label class="flex items-center gap-2"><input type="radio" name="role" value="student" x-model="role"> Student</label>
                <label class="flex items-center gap-2"><input type="radio" name="role" value="employer" x-model="role"> Employer</label>
            </div>
            @error('role')<p class="mt-1 text-sm text-bad">{{ $message }}</p>@enderror
        </fieldset>
        <x-field name="name" label="Full name" autocomplete="name" />
        <x-field name="email" label="Email" type="email" autocomplete="email" />
        <div x-show="role === 'student'" class="space-y-4">
            <x-field name="student_reference" label="Student reference (e.g. roll number)" x-bind:required="role === 'student'" />
            <div><label class="label" for="department">Department (optional)</label><input class="input" id="department" name="department" value="{{ old('department') }}"></div>
        </div>
        <div x-show="role === 'employer'" x-cloak>
            <x-field name="company_name" label="Company name" x-bind:required="role === 'employer'" />
        </div>
        <x-field name="password" label="Password (min 10 characters)" type="password" autocomplete="new-password" />
        <x-field name="password_confirmation" label="Confirm password" type="password" autocomplete="new-password" />
        <button class="btn-primary w-full" type="submit">Create account</button>
        <p class="text-sm text-muted">Already registered? <a class="underline" href="{{ route('login') }}">Sign in</a></p>
    </form>
</section>
</x-layouts.public>
