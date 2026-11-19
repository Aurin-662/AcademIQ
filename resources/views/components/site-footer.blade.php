<footer class="bg-navy text-slate-300">
    <div class="mx-auto grid max-w-6xl gap-8 px-4 py-12 sm:px-6 md:grid-cols-3">
        <div>
            <p class="font-display text-lg font-extrabold text-white">Academi<span class="text-teal">Q</span></p>
            <p class="mt-2 text-sm">Trust Academic Credentials. Verify Without Revealing.</p>
        </div>
        <ul class="space-y-2 text-sm">
            <li><a class="hover:text-white" href="{{ route('how-it-works') }}">How it works</a></li>
            <li><a class="hover:text-white" href="{{ route('privacy') }}">Privacy & security</a></li>
            <li><a class="hover:text-white" href="{{ route('contact') }}">Contact</a></li>
            <li><a class="hover:text-white" href="{{ route('report.verify.form') }}">Verify a report</a></li>
        </ul>
        <p class="text-xs text-slate-400">AcademiQ is the academic credential verification system of Khulna University of Engineering &amp; Technology (KUET). Institutional logo is a replaceable placeholder pending authorized branding files.</p>
    </div>
    <div class="border-t border-white/10 py-4 text-center text-xs text-slate-400">&copy; {{ date('Y') }} AcademiQ</div>
</footer>
