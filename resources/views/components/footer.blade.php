<footer class="border-t border-border bg-white mt-auto">
    {{-- 4-column grid (desktop), stack (mobile) --}}
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
        {{-- Column 1: Brand --}}
        <div>
            <a href="{{ route('home') }}" class="text-lg font-bold text-primary font-[family-name:var(--font-brand)]">
                AlbaSambosa
            </a>
            <p class="mt-2 text-sm text-foreground/60 leading-relaxed">
                Frozen Food UMKM Asli sejak 2023
            </p>
            {{-- Social Media --}}
            <div class="flex items-center gap-3 mt-4">
                @if(config('app.social.instagram') !== '#')
                <a href="{{ config('app.social.instagram') }}" target="_blank" rel="noopener noreferrer" aria-label="Instagram AlbaSambosa" class="text-foreground/60 hover:text-foreground transition-colors duration-200 cursor-pointer">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                </a>
                @endif
                @if(config('app.social.tiktok') !== '#')
                <a href="{{ config('app.social.tiktok') }}" target="_blank" rel="noopener noreferrer" aria-label="TikTok AlbaSambosa" class="text-foreground/60 hover:text-foreground transition-colors duration-200 cursor-pointer">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/></svg>
                </a>
                @endif
                @if(config('app.social.facebook') !== '#')
                <a href="{{ config('app.social.facebook') }}" target="_blank" rel="noopener noreferrer" aria-label="Facebook AlbaSambosa" class="text-foreground/60 hover:text-foreground transition-colors duration-200 cursor-pointer">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                </a>
                @endif
            </div>
        </div>

        {{-- Column 2: Navigasi --}}
        <div>
            <h3 class="text-sm font-bold text-foreground uppercase tracking-wider font-[family-name:var(--font-heading)]">Navigasi</h3>
            <ul class="mt-3 space-y-2">
                <li><a href="{{ route('catalog.index') }}" class="text-sm text-foreground/60 hover:text-primary transition-colors duration-150 cursor-pointer">Menu</a></li>
                <!-- placeholder link, page built when content is ready -->
                <li><a href="{{ route('home') }}#tentang-kami" class="text-sm text-foreground/60 hover:text-primary transition-colors duration-150 cursor-pointer">Tentang Kami</a></li>
                <!-- placeholder link, page built when content is ready -->
                <li><a href="{{ route('home') }}#cara-pesan" class="text-sm text-foreground/60 hover:text-primary transition-colors duration-150 cursor-pointer">Cara Pesan</a></li>
                <li><a href="{{ route('orders.track') }}" class="text-sm text-foreground/60 hover:text-primary transition-colors duration-150 cursor-pointer">Lacak Pesanan</a></li>
                <li><a href="https://wa.me/6285780159620?text=Halo%20AlbaSambosa,%20saya%20ingin%20menyampaikan%20kritik%20dan%20saran" target="_blank" rel="noopener noreferrer" class="text-sm text-foreground/60 hover:text-primary transition-colors duration-150 cursor-pointer">Kritik & Saran</a></li>
            </ul>
        </div>

        {{-- Column 3: Info Toko --}}
        <div>
            <h3 class="text-sm font-bold text-foreground uppercase tracking-wider font-[family-name:var(--font-heading)]">Info Toko</h3>
            <ul class="mt-3 space-y-2 text-sm text-foreground/60">
                <li class="flex items-start gap-2">
                    <x-heroicon-o-map-pin class="w-4 h-4 mt-0.5 shrink-0 text-foreground/40" />
                    <span>Jl. G No.120, RT.8/RW.6, Srengseng, Kec. Kembangan, Jakarta Barat, 11630</span>
                </li>
                <li class="flex items-start gap-2">
                    <x-heroicon-o-clock class="w-4 h-4 mt-0.5 shrink-0 text-foreground/40" />
                    <span>09:00 - 20:00 WIB</span>
                </li>
                <li class="flex items-start gap-2">
                    <x-heroicon-o-phone class="w-4 h-4 mt-0.5 shrink-0 text-foreground/40" />
                    <a href="https://wa.me/6285780159620" target="_blank" rel="noopener noreferrer" class="hover:text-primary transition-colors duration-150 cursor-pointer">
                        085780159620
                    </a>
                </li>
            </ul>
        </div>

    </div>

    {{-- Bottom bar --}}
    <div class="border-t border-border">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex flex-wrap justify-center gap-x-6 gap-y-1 text-sm text-foreground/50">
            <span class="font-[family-name:var(--font-brand)]">&copy; {{ date('Y') }} AlbaSambosa</span>
            <a href="{{ route('pages.privacy') }}" class="hover:text-primary transition-colors duration-150 cursor-pointer">Kebijakan Privasi</a>
            <a href="{{ route('pages.cookie') }}" class="hover:text-primary transition-colors duration-150 cursor-pointer">Kebijakan Cookie</a>
            <a href="{{ route('pages.terms') }}" class="hover:text-primary transition-colors duration-150 cursor-pointer">Syarat & Ketentuan</a>
        </div>
    </div>
</footer>
