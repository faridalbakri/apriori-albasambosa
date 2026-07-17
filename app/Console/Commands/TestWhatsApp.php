<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Jobs\SendWhatsAppNotification;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Console\Command;

class TestWhatsApp extends Command
{
    protected $signature = 'test:whatsapp {phone : Nomor HP tujuan (contoh: 081398258935 atau +6281398258935)}';

    protected $description = 'Kirim test WhatsApp notification via Twilio sandbox.';

    public function handle(): int
    {
        $phone = $this->argument('phone');

        // Cari order dengan ReadyPickup + nomor itu
        $order = Order::where('status', OrderStatus::ReadyPickup)
            ->where('phone', $phone)
            ->first();

        if (! $order) {
            // Bikin order test
            $product = Product::first();
            if (! $product) {
                $this->error('Tidak ada produk di database. Jalankan seeder dulu.');

                return self::FAILURE;
            }

            $order = new Order([
                'order_number' => 'TEST-'.now()->format('YmdHis'),
                'phone' => $phone,
            ]);
            $order->forceFill([
                'total_price' => $product->price,
                'status' => OrderStatus::ReadyPickup,
            ]);
            $order->save();

            $item = $order->items()->make([
                'product_id' => $product->id,
                'quantity' => 1,
            ]);
            $item->forceFill(['price' => $product->price]);
            $item->save();

            $this->info("Order test dibuat: {$order->order_number}");
        } else {
            $this->info("Order ditemukan: {$order->order_number}");
        }

        $this->info("Mengirim WhatsApp ke {$phone}...");

        // Dispatch sync — kirim langsung, tidak lewat queue
        try {
            dispatch_sync(new SendWhatsAppNotification($order->id));
        } catch (\Throwable $e) {
            // exception sudah dicatat di NotificationLog oleh job, lanjutkan
        }

        $log = NotificationLog::where('metadata->order_id', $order->id)
            ->latest()
            ->first();

        if ($log && $log->status === 'sent') {
            $this->info('✅ WhatsApp terkirim!');
            $this->table(['Field', 'Value'], [
                ['Order', $order->order_number],
                ['Status', $log->status],
                ['Phone', $log->metadata['phone'] ?? '-'],
            ]);
        } elseif ($log && $log->status === 'failed') {
            $this->error('❌ WhatsApp gagal!');
            $this->error($log->metadata['error'] ?? 'Unknown error');
        } else {
            $this->error('❌ Tidak ada notifikasi tercatat.');
        }

        return self::SUCCESS;
    }
}
