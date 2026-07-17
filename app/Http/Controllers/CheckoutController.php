<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\MidtransService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function index(): View
    {
        return view('checkout.index');
    }

    public function success(Order $order): View|RedirectResponse
    {
        // Registered user must own the order
        if ($order->user_id && $order->user_id !== auth()->id()) {
            abort(403);
        }

        // Guest order: verify via session (set during checkout)
        if (! $order->user_id && session('last_order_id') !== $order->id) {
            return redirect()->route('orders.track', ['order_number' => $order->order_number])
                ->with('status', 'Gunakan form di bawah untuk melacak pesanan Anda.');
        }

        // Retrieve or regenerate Snap token (survives page refresh)
        $snapToken = session('snap_token_'.$order->id);
        if (! $snapToken && $order->status === OrderStatus::Pending) {
            try {
                $snapToken = MidtransService::createSnapToken($order);
                session(['snap_token_'.$order->id => $snapToken]);
            } catch (\Exception $e) {
                Log::warning('Midtrans token regeneration failed', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $clientKey = config('services.midtrans.client_key');

        return view('checkout.success', [
            'order' => $order->load('items.product'),
            'snapToken' => $snapToken,
            'clientKey' => $clientKey,
        ]);
    }
}
