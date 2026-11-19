<x-layouts.app title="Notifications">
    <form method="POST" action="{{ route('notifications.read') }}" class="mb-4">@csrf<button class="btn-ghost" type="submit">Mark all as read</button></form>
    <div class="card divide-y divide-slate-soft !p-0">
        @forelse($items as $n)
            <form method="POST" action="{{ route('notifications.read', $n->getKey()) }}">
                @csrf
                <button type="submit" class="flex w-full items-start justify-between gap-4 px-4 py-3 text-left text-sm hover:bg-slate-soft {{ $n->read_at ? 'text-muted' : '' }}">
                    <span><span class="{{ $n->read_at ? '' : 'font-semibold text-navy' }}">{{ $n->title }}</span><br><span class="text-xs">{{ $n->body }}</span></span>
                    <span class="whitespace-nowrap text-xs text-muted">{{ $n->created_at?->format('Y-m-d H:i') }}</span>
                </button>
            </form>
        @empty
            <p class="px-4 py-8 text-center text-sm text-muted">You have no notifications.</p>
        @endforelse
    </div>
    <div class="mt-4">{{ $items->links() }}</div>
</x-layouts.app>
