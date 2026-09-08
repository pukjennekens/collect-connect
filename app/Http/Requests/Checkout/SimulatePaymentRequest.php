<?php

declare(strict_types=1);

namespace App\Http\Requests\Checkout;

use Illuminate\Foundation\Http\FormRequest;

class SimulatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        abort_if(app()->environment('production') || ! app()->environment((array) config('payment.simulator_environments', [])), 404);

        return \Illuminate\Support\Facades\Gate::allows('viewPlaced', $this->route('order'));
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['outcome' => ['required', 'in:paid,failed,cancelled']];
    }
}
