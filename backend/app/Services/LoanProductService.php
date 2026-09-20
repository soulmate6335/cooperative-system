<?php

namespace App\Services;

use App\Models\LoanProduct;
use Illuminate\Validation\ValidationException;

class LoanProductService
{
    public function create(array $data): LoanProduct
    {
        return LoanProduct::create($data)->fresh();
    }

    public function update(LoanProduct $product, array $data): LoanProduct
    {
        $product->update($data);

        return $product->fresh();
    }

    public function setActive(LoanProduct $product, bool $active): LoanProduct
    {
        if ($product->isActive() === $active) {
            throw ValidationException::withMessages(['product' => $active ? 'The product is already active.' : 'The product is already inactive.']);
        }

        $product->update(['status' => $active ? 'active' : 'inactive']);

        return $product->fresh();
    }
}
