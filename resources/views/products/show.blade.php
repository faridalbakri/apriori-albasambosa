<x-layouts.app>
    @section('title', $product->name . ' — AlbaSambosa')

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        {{-- Back --}}
        <a href="{{ route('catalog.index') }}" class="inline-flex items-center gap-1 text-sm font-medium text-foreground/60 hover:text-primary transition-colors duration-150 mb-6">
            <x-heroicon-o-arrow-left class="w-4 h-4" />
            Kembali ke Menu
        </a>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            {{-- Image --}}
            @if ($product->image)
                <img src="{{ asset('storage/' . $product->image) }}"
                     alt="{{ $product->name }}"
                     class="aspect-square rounded-2xl object-cover w-full">
            @else
                <div class="aspect-square bg-gradient-to-br from-background to-border rounded-2xl flex items-center justify-center">
                    <x-heroicon-o-photo class="w-24 h-24 text-foreground/20" />
                </div>
            @endif

            {{-- Info --}}
            <div>
                <h1 class="text-2xl md:text-3xl font-bold font-[family-name:var(--font-heading)]">{{ $product->name }}</h1>

                <div class="flex gap-2 mt-3">
                    <span class="px-3 py-1 rounded-full text-xs font-semibold bg-background text-primary">
                        {{ $product->category->name }}
                    </span>
                    @if ($product->total_sold > 50)
                        <span class="px-3 py-1 rounded-full text-xs font-semibold bg-accent/10 text-accent">
                            Best Seller
                        </span>
                    @endif
                </div>

                <p class="text-3xl font-bold text-primary mt-4">
                    Rp {{ number_format($product->price, 0, ',', '.') }}
                </p>

                <div class="flex gap-6 mt-4 text-sm text-foreground/60">
                    <span>Stok: <strong class="text-foreground">{{ max(0, $product->stock - $product->stock_reserved) }}</strong></span>
                    <span>Terjual: <strong class="text-foreground">{{ $product->total_sold }}</strong></span>
                </div>

                <p class="text-sm leading-relaxed py-4 border-y border-border mt-4 text-foreground/80">
                    {{ $product->description }}
                </p>

                @if (max(0, $product->stock - $product->stock_reserved) > 0)
                    <livewire:add-to-cart :product="$product" />
                @else
                    <p class="mt-6 py-3 text-center bg-muted text-foreground/60 rounded-lg font-semibold">Stok Habis</p>
                @endif
            </div>
        </div>

        {{-- Related Products --}}
        @if ($recommended->isNotEmpty())
            <section class="mt-12">
                <h2 class="text-xl font-bold font-[family-name:var(--font-heading)] mb-4">Beli Bersama</h2>
                <div class="flex gap-4 overflow-x-auto pb-2">
                    @foreach ($recommended as $item)
                        <a href="{{ route('catalog.show', $item) }}"
                           class="flex-shrink-0 w-[280px] sm:w-[320px] flex items-center gap-3 p-3 border border-border rounded-xl hover:shadow-md transition-shadow duration-200">
                            @if ($item->image)
                                <img src="{{ asset('storage/' . $item->image) }}"
                                     alt="{{ $item->name }}"
                                     class="w-14 h-14 rounded-lg object-cover">
                            @else
                                <div class="w-14 h-14 rounded-lg bg-background flex items-center justify-center">
                                    <x-heroicon-o-photo class="w-6 h-6 text-foreground/20" />
                                </div>
                            @endif
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
</x-layouts.app>
