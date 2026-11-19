<x-layouts.app title="Users">
    <form method="POST" action="{{ route('admin.users.store') }}" class="card mb-8 grid gap-4 sm:grid-cols-2">
        @csrf
        <h2 class="text-lg font-bold sm:col-span-2">Provision account</h2>
        <x-field name="name" label="Name" />
        <x-field name="email" label="Email" type="email" />
        <div>
            <label class="label" for="role">Role</label>
            <select class="input" id="role" name="role" required>@foreach($roles as $r)<option value="{{ $r->value }}">{{ $r->label() }}</option>@endforeach</select>
        </div>
        <x-field name="password" label="Initial password (min 10)" type="password" autocomplete="new-password" />
        <div class="sm:col-span-2"><button class="btn-primary" type="submit">Create account</button></div>
    </form>
    <div class="card overflow-x-auto !p-0">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-soft text-muted"><tr><th class="px-4 py-2">Name</th><th class="px-4 py-2">Email</th><th class="px-4 py-2">Role</th><th class="px-4 py-2">Status</th><th class="px-4 py-2"></th></tr></thead>
            <tbody class="divide-y divide-slate-soft">
            @foreach($users as $u)
                <tr><td class="px-4 py-2">{{ $u->name }}</td><td class="px-4 py-2">{{ $u->email }}</td><td class="px-4 py-2">{{ $u->roleEnum()->label() }}</td>
                    <td class="px-4 py-2"><span class="badge {{ $u->is_active ? 'bg-ok/10 text-ok' : 'bg-bad/10 text-bad' }}">{{ $u->is_active ? 'Active' : 'Inactive' }}</span></td>
                    <td class="px-4 py-2 text-right">@unless($u->is(auth()->user()))
                        <form method="POST" action="{{ route('admin.users.toggle', $u) }}" onsubmit="return confirm('{{ $u->is_active ? 'Deactivate' : 'Activate' }} this account?')">@csrf<button class="btn-ghost !py-1">{{ $u->is_active ? 'Deactivate' : 'Activate' }}</button></form>
                    @endunless</td></tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $users->links() }}</div>
</x-layouts.app>
