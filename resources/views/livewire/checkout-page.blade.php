<div>
    {{-- Back to Cart --}}
    <a href="{{ route('cart.index') }}"
       class="inline-flex items-center gap-2 px-4 py-2 bg-background text-foreground font-semibold rounded-lg border border-border hover:bg-border transition-colors duration-150 text-sm mb-6">
        ← Kembali ke Keranjang
    </a>

    {{-- Header --}}
    <h1 class="text-2xl font-bold font-[family-name:var(--font-heading)] mb-8">Checkout</h1>

    @if ($cartItems->isEmpty())
        <div class="text-center py-16">
            <p class="text-foreground/60 text-lg mb-4">Keranjang masih kosong</p>
            <a href="{{ route('catalog.index') }}"
               class="inline-block px-6 py-3 bg-primary text-white font-semibold rounded-lg hover:bg-primary/90 transition-colors duration-150">
                Lihat Menu
            </a>
        </div>
    @else
        <form wire:submit="checkout" class="space-y-5">
            {{-- Shipping Method --}}
            <div class="bg-white rounded-2xl shadow-md p-6">
                <h2 class="text-lg font-semibold font-[family-name:var(--font-heading)] mb-4">Metode Pengambilan</h2>

                <div class="flex rounded-lg overflow-hidden border border-border">
                    <label class="flex-1 px-4 py-3 text-center text-sm font-semibold cursor-pointer transition-colors duration-150
                                  {{ $shippingMethod === 'pickup' ? 'bg-primary text-white' : 'bg-white text-foreground hover:bg-background' }}">
                        <input type="radio" wire:model.live="shippingMethod" value="pickup" class="hidden">
                        Pickup
                    </label>
                    <label class="flex-1 px-4 py-3 text-center text-sm font-semibold transition-colors duration-150
                                  {{ $deliveryUnavailable ? 'bg-gray-100 text-foreground/40 cursor-not-allowed' : ($shippingMethod === 'delivery' ? 'bg-primary text-white cursor-pointer' : 'bg-white text-foreground hover:bg-background cursor-pointer') }}">
                        <input type="radio" wire:model.live="shippingMethod" value="delivery" class="hidden"
                               {{ $deliveryUnavailable ? 'disabled' : '' }}>
                        Delivery
                    </label>
                </div>

                {{-- Delivery Unavailable Warning --}}
                @if ($deliveryUnavailable)
                    <div class="mt-3 flex items-start gap-2 text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-4 py-3">
                        <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                        </svg>
                        <span>Layanan delivery sedang tidak tersedia. Silakan pilih <strong>Pickup</strong> (ambil langsung di toko).</span>
                    </div>
                @endif

                {{-- Pickup Section --}}
                @if ($shippingMethod === 'pickup')
                    <div class="mt-4 grid grid-cols-1 @auth sm:grid-cols-3 @else sm:grid-cols-2 @endauth gap-4">
                        <div class="flex flex-col gap-1">
                            <label class="text-sm font-semibold text-foreground">Tanggal</label>
                            <input type="date" wire:model.live="pickupDate"
                                   min="{{ now()->format('Y-m-d') }}"
                                   class="px-3 py-2.5 border rounded-lg text-sm bg-white
                                          focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10
                                          @error('pickupDate') border-red-500 ring-2 ring-red-500/20 @else border-border @enderror">
                            @error('pickupDate') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-sm font-semibold text-foreground">Jam</label>
                            <select wire:model="pickupTime"
                                    class="px-3 py-2.5 border rounded-lg text-sm bg-white
                                           focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10
                                           @error('pickupTime') border-red-500 ring-2 ring-red-500/20 @else border-border @enderror">
                                <option value="">-- Pilih jam --</option>
                                @foreach ($pickupSlots as $slot)
                                    <option value="{{ $slot['value'] }}">{{ $slot['label'] }}</option>
                                @endforeach
                            </select>
                            @error('pickupTime') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        </div>
                        @auth
                            <div class="flex flex-col gap-1">
                                <label class="text-sm font-semibold text-foreground">Nomor Telepon</label>
                                <input type="tel" wire:model="recipientPhone"
                                       placeholder="+6281234567890"
                                       class="px-3 py-2.5 border rounded-lg text-sm bg-white
                                              focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10
                                              @error('recipientPhone') border-red-500 ring-2 ring-red-500/20 @else border-border @enderror">
                                
                                @error('recipientPhone') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                            </div>
                        @endauth
                    </div>
                @endif

                {{-- Delivery Section --}}
                @if ($shippingMethod === 'delivery')
                    <div class="mt-4 space-y-3">
                        <div class="flex flex-col gap-1">
                            <label class="text-sm font-semibold text-foreground">Nama Penerima</label>
                            <input type="text" wire:model="recipientName"
                                   placeholder="Nama lengkap penerima"
                                   class="px-3 py-2.5 border rounded-lg text-sm bg-white
                                          focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10
                                          @error('recipientName') border-red-500 ring-2 ring-red-500/20 @else border-border @enderror">
                            @error('recipientName') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-sm font-semibold text-foreground">Nomor Telepon</label>
                            <input type="tel" wire:model="recipientPhone"
                                   placeholder="+6281234567890"
                                   class="px-3 py-2.5 border rounded-lg text-sm bg-white
                                          focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10
                                          @error('recipientPhone') border-red-500 ring-2 ring-red-500/20 @else border-border @enderror">
                            @error('recipientPhone') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-sm font-semibold text-foreground">Kode Pos Tujuan</label>
                            <input type="text" wire:model.live.debounce.500ms="destinationPostalCode"
                                   placeholder="Contoh: 12950"
                                   maxlength="10"
                                   class="px-3 py-2.5 border rounded-lg text-sm bg-white
                                          focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10
                                          @error('destinationPostalCode') border-red-500 ring-2 ring-red-500/20 @else border-border @enderror">
                            @error('destinationPostalCode') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-sm font-semibold text-foreground">Alamat Lengkap</label>
                            <textarea wire:model="addressDetail" rows="3"
                                      placeholder="Jalan, nomor, RT/RW, kelurahan, kecamatan, kota"
                                      class="px-3 py-2.5 border rounded-lg text-sm bg-white
                                             focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10
                                             @error('addressDetail') border-red-500 ring-2 ring-red-500/20 @else border-border @enderror"></textarea>
                            @error('addressDetail') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        </div>

                        {{-- Courier Rates --}}
                        @if ($isLoadingRates)
                            <div class="flex items-center gap-2 py-3 text-sm text-foreground/60">
                                <svg class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                Mengecek ongkir...
                            </div>
                        @elseif (!empty($availableRates))
                            <div class="space-y-2 @error('selectedRateKey') border-2 border-red-500 rounded-lg p-3 @enderror">
                                <label class="text-sm font-semibold text-foreground">Pilih Kurir</label>
                                @foreach ($availableRates as $rate)
                                    @php
                                        $key = $rate['courier_code'] . '|' . $rate['courier_service_code'];
                                        $isSelected = $selectedRateKey === $key;
                                    @endphp
                                    <label
                                        wire:click="selectRate(@js($rate['courier_code']), @js($rate['courier_service_code']))"
                                        class="flex items-center justify-between px-4 py-3 border rounded-lg cursor-pointer transition-colors duration-150
                                               {{ $isSelected ? 'border-primary bg-primary/5 ring-2 ring-primary/10' : 'border-border hover:bg-background' }}">
                                        <div>
                                            <span class="text-sm font-semibold text-foreground">{{ $rate['courier_name'] }} — {{ $rate['courier_service_name'] }}</span>
                                            <span class="block text-xs text-foreground/60">{{ $rate['duration'] }}</span>
                                        </div>
                                        <span class="text-sm font-bold text-foreground">Rp {{ number_format($rate['price'], 0, ',', '.') }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @error('selectedRateKey') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        @elseif ($destinationPostalCode && strlen($destinationPostalCode) >= 4)
                            <div class="flex items-start gap-2 text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-4 py-3">
                                <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <span>GoSend/Grab tidak tersedia untuk kode pos ini. Silakan pilih <strong>Pickup</strong> (ambil langsung di toko).</span>
                            </div>
                        @endif

                        @guest
                            <label class="flex items-start gap-2 cursor-pointer">
                                <input type="checkbox" wire:model="pdpConsent"
                                       class="mt-0.5 accent-primary
                                              @error('pdpConsent') border-2 border-red-500 rounded @enderror">
                                <span class="text-sm text-foreground/70">
                                    Saya menyetujui <span class="text-primary underline">Kebijakan Privasi</span> dan
                                    pemrosesan data pribadi sesuai UU PDP
                                </span>
                            </label>
                            @error('pdpConsent') <span class="text-xs text-red-500 block">{{ $message }}</span> @enderror
                        @endguest
                    </div>
                @endif
            </div>

            {{-- Guest Info: pickup only (delivery uses the same fields from delivery form) --}}
            @guest
                @if ($shippingMethod === 'pickup')
                    <div class="bg-white rounded-2xl shadow-md p-6">
                        <h2 class="text-lg font-semibold font-[family-name:var(--font-heading)] mb-4">Data Diri</h2>

                        <div class="space-y-3">
                            <div class="flex flex-col gap-1">
                                <label class="text-sm font-semibold text-foreground">Nama Lengkap</label>
                                <input type="text" wire:model="guestName"
                                       placeholder="Nama Anda"
                                       class="px-3 py-2.5 border rounded-lg text-sm bg-white
                                              focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10
                                              @error('guestName') border-red-500 ring-2 ring-red-500/20 @else border-border @enderror">
                                @error('guestName') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                            </div>
                            <div class="flex flex-col gap-1">
                                <label class="text-sm font-semibold text-foreground">Nomor Telepon</label>
                                <input type="tel" wire:model="guestPhone"
                                       placeholder="+6281234567890"
                                       class="px-3 py-2.5 border rounded-lg text-sm bg-white
                                              focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10
                                              @error('guestPhone') border-red-500 ring-2 ring-red-500/20 @else border-border @enderror">
                                @error('guestPhone') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                            </div>
                            <label class="flex items-start gap-2 cursor-pointer">
                                <input type="checkbox" wire:model="pdpConsent"
                                       class="mt-0.5 accent-primary
                                              @error('pdpConsent') border-2 border-red-500 rounded @enderror">
                                <span class="text-sm text-foreground/70">
                                    Saya menyetujui <span class="text-primary underline">Kebijakan Privasi</span> dan
                                    pemrosesan data pribadi sesuai UU PDP
                                </span>
                            </label>
                            @error('pdpConsent') <span class="text-xs text-red-500 block">{{ $message }}</span> @enderror
                        </div>
                    </div>
                @endif
            @endguest

            {{-- Order Summary --}}
            <div class="bg-white rounded-2xl shadow-md p-6">
                <h2 class="text-lg font-semibold font-[family-name:var(--font-heading)] mb-4">Ringkasan Pesanan</h2>

                @foreach ($cartItems as $item)
                    <div class="flex justify-between py-2 text-sm">
                        <span class="text-foreground">{{ $item->product->name }} ({{ $item->quantity }}x)</span>
                        <span class="font-semibold">Rp {{ number_format($item->price * $item->quantity, 0, ',', '.') }}</span>
                    </div>
                @endforeach

                <hr class="border-border my-3">

                <div class="flex justify-between py-1 text-sm text-foreground/60">
                    <span>Subtotal</span>
                    <span>Rp {{ number_format($subtotal, 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between py-1 text-sm text-foreground/60">
                    <span>Ongkos Kirim</span>
                    <span>{{ $this->shippingCost > 0 ? 'Rp ' . number_format($this->shippingCost, 0, ',', '.') : 'Gratis' }}</span>
                </div>

                <hr class="border-2 border-border my-3">

                <div class="flex justify-between text-lg font-bold text-primary">
                    <span>Total</span>
                    <span>Rp {{ number_format($total, 0, ',', '.') }}</span>
                </div>
            </div>

            {{-- Payment Info --}}
            <div class="bg-white rounded-2xl shadow-md p-6">
                <h2 class="text-lg font-semibold font-[family-name:var(--font-heading)] mb-4">Metode Pembayaran</h2>
                <p class="text-sm text-foreground/60 mb-4">
                    Pembayaran via Midtrans (transfer bank, e-wallet, QRIS, kartu kredit/debit).
                    Anda akan diarahkan ke halaman pembayaran setelah menekan tombol "Buat Pesanan".
                </p>
            </div>

            {{-- Submit --}}
            <button type="button"
                    x-data
                    @click="$dispatch('show-confirm')"
                    class="block w-full py-3.5 bg-accent text-white font-bold text-base rounded-lg
                           hover:bg-accent/90 transition-colors duration-150 cursor-pointer">
                Buat Pesanan
            </button>

            {{-- Confirmation Modal --}}
            <div x-data="{ show: false }"
                 x-on:show-confirm.window="show = true"
                 x-show="show"
                 x-cloak
                 x-transition.opacity
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
                 style="display: none;">
                <div class="bg-white rounded-2xl shadow-xl p-6 mx-4 max-w-sm w-full"
                     x-transition.scale
                     @click.outside="show = false">
                    <h3 class="text-lg font-bold font-[family-name:var(--font-heading)] mb-3">Konfirmasi Pesanan</h3>
                    <p class="text-sm text-foreground/70 mb-6">
                        Pastikan data pesanan sudah benar. Setelah dibuat, silakan selesaikan pembayaran dalam 1 jam.
                    </p>
                    <div class="flex gap-3">
                        <button type="button"
                                @click="show = false"
                                class="flex-1 py-2.5 text-sm font-semibold border border-border rounded-lg
                                       hover:bg-background transition-colors duration-150 cursor-pointer">
                            Periksa Kembali
                        </button>
                        <button type="submit"
                                wire:click="checkout"
                                @click="show = false"
                                class="flex-1 py-2.5 text-sm font-bold text-white bg-accent rounded-lg
                                       hover:bg-accent/90 transition-colors duration-150 cursor-pointer">
                            Ya, Buat Pesanan
                        </button>
                    </div>
                </div>
            </div>
        </form>
    @endif
</div>
