<x-layouts.app>
    @section('title', 'AlbaSambosa — Frozen Food Homemade')

    {{-- ================================================================
         Section 1 — Hero (fullscreen)
         ================================================================ --}}
    <section class="relative overflow-hidden text-white py-24 md:py-36 flex flex-col items-center justify-center text-center bg-cover bg-center"
             style="background-image: url('{{ asset('images/hero.webp') }}');">
        {{-- Overlay: dark tint for text readability --}}
        <div class="absolute inset-0 bg-black/30"></div>
        {{-- Overlay: bottom gradient to background color --}}
        <div class="absolute inset-x-0 bottom-0 h-32 bg-gradient-to-t from-[var(--color-background)] to-transparent"></div>
        <div class="relative px-6 py-12 md:px-12 md:py-16">
            <h1 class="text-3xl md:text-5xl lg:text-6xl font-bold font-[family-name:var(--font-heading)] leading-tight max-w-2xl">
                Dari Dapur Kami, ke Meja Anda
            </h1>
            <p class="mt-4 text-base md:text-lg text-white/80 max-w-lg mx-auto">
                Frozen food homemade — halal, fresh, siap dimasak
            </p>
            <a href="#catalog"
               class="inline-flex items-center gap-2 mt-8 px-6 py-3 bg-white text-primary font-semibold rounded-lg hover:bg-white/90 focus:outline-none focus:ring-2 focus:ring-white/50 transition-all duration-200 cursor-pointer">
                Lihat Menu
                <svg class="w-4 h-4 animate-bounce" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
            </a>
        </div>
    </section>

    {{-- ================================================================
         Section 2 — Semua Produk (Livewire catalog)
         ================================================================ --}}
    <livewire:product-catalog />

</x-layouts.app>
