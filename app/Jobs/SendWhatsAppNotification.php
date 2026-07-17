<?php

namespace App\Jobs;

use App\Enums\OrderStatus;
use App\Models\NotificationLog;
use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;

class SendWhatsAppNotification implements ShouldQueue
{
    use Queueable;

    /**
     * Max attempts before the job is marked as failed.
     */
    public int $tries = 3;

    /**
     * Backoff intervals between retries (in seconds).
     */
    public array $backoff = [30, 60, 120];

    /**
     * Create a new job instance.
     */
    public function __construct(public int $orderId) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $order = Order::find($this->orderId);

        if (! $order) {
            Log::warning('WhatsApp notification: order not found', [
                'order_id' => $this->orderId,
            ]);

            return;
        }

        // Guard: only notify for Settlement, Shipping, ReadyPickup, Delivered
        $notifyStatuses = [
            OrderStatus::Settlement,
            OrderStatus::Shipping,
            OrderStatus::ReadyPickup,
            OrderStatus::Delivered,
        ];

        if (! in_array($order->status, $notifyStatuses, true)) {
            Log::debug('WhatsApp notification: skipped (non-notify status)', [
                'order_id' => $order->id,
                'status' => $order->status->value,
            ]);

            return;
        }

        $phone = $order->phone;

        // Guard: phone required
        if (! $phone) {
            Log::warning('WhatsApp notification: skipped (no phone)', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]);

            return;
        }

        $normalizedPhone = $this->normalizePhone($phone);

        if (! $normalizedPhone) {
            Log::warning('WhatsApp notification: skipped (invalid phone format)', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'phone' => $phone,
            ]);

            return;
        }

        try {
            $twilio = $this->createTwilioClient();
            $message = $this->buildMessage($order);

            $twilio->messages->create(
                'whatsapp:'.$normalizedPhone,
                [
                    'from' => 'whatsapp:'.config('services.twilio.whatsapp_from'),
                    'body' => $message,
                ],
            );

            $log = NotificationLog::create([
                'user_id' => $order->user_id,
                'metadata' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status->value,
                    'channel' => 'whatsapp',
                    'phone' => $normalizedPhone,
                ],
            ]);
            $log->status = 'sent';
            $log->save();

            Log::info('WhatsApp notification sent', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status->value,
            ]);
        } catch (\Throwable $e) {
            Log::error('WhatsApp notification failed', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'error' => $e->getMessage(),
            ]);

            // failure log is created once in failed() hook,
            // not on every retry attempt, to avoid duplicate entries.
            throw $e;
        }
    }

    /**
     * Handle a job failure after all retries are exhausted.
     * Called once per job, not per retry.
     */
    public function failed(?\Throwable $e): void
    {
        $order = Order::find($this->orderId);

        $phone = $order?->phone ?? 'unknown';
        $normalizedPhone = $phone !== 'unknown' ? $this->normalizePhone($phone) : null;

        $log = NotificationLog::create([
            'user_id' => $order?->user_id,
            'metadata' => [
                'order_id' => $this->orderId,
                'order_number' => $order?->order_number ?? 'N/A',
                'status' => $order?->status->value ?? 'unknown',
                'channel' => 'whatsapp',
                'phone' => $normalizedPhone ?? $phone,
                'error' => $e?->getMessage() ?? 'unknown',
            ],
        ]);
        $log->status = 'failed';
        $log->save();

        Log::error('WhatsApp notification: all retries exhausted', [
            'order_id' => $this->orderId,
            'error' => $e?->getMessage(),
        ]);
    }

    /**
     * Create a new Twilio Client instance.
     * extracted for test mockability.
     */
    protected function createTwilioClient(): Client
    {
        return new Client(config('services.twilio.sid'), config('services.twilio.auth_token'));
    }

    /**
     * Normalize a phone number to E.164 format (+62xxx).
     * Returns null if the number cannot be parsed.
     */
    private function normalizePhone(string $phone): ?string
    {
        // manual normalization for ID numbers covers 99% of cases.
        // libphonenumber available as fallback but adds complexity for this V1 scope.

        // Guard: strip non-digits and validate minimum length (valid ID number: min 10 digits)
        $digits = preg_replace('/[^0-9+]/', '', $phone);

        if (strlen(ltrim($digits, '+')) < 10) {
            return null;
        }

        // Already E.164
        if (str_starts_with($digits, '+')) {
            return $digits;
        }

        // 08xxx → +62xxx
        if (str_starts_with($digits, '0')) {
            return '+62'.substr($digits, 1);
        }

        // 62xxx → +62xxx
        if (str_starts_with($digits, '62')) {
            return '+'.$digits;
        }

        // Unknown format, cannot be normalized — log and skip
        // only Indonesian numbers (+62) are valid for this V1 scope.
        // Non-ID numbers would be prefixed incorrectly if we blindly added +62.
        if (strlen($digits) >= 8 && strlen($digits) <= 15) {
            Log::warning('WhatsApp notification: non-Indonesian phone format, skipping', [
                'phone' => $phone,
            ]);

            return null;
        }

        return null;
    }

    /**
     * Build WhatsApp message body based on order status.
     */
    private function buildMessage(Order $order): string
    {
        $storeAddress = config('services.twilio.store_address', 'Jl. G No.120, RT.8/RW.6, Srengseng, Kec. Kembangan, Jakarta Barat 11630');
        $storeHours = config('services.twilio.store_hours', '09.00 - 20.00 WIB');

        return match ($order->status) {
            OrderStatus::Settlement => sprintf(
                "Halo! Pembayaran untuk pesanan *%s* berhasil.\n\nPesanan Anda akan segera diproses.\n\nTerima kasih sudah berbelanja di AlbaSambosa! 🙏",
                $order->order_number,
            ),
            OrderStatus::Shipping => $this->shippingMessage($order),
            OrderStatus::ReadyPickup => sprintf(
                "Halo! Pesanan kamu *%s* sudah *siap diambil* di AlbaSambosa.\n\nAlamat: %s\nJam operasional: %s\n\n%s\n\nTerima kasih sudah berbelanja! 🙏",
                $order->order_number,
                $storeAddress,
                $storeHours,
                $this->trackingLine($order),
            ),
            OrderStatus::Delivered => sprintf(
                "Halo! Pesanan kamu *%s* sudah *sampai di tujuan*.\n\n%s\n\nTerima kasih sudah berbelanja di AlbaSambosa! 🙏",
                $order->order_number,
                $this->trackingLine($order),
            ),
            default => throw new \InvalidArgumentException('Unexpected order status for notification'),
        };
    }

    private function trackingLine(Order $order): string
    {
        $url = \URL::temporarySignedRoute('orders.track.lookup', now()->addDays(30), [
            'order_number' => $order->order_number,
            'phone' => $order->phone,
        ]);

        return "Lacak pesanan: {$url}";
    }

    private function shippingMessage(Order $order): string
    {
        $msg = "Halo! Pesanan kamu *{$order->order_number}* sedang *dalam pengiriman*";

        if ($order->shipment) {
            $msg .= " dengan {$order->shipment->courier}.";

            if ($order->shipment->waybill_id) {
                $trackingUrl = "https://biteship.com/tracking/{$order->shipment->waybill_id}?courier={$order->shipment->courier}";
                $msg .= "\n\nLacak pengiriman: {$trackingUrl}";
            }
        }

        $msg .= "\n\nTerima kasih sudah berbelanja di AlbaSambosa! 🙏";

        return $msg;
    }
}
