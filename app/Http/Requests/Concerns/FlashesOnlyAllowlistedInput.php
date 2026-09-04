<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

trait FlashesOnlyAllowlistedInput
{
    /** @return list<string> */
    abstract protected function flashInputKeys(): array;

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(back()->withErrors($validator)->withInput($this->only($this->flashInputKeys())));
    }
}
