<div>
    <h1 class="text-2xl font-bold font-[family-name:var(--font-heading)] mb-8">Keranjang Belanja</h1>

    @if ($cartItems->isEmpty())
        <div class="text-center py-20">
            <div class="mx-auto w-32 h-32 mb-6 rounded-full bg-background flex items-center justify-center">
                <svg class="w-16 h-16 text-foreground/25" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                </svg>
            </div>
            <p class="text-foreground/70 text-lg mb-2">Keranjang masih kosong</p>
            <p class="text-foreground/50 text-sm mb-6">Yuk, isi dengan produk favoritmu!</p>
            <a href="{{ route('catalog.index') }}"
               class="inline-block px-8 py-3 bg-accent text-white font-semibold rounded-lg hover:bg-accent/90 transition-all duration-200 cursor-pointer active:scale-[0.98]">
                Lihat Menu
            </a>
        </div>
    @else
        <div class="bg-white rounded-2xl shadow-md overflow-hidden overflow-x-auto">
            <table class="w-full">
                <thead class="bg-background text-sm text-foreground/60">
                    <tr>
                        <th class="text-left p-5 font-medium">Produk</th>
                        <th class="text-center p-5 font-medium">Jumlah</th>
                        <th class="text-right p-5 font-medium">Subtotal</th>
                        <th class="p-5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach ($cartItems as $item)
                        <tr class="hover:bg-background/40 transition-colors duration-150">
                            <td class="p-5">
                                <div class="flex items-center gap-4">
                                    <img src="{{ $item->product->image_url }}"
                                         alt="{{ $item->product->name }}"
                                         class="w-14 h-14 rounded-xl object-cover bg-background flex-shrink-0"
                                         loading="lazy" />
                                    <div>
                                        <a href="{{ route('catalog.show', $item->product) }}"
                                           class="font-semibold text-sm hover:text-primary transition-colors duration-150 cursor-pointer">
                                            {{ $item->product->name }}
                                        </a>
                                        <p class="text-xs text-foreground/60 mt-0.5">Rp {{ number_format($item->price, 0, ',', '.') }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="p-5">
                                <div class="flex items-center justify-center border border-border rounded-lg overflow-hidden w-fit mx-auto">
                                    <button wire:click="updateQuantity({{ $item->id }}, {{ $item->quantity - 1 }})"
                                            aria-label="Kurangi jumlah {{ $item->product->name }}"
                                            class="w-11 h-11 flex items-center justify-center bg-background hover:bg-border focus:ring-2 focus:ring-ring/20 focus:z-10 transition-colors duration-150 cursor-pointer">−</button>
                                    <span class="w-10 h-11 flex items-center justify-center border-x border-border text-sm font-semibold">{{ $item->quantity }}</span>
                                    <button wire:click="updateQuantity({{ $item->id }}, {{ $item->quantity + 1 }})"
                                            aria-label="Tambah jumlah {{ $item->product->name }}"
                                            class="w-11 h-11 flex items-center justify-center bg-background hover:bg-border focus:ring-2 focus:ring-ring/20 focus:z-10 transition-colors duration-150 cursor-pointer">+</button>
                                </div>
                            </td>
                            <td class="p-5 text-right font-semibold text-sm">
                                Rp {{ number_format($item->price * $item->quantity, 0, ',', '.') }}
                            </td>
                            <td class="p-5 text-center">
                                <button wire:click="remove({{ $item->id }})"
                                        aria-label="Hapus {{ $item->product->name }} dari keranjang"
                                        class="w-11 h-11 flex items-center justify-center rounded-lg text-foreground/40 hover:text-destructive hover:bg-destructive/5 focus:ring-2 focus:ring-destructive/20 transition-all duration-200 cursor-pointer">
                                    <x-heroicon-o-trash class="w-4 h-4" />
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="bg-white rounded-2xl shadow-md p-6 mt-5">
            <div class="flex justify-between items-center text-lg font-bold">
                <span class="text-foreground/70">Total</span>
                <span class="text-primary text-xl">Rp {{ number_format($total, 0, ',', '.') }}</span>
            </div>
            <a href="{{ route('checkout.index') }}"
               class="block mt-5 text-center py-3.5 bg-accent text-white font-semibold rounded-lg hover:bg-accent/90 transition-all duration-200 cursor-pointer active:scale-[0.98]">
                Lanjut ke Checkout
            </a>
        </div>
    @endif

    {{-- Recommendations --}}
    @if ($recommendations->isNotEmpty())
        <section class="mt-10">
            <h2 class="text-lg font-bold font-[family-name:var(--font-heading)] mb-4">Mungkin Anda Suka</h2>
            <div class="flex gap-4 overflow-x-auto pb-2">
                @foreach ($recommendations as $item)
                    <a href="{{ route('catalog.show', $item) }}"
                       class="flex-shrink-0 w-[260px] sm:w-[280px] flex items-center gap-3 p-3 border border-border rounded-xl hover:shadow-md transition-all duration-200 cursor-pointer">
                        <img src="{{ $item->image_url }}"
                             alt="{{ $item->name }}"
                             class="w-16 h-16 rounded-lg object-cover bg-background flex-shrink-0"
                             loading="lazy" />
                        <div>
                            <p class="text-sm font-medium">{{ $item->name }}</p>
                            <p class="text-sm font-bold text-primary">Rp {{ number_format($item->price, 0, ',', '.') }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
</div>
