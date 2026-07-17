<?php

namespace App\Livewire;

use App\Actions\CreateOrder;
use App\Contracts\DeliveryService;
use App\Models\Cart;
use App\Models\Shipment;
use App\Services\MidtransService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Livewire\Component;
use Propaganistas\LaravelPhone\Rules\Phone;

class CheckoutPage extends Component
{
    // Shipping method
    public string $shippingMethod = 'pickup';

    // Pickup
    public ?string $pickupDate = null;

    public ?string $pickupTime = null;

    // Delivery address
    public string $recipientName = '';

    public string $recipientPhone = '';

    public string $addressDetail = '';

    public string $destinationPostalCode = '';

    // Courier selection
    public array $availableRates = [];

    public bool $isLoadingRates = false;

    public ?string $selectedRateKey = null;

    // Guest fields
    public string $guestName = '';

    public string $guestPhone = '';

    public bool $pdpConsent = false;

    // block delivery when Biteship is unreachable — safer than fallback ongkir
    public bool $deliveryUnavailable = false;

    public function mount(): void
    {
        if (auth()->check()) {
            $this->recipientName = auth()->user()->name;
        }

        // Restore form state from session (survive page refresh)
        $saved = session('checkout_form');
        if ($saved) {
            foreach ($this->persistableFields() as $field) {
                if (array_key_exists($field, $saved)) {
                    $this->{$field} = $saved[$field];
                }
            }
        }
    }

    public function dehydrate(): void
    {
        $state = [];
        foreach ($this->persistableFields() as $field) {
            $state[$field] = $this->{$field};
        }
        session(['checkout_form' => $state]);
    }

    private function persistableFields(): array
    {
        return [
            'shippingMethod', 'pickupDate', 'pickupTime',
            'recipientName', 'recipientPhone', 'addressDetail', 'destinationPostalCode',
            'selectedRateKey',
            'guestName', 'guestPhone',
        ];
    }

    // shipping cost computed server-side, not a public property (price manipulation protection)

    public function getShippingCostProperty(): float
    {
        if ($this->shippingMethod !== 'delivery') {
            return 0;
        }

        $rate = $this->findSelectedRate();

        return $rate ? (float) $rate['price'] : 0;
    }

    public function getCartItemsProperty(): Collection
    {
        return Cart::with('product')
            ->when(auth()->id(), fn ($q, $id) => $q->where('user_id', $id))
            ->when(! auth()->id(), fn ($q) => $q->where('session_id', session()->getId()))
            ->get();
    }

    public function getSubtotalProperty(): float
    {
        return $this->cartItems->sum(fn ($item) => $item->price * $item->quantity);
    }

    public function getTotalProperty(): float
    {
        return $this->subtotal + $this->shippingCost;
    }

    /**
     * Phone number that will receive WhatsApp notifications.
     * Returns null if no phone has been entered yet.
     */
    public function getNotificationPhoneProperty(): ?string
    {
        $phone = auth()->check() ? $this->recipientPhone : $this->guestPhone;

        return $phone ?: null;
    }

    public function getPickupSlotsProperty(): array
    {
        if (! $this->pickupDate) {
            return [];
        }

        $slots = [];

        for ($hour = 9; $hour < 20; $hour++) {
            $slots[] = [
                'value' => sprintf('%02d:00', $hour),
                'label' => sprintf('%02d:00 - %02d:00', $hour, $hour + 1),
            ];
        }

        // 1h slots Mon-Fri 10:00-18:00, configurable later
        return $slots;
    }

    public function updatedShippingMethod(): void
    {
        if ($this->shippingMethod === 'delivery' && $this->deliveryUnavailable) {
            $this->shippingMethod = 'pickup';
            $this->dispatch('notify', type: 'warning', message: 'Layanan delivery sedang tidak tersedia.');

            return;
        }

        $this->selectedRateKey = null;
        $this->availableRates = [];
        $this->destinationPostalCode = '';
    }

