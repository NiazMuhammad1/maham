<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['group', 'key', 'value', 'type', 'is_public'])]
class Setting extends Model
{
    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
        ];
    }

    /**
     * The value cast according to the `type` column.
     */
    public function typedValue(): mixed
    {
        return match ($this->type) {
            'integer' => $this->value === null ? null : (int) $this->value,
            'decimal' => $this->value === null ? null : (float) $this->value,
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode((string) $this->value, true),
            default => $this->value,
        };
    }

    /**
     * One setting's value, or $default when it is not saved yet.
     */
    public static function valueOf(string $key, mixed $default = null): mixed
    {
        return static::query()->where('key', $key)->first()?->typedValue() ?? $default;
    }

    /**
     * @param  Builder<Setting>  $query
     */
    public function scopePublic(Builder $query): void
    {
        $query->where('is_public', true);
    }
}
