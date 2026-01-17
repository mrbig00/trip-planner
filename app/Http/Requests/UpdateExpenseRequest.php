<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExpenseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $trip = $this->route('trip');
        $expense = $this->route('expense');
        
        // Expense owner or trip creator can update
        return $expense->user_id === $this->user()->id || $trip->user_id === $this->user()->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $trip = $this->route('trip');
        $eligibleUserIds = $trip->participants->pluck('id')->push($trip->user_id)->unique()->toArray();

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'link' => ['nullable', 'url', 'max:255'],
            'unit_price' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'quantity' => ['required', 'integer', 'min:1'],
            'user_id' => ['required', 'exists:users,id', 'in:'.implode(',', $eligibleUserIds)],
        ];
    }
}
