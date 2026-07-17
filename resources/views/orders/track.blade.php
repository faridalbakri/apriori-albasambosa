@php
$statusLabels = [
    'pending' => 'Menunggu Pembayaran',
    'settlement' => 'Pembayaran Diterima',
    'processing' => 'Diproses',
    'ready_pickup' => 'Siap Diambil',
    'shipping' => 'Dalam Pengiriman',
    'delivered' => 'Terkirim',
    'completed' => 'Selesai',
    'expire' => 'Kedaluwarsa',
    'cancel' => 'Dibatalkan',
    'failed' => 'Gagal',
    'refund_pending' => 'Menunggu Refund',
    'refund_done' => 'Refund Selesai',
];

$mainFlow = ['pending', 'settlement', 'processing', 'ready_pickup', 'shipping', 'delivered', 'completed'];

/** @var \App\Models\Order|null $order */
/** @var bool $searched */
/** @var bool $resent */
$resent ??= false;
$searched ??= false;

// Build timeline from status logs
$timeline = [];
$currentStatusIdx = -1;
if (isset($order)) {
    foreach ($order->statusLogs as $log) {
        $timeline[] = [
            'label' => $statusLabels[$log->new_status] ?? $log->new_status,
            'time' => $log->created_at->format('d M H:i'),
            'status' => $log->new_status,
        ];
    }
    $currentStatusIdx = array_search($order->status->value, $mainFlow);
}
@endphp