    /**
     * Fetch shipping rates when destination postal code changes.
     * Debounced by Livewire's 500ms wire:model.live.debounce.
     */
    public function updatedDestinationPostalCode(): void
    {
        $this->selectedRateKey = null;
        $this->availableRates = [];

        $postalCode = trim($this->destinationPostalCode);

        if (mb_strlen($postalCode) < 4) {
            return;
        }

        // Rate limit: 10 requests per minute per IP
        $ip = request()->ip();
        $rateLimitKey = "biteship-rates:{$ip}";

        if (RateLimiter::tooManyAttempts($rateLimitKey, 10)) {
            $this->dispatch('notify', type: 'warning', message: 'Terlalu banyak cek ongkir. Coba lagi sebentar.');

            return;
        }

        RateLimiter::hit($rateLimitKey, 60);

        $this->fetchRates($postalCode);
    }

    /**
     * Fetch rates from Biteship, cached for 5 minutes.
     */
    private function fetchRates(string $postalCode): void
    {
        $this->isLoadingRates = true;

        try {
            $originPostalCode = config('services.biteship.origin_postal_code');

            if (! $originPostalCode) {
                Log::warning('Biteship origin_postal_code not configured — rate fetching disabled');
                $this->dispatch('notify', type: 'error', message: 'Layanan cek ongkir belum dikonfigurasi. Silakan hubungi admin.');

                return;
            }

            $items = $this->cartItems
                ->filter(fn ($cart) => $cart->product !== null)
                ->map(fn ($cart) => [
                    'name' => $cart->product->name,
                    'value' => (int) $cart->price,
                    'quantity' => $cart->quantity,
                    'weight' => 1000, // default 1kg per item, refine with actual weight field later
                ])->values()->toArray();

            if (empty($items)) {
                $this->availableRates = [];

                return;
            }

            $cacheKey = 'biteship_rates_'.md5($originPostalCode.$postalCode.serialize($items));

            $response = Cache::remember($cacheKey, 300, function () use ($originPostalCode, $postalCode, $items) {
                return app(DeliveryService::class)->getRates(
                    origin: ['postal_code' => $originPostalCode],
                    destination: ['postal_code' => $postalCode],
                    items: $items,
                    couriers: 'gosend,grab',
                );
            });

            $this->availableRates = $response['pricing'] ?? [];
            $this->deliveryUnavailable = false;
        } catch (\Exception $e) {
            $this->availableRates = [];
            $this->deliveryUnavailable = true;
            $this->dispatch('notify', type: 'error', message: 'Layanan delivery sedang tidak tersedia. Silakan pilih Pickup.');
        } finally {
            $this->isLoadingRates = false;
        }
    }

    /**
     * Find the selected rate from available rates.
     */
    private function findSelectedRate(): ?array
    {
        if (! $this->selectedRateKey || empty($this->availableRates)) {
            return null;
        }

        foreach ($this->availableRates as $rate) {
            $key = $rate['courier_code'].'|'.$rate['courier_service_code'];

            if ($key === $this->selectedRateKey) {
                return $rate;
            }
        }

        return null;
    }

    public function selectRate(string $courierCode, string $serviceCode): void
    {
        $this->selectedRateKey = $courierCode.'|'.$serviceCode;
    }

