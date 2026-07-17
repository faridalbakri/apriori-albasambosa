<?php

namespace App\Http\Controllers;

use App\Actions\TransitionOrderStatus;
use App\Enums\OrderStatus;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Order;
use App\Services\MidtransService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class OrderTrackingController extends Controller
{
    public function show(): View
    {
        return view('orders.track', [
            'snapToken' => null,
            'clientKey' => null,
            'searched' => false,
        ]);
    }

    /** Auto-lookup via signed URL (from WhatsApp notification) */
    public function lookupSigned(Request $request): View
    {
        return $this->lookup($request);
    }

    public function lookup(Request $request): View
    {
        $validated = $request->validate([
            'order_number' => ['required', 'string', 'max:50'],
            'phone' => ['required', 'string', 'max:20'],
        ]);

        $order = Order::with(['items.product', 'statusLogs'])
            ->where('order_number', $validated['order_number'])
            ->where('phone', $validated['phone'])
            ->first();

        session(['last_tracked_order_id' => $order?->id]);

        $snapToken = null;
        $clientKey = null;

        if ($order && $order->status === OrderStatus::Pending) {
            try {
                $snapToken = MidtransService::createSnapToken($order);
                $clientKey = config('services.midtrans.client_key');
            } catch (\Exception $e) {
                Log::warning('Midtrans token failed during order lookup', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return view('orders.track', [
            'order' => $order,
            'searched' => true,
            'snapToken' => $snapToken,
            'clientKey' => $clientKey,
        ]);
    }

    /**
     * Customer self-cancellation — only allowed when status is Pending.
     * Guest: verified via session. Registered: must own the order.
     */
    public function cancel(Order $order, TransitionOrderStatus $transitionOrderStatus): RedirectResponse
    {
        // Authorization: registered user must own the order
        if ($order->user_id && $order->user_id !== auth()->id()) {
            abort(403);
        }

        // Guest: verify via session (set during lookup)
        if (! $order->user_id && session('last_tracked_order_id') !== $order->id) {
            abort(403);
        }

        // Only pending orders can be cancelled by customer
        if ($order->status !== OrderStatus::Pending) {
            return redirect()->route('orders.track')
                ->with('status', 'Pesanan tidak dapat dibatalkan — status sudah berubah.');
        }

        try {
            $transitionOrderStatus($order, OrderStatus::Cancel);
        } catch (InvalidStatusTransitionException) {
            return redirect()->route('orders.track')
                ->with('status', 'Pesanan tidak dapat dibatalkan — status sudah berubah.');
        }

        session()->forget('last_tracked_order_id');

        return redirect()->route('orders.track', [
            'order_number' => $order->order_number,
        ])->with('status', 'Pesanan berhasil dibatalkan.');
    }
}
