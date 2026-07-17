<?php

namespace App\Listeners;

use App\Models\Cart;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\DB;

class SyncCartOnLogin
{
    /**
     * Transfer guest cart items to authenticated user on login.
     */
    public function handle(Login $event): void
    {
        $sessionId = session()->getId();
        $guestCart = Cart::where('session_id', $sessionId)->get();

        if ($guestCart->isEmpty()) {
            return;
        }

        // DB transaction prevents partial sync if a mid-loop failure occurs
        DB::transaction(function () use ($guestCart, $event) {
            foreach ($guestCart as $item) {
                $existing = Cart::where('user_id', $event->user->id)
                    ->where('product_id', $item->product_id)
                    ->first();

                if ($existing) {
                    $existing->increment('quantity', $item->quantity);
                    $item->delete();
                } else {
                    $item->user_id = $event->user->id;
                    $item->session_id = null;
                    $item->save();
                }
            }
        });
    }
}
