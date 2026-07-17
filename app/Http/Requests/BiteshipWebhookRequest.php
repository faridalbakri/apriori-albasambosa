<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BiteshipWebhookRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Authorization is handled by IP whitelist + signature verification in the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules.
     * Only validates minimal required fields — full validation
     * happens inside the job after signature verification.
     */
    public function rules(): array
    {
        return [
            'event' => ['required', 'string'],
            'waybill_id' => ['required', 'string'],
            'status' => ['required', 'string'],
            'courier' => ['required', 'array'],
            'courier.company' => ['required', 'string'],
            'courier.waybill_id' => ['required', 'string'],
            'order_id' => ['required', 'string'],
        ];
    }
}
