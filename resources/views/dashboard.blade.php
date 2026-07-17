<x-layouts.app>
    @section('title', 'Dashboard — AlbaSambosa')

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="bg-white rounded-2xl shadow-md p-6 text-center">
            <p class="text-[var(--color-foreground)]/60">{{ __("You're logged in!") }}</p>
            <a href="{{ route('catalog.index') }}" class="inline-block mt-4 px-6 py-2 bg-[var(--color-accent)] text-white font-semibold rounded-lg hover:opacity-90 transition-opacity">
                Lihat Menu
            </a>
        </div>
    </div>
</x-layouts.app>
