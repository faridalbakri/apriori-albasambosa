<x-layouts.app>
    @section('title', 'Pesanan Berhasil — AlbaSambosa')

    <div class="max-w-xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {{-- Success Icon --}}
        <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-success/10 mb-4">
                <x-heroicon-o-check class="w-8 h-8 text-success" />
            </div>
            <h1 class="text-2xl font-bold font-[family-name:var(--font-heading)]">Pesanan Berhasil Dibuat!</h1>
            <p class="text-foreground/60 mt-2">Nomor pesanan Anda:</p>
            <p class="text-2xl font-bold text-primary mt-1 font-mono">{{ $order->order_number }}</p>
        </div>

        {{-- Order Details --}}
        <div class="bg-white rounded-2xl shadow-md p-6 mb-5 space-y-3">
            <h2 class="text-lg font-semibold font-[family-name:var(--font-heading)]">Detail Pesanan</h2>

            <div class="text-sm space-y-2">
                <div class="flex justify-between">
                    <span class="text-foreground/60">Status</span>
                    <span class="font-semibold text-accent">Menunggu Pembayaran</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-foreground/60">Metode</span>
                    <span class="font-semibold">{{ $order->pickup_time ? 'Pickup' : 'Delivery' }}</span>
                </div>
                @if ($order->pickup_time)
                    <div class="flex justify-between">
                        <span class="text-foreground/60">Waktu Pickup</span>
                        <span class="font-semibold">{{ $order->pickup_time->translatedFormat('l, d M Y — H:i') }}</span>
                    </div>
                @endif
                @if ($order->recipient_name)
                    <div class="flex justify-between">
                        <span class="text-foreground/60">Penerima</span>
                        <span class="font-semibold">{{ $order->recipient_name }}</span>
                    </div>
                @endif
                @if ($order->address_detail)
                    <div class="flex justify-between">
                        <span class="text-foreground/60">Alamat</span>
                        <span class="font-semibold">{{ $order->address_detail }}</span>
                    </div>
                @endif
                <div class="flex justify-between">
                    <span class="text-foreground/60">Telepon</span>
                    <span class="font-semibold">{{ $order->phone }}</span>
                </div>
            </div>

            <hr class="border-border">

            <h3 class="font-semibold text-sm">Item:</h3>
            @foreach ($order->items as $item)
                <div class="flex justify-between text-sm">
                    <span>{{ $item->product->name }} ({{ $item->quantity }}x)</span>
                    <span class="font-semibold">Rp {{ number_format($item->price * $item->quantity, 0, ',', '.') }}</span>
                </div>
            @endforeach

            @if ($order->shipping_cost > 0)
                <div class="flex justify-between text-sm text-foreground/60">
                    <span>Ongkos Kirim</span>
                    <span>Rp {{ number_format($order->shipping_cost, 0, ',', '.') }}</span>
                </div>
            @endif

            <hr class="border-2 border-border">

            <div class="flex justify-between text-lg font-bold text-primary">
                <span>Total</span>
                <span>Rp {{ number_format($order->total_price, 0, ',', '.') }}</span>
            </div>
        </div>

        {{-- Payment Section — Midtrans Snap embed --}}
        @if ($snapToken)
            <div class="bg-white rounded-2xl shadow-md p-6 mb-5" id="payment-section">
                <h2 class="text-lg font-semibold font-[family-name:var(--font-heading)] mb-4">Pembayaran</h2>
                <div class="text-sm text-foreground/60 mb-4"
                         x-data="{
                             seconds: {{ (int) now()->diffInSeconds($order->created_at->addHour(), false) }},
                             get display() {
                                 if (this.seconds <= 0) return '⏰ Waktu pembayaran habis';
                                 const m = Math.floor(this.seconds / 60);
                                 const s = this.seconds % 60;
                                 return '⏰ Selesaikan pembayaran dalam ' + m + ':' + String(s).padStart(2, '0');
                             }
                         }"
                         x-init="setInterval(() => { if (seconds > 0) seconds-- }, 1000)">
                        <span x-text="display"></span>
                    </div>
                    <div id="snap-message" class="mb-4 text-center text-sm"></div>
                <button id="pay-button"
                        class="block w-full py-3.5 bg-accent text-white font-bold text-base rounded-lg
                               hover:bg-accent/90 transition-colors duration-150 cursor-pointer">
                    Bayar Sekarang
                </button>
            </div>

            <script src="https://app.{{ config('services.midtrans.is_production') ? '' : 'sandbox.' }}midtrans.com/snap/snap.js"
                    data-client-key="{{ $clientKey }}"></script>
            <script>
                const snapMessage = document.getElementById('snap-message');
                const payButton = document.getElementById('pay-button');
                const paymentHeading = document.getElementById('payment-section').querySelector('h2');

                payButton.onclick = function () {
                    snap.pay({!! json_encode($snapToken) !!}, {
                        onSuccess: function () {
                            snapMessage.innerHTML = '<p class="text-success font-semibold">Pembayaran berhasil! Status pesanan akan diperbarui.</p>';
                            payButton.style.display = 'none';
                            paymentHeading.textContent = 'Pembayaran Berhasil';
                        },
                        onPending: function () {
                            snapMessage.innerHTML = '<p class="text-accent font-semibold">Pembayaran tertunda. Silakan selesaikan pembayaran Anda.</p>';
                        },
                        onError: function () {
                            snapMessage.innerHTML = '<p class="text-destructive font-semibold">Pembayaran gagal. Silakan coba lagi.</p>';
                        },
                        onClose: function () {
                            snapMessage.innerHTML = '';
                        }
                    });
                };
            </script>
        @else
            <div class="bg-white rounded-2xl shadow-md p-6 mb-5">
                <h2 class="text-lg font-semibold font-[family-name:var(--font-heading)] mb-4">Pembayaran</h2>
                <div class="bg-background border-2 border-dashed border-border rounded-lg p-10 text-center text-foreground/60 text-sm">
                    Token pembayaran tidak tersedia. Silakan hubungi admin.
                </div>
            </div>
        @endif

        {{-- Info --}}
        <div class="text-center text-sm text-foreground/60">
            <p>Simpan nomor pesanan Anda untuk <a href="{{ route('orders.track', ['order_number' => $order->order_number]) }}" class="text-primary underline">melacak status</a>.</p>
            <p class="mt-1">Pertanyaan? Hubungi kami di <span class="font-semibold">admin@albasambosa.com</span></p>
        </div>

        <div class="text-center mt-6">
            <a href="{{ route('catalog.index') }}"
               class="inline-block px-6 py-3 bg-background text-foreground font-semibold rounded-lg border border-border
                      hover:bg-border transition-colors duration-150">
                ← Kembali ke Menu
            </a>
        </div>
    </div>
</x-layouts.app>