    public function checkout(CreateOrder $createOrder): void
    {
        $this->validate($this->rules());

        $cartItems = $this->cartItems;

        if ($cartItems->isEmpty()) {
            $this->dispatch('notify', type: 'error', message: 'Keranjang belanja kosong.');

            return;
        }

        // Validate rate selection BEFORE creating order (prevents orphaned orders)
        $selectedRate = null;

        if ($this->shippingMethod === 'delivery') {
            $selectedRate = $this->findSelectedRate();

            if (! $selectedRate) {
                // Block delivery if rates never loaded (Biteship down)
                if (empty($this->availableRates)) {
                    $this->dispatch('notify', type: 'error', message: 'Layanan delivery sedang tidak tersedia. Silakan pilih Pickup.');

                    return;
                }

                $this->dispatch('notify', type: 'error', message: 'Ongkos kirim tidak valid. Silakan pilih kurir kembali.');

                return;
            }
        }

        $pickupTime = $this->shippingMethod === 'pickup'
            ? ($this->pickupDate.' '.($this->pickupTime ?? '10:00'))
            : null;

        // guest delivery uses delivery form fields, guest pickup uses Data Diri fields
        $customerName = auth()->check()
            ? $this->recipientName
            : ($this->shippingMethod === 'delivery' ? $this->recipientName : $this->guestName);
        $phone = auth()->check()
            ? $this->recipientPhone
            : ($this->shippingMethod === 'delivery' ? $this->recipientPhone : $this->guestPhone);

        try {
            $order = $createOrder(
                cartItems: $cartItems,
                user: auth()->user(),
                shippingMethod: $this->shippingMethod,
                pickupTime: $pickupTime,
                customerName: $customerName,
                phone: $phone,
                shippingCost: $this->shippingCost,
                addressDetail: $this->shippingMethod === 'delivery' ? $this->addressDetail : null,
                postalCode: $this->shippingMethod === 'delivery' ? $this->destinationPostalCode : null,
            );

            // Generate Snap token for Midtrans payment
            // try token first; if it fails, order exists but can still be paid via manual sync
            $snapToken = null;

            try {
                $snapToken = MidtransService::createSnapToken($order);
            } catch (\Exception $e) {
                Log::warning('Midtrans createSnapToken failed during checkout', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // Create Shipment record AFTER token generation (avoids orphaned shipments)
            // try-catch prevents 500 error if shipment save fails — admin can fix manually
            if ($this->shippingMethod === 'delivery' && $selectedRate) {
                try {
                    $shipment = new Shipment;
                    $shipment->fill([
                        'order_id' => $order->id,
                        'courier' => $selectedRate['courier_code'],
                        'courier_service' => $selectedRate['courier_service_code'],
                    ]);
                    $shipment->setTrackingStatus('pending');
                    $shipment->save();
                } catch (\Exception $e) {
                    Log::error('Shipment creation failed during checkout', [
                        'order_id' => $order->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            session([
                'last_order_id' => $order->id,
                'snap_token_'.$order->id => $snapToken,
            ]);

            session()->forget('checkout_form');
            $this->dispatch('cart-updated');
            $this->redirect(route('checkout.success', ['order' => $order->id]));
        } catch (\RuntimeException $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    protected function rules(): array
    {
        $rules = [
            'shippingMethod' => ['required', 'in:pickup,delivery'],
        ];

        if ($this->shippingMethod === 'pickup') {
            $rules['pickupDate'] = ['required', 'date', 'after_or_equal:today'];
            $rules['pickupTime'] = ['required', 'string'];

            if (auth()->check()) {
                $rules['recipientPhone'] = ['required', new Phone('ID')];
            }
        }

        if ($this->shippingMethod === 'delivery') {
            $rules['recipientName'] = ['required', 'string', 'max:255'];
            $rules['recipientPhone'] = ['required', new Phone('ID')];
            $rules['addressDetail'] = ['required', 'string', 'max:500'];
            $rules['destinationPostalCode'] = ['required', 'string', 'regex:/^[0-9]+$/', 'min:4', 'max:10'];
            $rules['selectedRateKey'] = ['required', 'string'];
        }

        if (! auth()->check()) {
            $rules['pdpConsent'] = ['required', 'accepted'];

            // Guest pickup: name + phone from Data Diri section
            // Guest delivery: name + phone from delivery form (already validated above)
            if ($this->shippingMethod !== 'delivery') {
                $rules['guestName'] = ['required', 'string', 'max:255'];
                $rules['guestPhone'] = ['required', new Phone('ID')];
            }
        }

        return $rules;
    }

    public function render(): View
    {
        return view('livewire.checkout-page', [
            'cartItems' => $this->cartItems,
            'subtotal' => $this->subtotal,
            'total' => $this->total,
            'pickupSlots' => $this->pickupSlots,
        ]);
    }
}
