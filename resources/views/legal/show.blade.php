<x-layouts.public :title="$type->label()">
    <article class="rounded-3xl border border-white/60 bg-white/70 p-8 shadow-xl shadow-amber-900/5 backdrop-blur-xl sm:p-10">
        <h1 class="text-3xl font-semibold tracking-tight text-stone-900">{{ $type->label() }}</h1>

        @if ($document && filled($document->content))
            <div class="legal-content mt-8">
                {!! $document->content !!}
            </div>
        @else
            <p class="mt-6 text-stone-500">Treść jest w przygotowaniu i pojawi się wkrótce.</p>
        @endif
    </article>
</x-layouts.public>
