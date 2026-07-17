<div>
    <div class="flex items-center gap-3 mt-6">
        <span class="text-sm font-semibold">Jumlah</span>
        <div class="flex items-center border border-border rounded-lg overflow-hidden">
            <button wire:click="decrement" class="w-10 h-10 flex items-center justify-center bg-background hover:bg-border transition-colors duration-150 text-lg font-semibold cursor-pointer">−</button>
            <input type="text" value="{{ $quantity }}" readonly
                   class="w-14 h-10 text-center border-x border-border text-base font-semibold bg-white">
            <button wire:click="increment" class="w-10 h-10 flex items-center justify-center bg-background hover:bg-border transition-colors duration-150 text-lg font-semibold cursor-pointer">+</button>
        </div>
    </div>

    <button wire:click="add"
            class="mt-4 w-full py-3 bg-primary text-white font-semibold rounded-lg hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-ring/20 transition-all duration-150 cursor-pointer">
        + Keranjang
    </button>
</div>
