<div>
    <a href="{{ route('cart.index') }}" class="relative inline-flex items-center justify-center w-9 h-9 rounded-lg hover:bg-background transition-colors duration-150 cursor-pointer" aria-label="Keranjang belanja">
        <x-heroicon-o-shopping-cart class="w-6 h-6 text-foreground" />
        @if ($count > 0)
            <span class="absolute -top-0.5 -right-0.5 bg-accent text-white text-[11px] font-bold min-w-[20px] h-[20px] rounded-full flex items-center justify-center px-1 shadow-sm">
                {{ $count }}
            </span>
        @endif
    </a>
</div>