<x-layouts.app>
    @section('title', 'Cek Status Pesanan — AlbaSambosa')

    <div class="max-w-xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {{-- Heroicon inline sebagai SVG untuk menghindari dependency eksternal --}}
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-[var(--color-primary)]/10 mb-3">
                <svg class="w-7 h-7 text-[var(--color-primary)]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
                </svg>
            </div>
            <h1 class="text-2xl font-bold font-[family-name:var(--font-heading)]">
                Cek Status Pesanan Anda
            </h1>
            <p class="text-[var(--color-foreground)]/60 text-sm mt-2">
                Masukkan nomor pesanan dan nomor telepon yang digunakan saat checkout
            </p>
        </div>

        {{-- Form --}}
        <div class="bg-white rounded-2xl shadow-md p-6 mb-6">
            <form method="POST" action="{{ route('orders.lookup') }}">
                @csrf

                <div class="mb-4">
                    <label for="order_number" class="block text-sm font-semibold mb-1.5 text-[var(--color-foreground)]">
                        No. Pesanan
                    </label>
                    <input
                        type="text"
                        id="order_number"
                        name="order_number"
                        value="{{ old('order_number', request('order_number')) }}"
                        placeholder="ALBA-YYYYMMDD-XXX"
                        required
                        maxlength="50"
                        class="w-full px-4 py-3 border border-[var(--color-border)] rounded-lg text-sm
                               focus:outline-none focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[var(--color-primary)]/10
                               transition-colors duration-150"
                    >
                </div>

                <div class="mb-5">
                    <label for="phone" class="block text-sm font-semibold mb-1.5 text-[var(--color-foreground)]">
                        No. Telepon
                    </label>
                    <input
                        type="tel"
                        id="phone"
                        name="phone"
                        value="{{ old('phone', request('phone')) }}"
                        placeholder="08xxxxxxxxxx"
                        required
                        maxlength="20"
                        class="w-full px-4 py-3 border border-[var(--color-border)] rounded-lg text-sm
                               focus:outline-none focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[var(--color-primary)]/10
                               transition-colors duration-150"
                    >
                    <p class="text-xs text-[var(--color-foreground)]/50 mt-1.5">
                        Masukkan nomor telepon yang digunakan saat checkout
                    </p>
                </div>

                <button type="submit"
                        class="w-full py-3.5 bg-[var(--color-primary)] text-white rounded-lg font-bold text-base
                               hover:bg-[var(--color-primary)]/90 transition-colors duration-150 cursor-pointer">
                    Cek Status
                </button>
            </form>
        </div>

        {{-- Validation errors --}}
        @if ($errors->any())
            <div class="bg-destructive/5 border border-destructive/20 rounded-xl p-4 mb-6 text-sm text-destructive">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        {{-- Not found --}}
        @if ($searched && ! isset($order))
            <div class="bg-white rounded-2xl shadow-md p-8 text-center">
                <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-accent/10 mb-4">
                    <svg class="w-7 h-7 text-accent" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                    </svg>
                </div>
                <h3 class="font-semibold text-lg mb-1">Pesanan Tidak Ditemukan</h3>
                <p class="text-[var(--color-foreground)]/60 text-sm">
                    Periksa kembali nomor pesanan dan nomor telepon Anda.
                </p>
            </div>
        @endif

        {{-- Order Result --}}
        @if (isset($order))
            <div class="bg-white rounded-2xl shadow-md p-6 mb-6">
                {{-- Header --}}
                <div class="mb-5">
                    <h2 class="text-lg font-bold font-[family-name:var(--font-heading)]">
                        {{ $order->recipient_name ?? 'Pelanggan' }}
                    </h2>
                    <p class="text-sm text-[var(--color-foreground)]/50 font-mono mt-0.5 flex items-center gap-3">
                        {{ $order->order_number }}
                        @php
                            $statusColor = match($order->status->value) {
                                'pending' => 'bg-accent/10 text-accent',
                                'settlement', 'processing', 'ready_pickup' => 'bg-info/10 text-info',
                                'shipping' => 'bg-muted text-foreground',
                                'delivered', 'completed' => 'bg-success/10 text-success',
                                'expire', 'cancel', 'failed' => 'bg-destructive/10 text-destructive',
                                'refund_pending', 'refund_done' => 'bg-warning/10 text-warning',
                                default => 'bg-muted text-foreground',
                            };
                        @endphp
                        <span class="inline-block px-3 py-1 rounded-full text-xs font-bold {{ $statusColor }}">
                            {{ $statusLabels[$order->status->value] ?? $order->status->value }}
                        </span>
                    </p>
                </div>

                {{-- Payment & Cancel buttons — only for pending orders --}}
                @if ($order->status->value === 'pending')
                    <div class="flex gap-3 mb-4">
                        @if ($snapToken)
                            <button id="track-pay-button"
                                    class="flex-1 py-3 bg-accent text-white font-bold text-sm rounded-lg
                                           hover:bg-accent/90 transition-colors duration-150 cursor-pointer">
                                Bayar Sekarang
                            </button>
                        @endif
                        <form method="POST" action="{{ route('orders.cancel', $order) }}" class="{{ $snapToken ? 'flex-1' : '' }}">
                            @csrf
                            <button type="submit"
                                    class="w-full py-3 px-2 text-sm text-white bg-destructive rounded-lg font-bold
                                           hover:bg-destructive/90 transition-colors duration-150 cursor-pointer"
                                    onclick="return confirm('Batalkan pesanan ini?')">
                                Batalkan Pesanan
                            </button>
                        </form>
                    </div>
                @endif

                {{-- Info rows --}}
                <div class="space-y-3 text-sm border-t border-[var(--color-border)] pt-4">
                    @if ($order->pickup_time)
                        <div class="flex justify-between">
                            <span class="text-[var(--color-foreground)]/60">Metode</span>
                            <span class="font-semibold">Pickup</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-[var(--color-foreground)]/60">Waktu Pickup</span>
                            <span class="font-semibold">{{ $order->pickup_time->translatedFormat('l, d M Y — H:i') }}</span>
                        </div>
                    @else
                        <div class="flex justify-between">
                            <span class="text-[var(--color-foreground)]/60">Metode</span>
                            <span class="font-semibold">Delivery</span>
                        </div>
                        @if ($order->address_detail)
                            <div class="flex justify-between">
                                <span class="text-[var(--color-foreground)]/60">Alamat</span>
                                <span class="font-semibold text-right max-w-[65%]">{{ $order->address_detail }}</span>
                            </div>
                        @endif
                    @endif

                    @if ($order->shipment)
                        <div class="flex justify-between">
                            <span class="text-[var(--color-foreground)]/60">Kurir</span>
                            <span class="font-semibold">{{ $order->shipment->courier }} — {{ $order->shipment->waybill_id }}</span>
                        </div>
                        @if ($order->shipment->waybill_id)
                            <div class="mt-2">
                                <a href="https://biteship.com/tracking/{{ $order->shipment->waybill_id }}?courier={{ $order->shipment->courier }}"
                                   target="_blank" rel="noopener noreferrer"
                                   class="inline-flex items-center gap-1 text-sm text-info hover:underline font-semibold cursor-pointer">
                                    Lacak Pengiriman →
                                </a>
                            </div>
                        @endif
                        @if ($order->shipment->estimated_arrival)
                            <div class="flex justify-between">
                                <span class="text-[var(--color-foreground)]/60">Estimasi Tiba</span>
                                <span class="font-semibold">{{ \Carbon\Carbon::parse($order->shipment->estimated_arrival)->translatedFormat('d M Y') }}</span>
                            </div>
                        @endif
                    @endif

                    <div class="flex justify-between">
                        <span class="text-[var(--color-foreground)]/60">Total</span>
                        <span class="font-semibold text-[var(--color-primary)]">Rp {{ number_format($order->total_price, 0, ',', '.') }}</span>
                    </div>
                </div>

                {{-- Items --}}
                <div class="mt-5 pt-4 border-t border-[var(--color-border)]">
                    <h3 class="text-sm font-semibold mb-3">Item Pesanan</h3>
                    <div class="space-y-2">
                        @foreach ($order->items as $item)
                            <div class="flex justify-between text-sm">
                                <span>{{ $item->product->name }} &times; {{ $item->quantity }}</span>
                                <span class="font-semibold">Rp {{ number_format($item->price * $item->quantity, 0, ',', '.') }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Timeline --}}
                @if (count($timeline) > 0)
                    <div class="mt-6 pt-4 border-t border-[var(--color-border)]">
                        <h3 class="text-sm font-semibold mb-4">Riwayat Status</h3>
                        <div class="relative pl-7">
                            <div class="absolute left-[9px] top-2.5 bottom-2 w-0.5 bg-[var(--color-border)]"></div>
                            @foreach ($timeline as $i => $step)
                                @php
                                    $isLast = $loop->last;
                                    $stepIdx = array_search($step['status'], $mainFlow);
                                    $isDone = $stepIdx !== false && $stepIdx <= $currentStatusIdx;
                                    $isActive = $loop->last;
                                    $dotClass = $isActive
                                        ? 'bg-[var(--color-primary)] shadow-[0_0_0_5px_rgba(146,64,14,0.15)]'
                                        : ($isDone ? 'bg-[var(--color-primary)]' : 'bg-[var(--color-border)]');
                                @endphp
                                <div class="relative pb-4 last:pb-0">
                                    <div class="absolute -left-6 top-1 w-3 h-3 rounded-full border-2 border-white {{ $dotClass }}"></div>
                                    <div class="font-semibold text-sm {{ $isActive ? 'text-[var(--color-primary)]' : '' }}">
                                        {{ $step['label'] }}
                                        @if ($isActive)
                                            <span class="text-xs font-normal text-[var(--color-foreground)]/50">&larr; Saat ini</span>
                                        @endif
                                    </div>
                                    <div class="text-xs text-[var(--color-foreground)]/50">{{ $step['time'] }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

        @endif

        {{-- Midtrans Snap embed for pending orders --}}
        @if (isset($snapToken) && $snapToken)
            <script src="https://app.{{ config('services.midtrans.is_production') ? '' : 'sandbox.' }}midtrans.com/snap/snap.js"
                    data-client-key="{{ $clientKey }}"></script>
            <script>
                document.getElementById('track-pay-button').onclick = function () {
                    snap.pay({!! json_encode($snapToken) !!}, {
                        onSuccess: function () {
                            location.reload();
                        },
                        onPending: function () {
                            location.reload();
                        },
                        onError: function () {
                            location.reload();
                        },
                        onClose: function () {
                            // Do nothing — button still available
                        }
                    });
                };
            </script>
        @endif

        {{-- Back link --}}
        <div class="text-center mt-8">
            <a href="{{ route('catalog.index') }}"
               class="inline-block px-6 py-3 bg-[var(--color-background)] text-[var(--color-foreground)] font-semibold rounded-lg
                      border border-[var(--color-border)] hover:bg-[var(--color-border)] transition-colors duration-150">
                &larr; Kembali ke Menu
            </a>
        </div>
    </div>
</x-layouts.app>
