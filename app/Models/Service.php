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
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[Fillable([
    'slug',
    'name_en',
    'name_ar',
    'description_en',
    'description_ar',
    'icon_bg_color',
    'is_popular',
    'is_active',
    'sort_order',
])]
class Service extends Model implements HasMedia
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory, HasLocalizedAttributes, InteractsWithMedia, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_popular' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * "icon" = small picture on the Home / All Services grid, "image" = hero picture on Service Details.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('icon')->singleFile();
        $this->addMediaCollection('image')->singleFile();
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
