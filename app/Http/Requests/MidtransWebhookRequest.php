<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MidtransWebhookRequest extends FormRequest
{
    /**
     * Webhook requests are authorized via IP whitelist + signature verification,
     * not via user authentication. Always return true here.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validate required Midtrans webhook notification fields.
     */
    public function rules(): array
    {
        return [
            'order_id' => ['required', 'string'],
            'transaction_status' => ['required', 'string'],
            'status_code' => ['required', 'string'],
            'gross_amount' => ['required', 'string'],
            'signature_key' => ['required', 'string'],
        ];
    }
}
