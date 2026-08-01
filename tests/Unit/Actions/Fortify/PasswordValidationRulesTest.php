<?php

use App\Actions\Fortify\PasswordValidationRules;
use Illuminate\Validation\Rules\Password;

test('passwordRules returns the expected rule set', function () {
    $subject = new class
    {
        use PasswordValidationRules;

        public function rules(): array
        {
            return $this->passwordRules();
        }
    };

    $rules = $subject->rules();

    expect($rules)->toContain('required');
    expect($rules)->toContain('string');
    expect($rules)->toContain('confirmed');
    expect(collect($rules)->contains(fn ($rule) => $rule instanceof Password))->toBeTrue();
});
