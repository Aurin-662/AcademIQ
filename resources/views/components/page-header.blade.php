@props(['title', 'lead' => null])
<section class="bg-gradient-to-br from-navy to-kuet text-white">
    <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6 fade-in">
        <h1 class="max-w-3xl text-3xl font-extrabold !text-white sm:text-4xl">{{ $title }}</h1>
        @if($lead)<p class="mt-4 max-w-2xl text-lg text-slate-200">{{ $lead }}</p>@endif
    </div>
</section>
