<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedAttributes;
use Database\Factories\OnboardingSlideFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['audience', 'title_en', 'title_ar', 'description_en', 'description_ar', 'image', 'is_active', 'sort_order'])]
class OnboardingSlide extends Model
{
    /** @use HasFactory<OnboardingSlideFactory> */
    use HasFactory, HasLocalizedAttributes;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<OnboardingSlide>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
