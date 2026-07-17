<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Settlement = 'settlement';
    case Processing = 'processing';
    case ReadyPickup = 'ready_pickup';
    case Shipping = 'shipping';
    case Delivered = 'delivered';
    case Completed = 'completed';
    case Expire = 'expire';
    case Cancel = 'cancel';
    case Failed = 'failed';
    case RefundPending = 'refund_pending';
    case RefundDone = 'refund_done';
}
