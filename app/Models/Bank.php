<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedAttributes;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A Saudi bank for the "Select Bank" dropdown on Payout Settings (seeded by BankSeeder).
 */
#[Fillable(['name_en', 'name_ar', 'swift_code', 'is_active', 'sort_order'])]
class Bank extends Model
{
    use HasLocalizedAttributes;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<Bank>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
