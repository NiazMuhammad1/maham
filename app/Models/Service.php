<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedAttributes;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'slug',
    'name_en',
    'name_ar',
    'description_en',
    'description_ar',
    'icon',
    'icon_bg_color',
    'image',
    'is_popular',
    'is_active',
    'sort_order',
])]
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory, HasLocalizedAttributes, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_popular' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<SubService, $this>
     */
    public function subServices(): HasMany
    {
        return $this->hasMany(SubService::class);
    }

    /**
     * @param  Builder<Service>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
