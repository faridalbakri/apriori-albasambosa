<nav class="sticky top-0 z-30 border-b border-border bg-white" x-data="{ open: false }">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            {{-- Logo --}}
            <a href="{{ route('home') }}" class="text-xl font-bold text-primary whitespace-nowrap font-[family-name:var(--font-brand)]">
                AlbaSambosa
            </a>

            {{-- Desktop Right Section: Auth + Cart --}}
            <div class="hidden sm:flex items-center gap-2">
                @auth
                    <div class="relative" x-data="{ dropdown: false }">
                        <button @click="dropdown = !dropdown" class="flex items-center gap-1 px-3 py-2 text-sm font-medium rounded-lg hover:bg-background hover:text-primary transition-colors duration-150 cursor-pointer">
                            {{ Auth::user()->name }}
                            <x-heroicon-o-chevron-down class="w-4 h-4" />
                        </button>
                        <div x-show="dropdown" @click.outside="dropdown = false" class="absolute right-0 mt-1 w-48 bg-white border border-border rounded-lg shadow-lg py-1 z-50">
                            <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-sm hover:bg-background transition-colors cursor-pointer">Profil</a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="w-full text-left px-4 py-2 text-sm hover:bg-background transition-colors cursor-pointer">Keluar</button>
                            </form>
                        </div>
                    </div>
                @else
                    <a href="{{ route('login') }}" class="px-3 py-2 text-sm font-medium rounded-lg hover:bg-background hover:text-primary transition-colors duration-150 cursor-pointer">
                        Masuk
                    </a>
                    <a href="{{ route('register') }}" class="px-4 py-2 text-sm font-medium text-white bg-accent rounded-lg hover:opacity-90 transition-opacity duration-150 cursor-pointer">
                        Daftar
                    </a>
                @endauth

                <livewire:cart-badge />
            </div>

            {{-- Mobile: Cart + Hamburger --}}
            <div class="flex items-center gap-1 sm:hidden">
                <livewire:cart-badge />
                <button @click="open = !open" class="p-2 rounded-lg hover:bg-background transition-colors duration-150 cursor-pointer" aria-label="Toggle menu">
                    <x-heroicon-o-bars-3 x-show="!open" class="w-6 h-6" />
                    <x-heroicon-o-x-mark x-show="open" class="w-6 h-6" />
                </button>
            </div>
        </div>

        {{-- Mobile Menu --}}
        <div x-show="open" x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition duration-150 ease-in" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-2" class="sm:hidden pb-4 border-t border-border">
            <div class="flex flex-col items-center gap-2 mt-3">
                @auth
                    <span class="px-3 py-2 text-sm font-medium text-foreground/60">{{ Auth::user()->name }}</span>
                    <a href="{{ route('profile.edit') }}" @click="open = false" class="px-3 py-2 text-sm rounded-lg hover:bg-background transition-colors cursor-pointer">Profil</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="w-full text-left px-3 py-2 text-sm rounded-lg hover:bg-background transition-colors cursor-pointer">Keluar</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" @click="open = false" class="w-full px-4 py-2 text-sm font-medium text-white bg-accent rounded-lg hover:opacity-90 transition-opacity duration-150 cursor-pointer text-center">
                        Masuk
                    </a>
                    <a href="{{ route('register') }}" @click="open = false" class="w-full px-4 py-2 text-sm font-medium text-white bg-accent rounded-lg hover:opacity-90 transition-opacity duration-150 cursor-pointer text-center">
                        Daftar
                    </a>
                @endauth
            </div>
        </div>
    </div>
</nav>
