@props(['product', 'cartQuantity' => 0, 'cartId' => null])

<div class="group bg-white rounded-2xl overflow-hidden shadow-md hover:shadow-xl transition-shadow duration-200 flex flex-col">
    <a href="{{ route('catalog.show', $product) }}" class="block">
        @if ($product->image)
            <img src="{{ asset('storage/' . $product->image) }}"
                 alt="{{ $product->name }}"
                 class="w-full aspect-[4/3] object-cover"
                 loading="lazy">
        @else
            <div class="w-full aspect-[4/3] bg-gradient-to-br from-border to-background flex items-center justify-center" aria-label="{{ $product->name }}">
                <x-heroicon-o-shopping-bag class="w-12 h-12 text-foreground/25" />
            </div>
        @endif
    </a>

    <div class="p-4 flex flex-col flex-1">
        <a href="{{ route('catalog.show', $product) }}" class="flex-1">
            <h3 class="text-base font-semibold mb-1 group-hover:text-primary transition-colors duration-150">
                {{ $product->name }}
            </h3>
            <p class="text-xs text-foreground/60 mb-2">{{ $product->category->name }}</p>
            <p class="text-lg font-bold text-primary mb-3">
                Rp {{ number_format($product->price, 0, ',', '.') }}
            </p>
        </a>

        @if ($cartQuantity > 0)
            {{-- Qty selector — product already in cart --}}
            <div class="flex items-center border border-border rounded-lg overflow-hidden">
                <button wire:click="updateQuantity({{ $cartId }}, {{ $cartQuantity - 1 }})"
                        class="w-10 h-9 flex items-center justify-center bg-background hover:bg-border transition-colors duration-150 text-sm font-semibold cursor-pointer">
                    &minus;
                </button>
                <span class="w-full text-center text-sm font-semibold bg-white">{{ $cartQuantity }}</span>
                <button wire:click="updateQuantity({{ $cartId }}, {{ $cartQuantity + 1 }})"
                        class="w-10 h-9 flex items-center justify-center bg-background hover:bg-border transition-colors duration-150 text-sm font-semibold cursor-pointer">
                    +
                </button>
            </div>
        @else
            {{-- Quick-add — product not yet in cart --}}
            <button
                x-data="{ adding: false, message: '' }"
                x-on:click="adding = true; fetch('{{ route('cart.store') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ product_id: {{ $product->id }} })
                })
                .then(r => r.json().then(d => ({ status: r.status, body: d })))
                .then(({ status, body }) => {
                    if (status === 200) {
                        window.dispatchEvent(new CustomEvent('cart-updated'));
                        $wire.$refresh();
                    } else {
                        message = body.message || 'Gagal';
                    }
                })
                .catch(() => message = 'Error')
                .finally(() => { adding = false; setTimeout(() => message = '', 2000) })"
                :disabled="adding"
                class="w-full py-2 text-sm font-semibold rounded-lg border border-accent text-accent hover:bg-accent hover:text-white focus:outline-none focus:ring-2 focus:ring-ring/20 transition-all duration-150 cursor-pointer disabled:opacity-50"
                x-text="message || '+ Keranjang'"
            >+ Keranjang</button>
        @endif
    </div>
</div>
