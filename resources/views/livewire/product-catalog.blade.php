<section id="catalog" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-10 mb-12">
    <h2 class="text-xl md:text-2xl font-bold font-[family-name:var(--font-heading)] text-foreground mb-4">
        Semua Produk
    </h2>

    {{-- Search bar --}}
    <div class="mb-4">
        <div class="relative">
            <x-heroicon-o-magnifying-glass class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-foreground/40 pointer-events-none" />
            <input type="text" wire:model.live.debounce.300ms="search"
                   placeholder="Cari produk..."
                   class="w-full pl-10 pr-4 py-3 bg-white border border-border rounded-xl text-foreground placeholder:text-foreground/40 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all duration-150">
        </div>
    </div>

    {{-- Category pills + Sort in one row --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        {{-- Category pills — horizontal scroll --}}
        <div class="flex gap-2 overflow-x-auto scrollbar-none flex-1"
             style="scrollbar-width: none; -ms-overflow-style: none;">
            <button wire:click="selectCategory('')"
                    class="flex-shrink-0 px-4 py-2 rounded-full text-sm font-semibold border border-border transition-colors duration-150 cursor-pointer
                           {{ !$category ? 'bg-primary text-white border-primary' : 'bg-white text-foreground hover:bg-background' }}">
                Semua
            </button>
            @foreach ($categories as $cat)
                <button wire:click="selectCategory('{{ $cat->slug }}')"
                        class="flex-shrink-0 px-4 py-2 rounded-full text-sm font-semibold border border-border transition-colors duration-150 cursor-pointer
                               {{ $category === $cat->slug ? 'bg-primary text-white border-primary' : 'bg-white text-foreground hover:bg-background' }}">
                    {{ $cat->name }}
                </button>
            @endforeach
        </div>

        {{-- Sort dropdown --}}
        <div class="flex-shrink-0">
            <select wire:model.live="sort"
                    class="px-3 py-2 bg-white border border-border rounded-lg text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 cursor-pointer">
                <option value="terlaris">Paling Laris</option>
                <option value="terbaru">Terbaru</option>
                <option value="termurah">Termurah</option>
            </select>
        </div>
    </div>

    {{-- Product grid --}}
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 md:gap-6">
        @forelse ($products as $product)
            @php
                $cartItem = $cartItems->get($product->id);
            @endphp
            <x-product-card
                :product="$product"
                :cart-quantity="$cartItem->quantity ?? 0"
                :cart-id="$cartItem->id ?? null"
            />
        @empty
            <p class="col-span-full text-center py-12 text-foreground/60">
                Belum ada produk tersedia.
            </p>
        @endforelse
    </div>

    {{-- Pagination --}}
    <div class="mt-8 flex justify-center">
        {{ $products->links() }}
    </div>
</section>
