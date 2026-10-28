<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'variant_name',
        'variant_values',
        'status',
    ];

    protected $casts = [
        'variant_values' => 'array',
    ];

    /**
     * Get products that use this variant
     */
    public function products(): HasMany
    {
        return $this->hasMany(ProductDetail::class, 'variant_id');
    }

    /**
     * Get prices for this variant
     */
    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class, 'variant_id');
    }

    /**
     * Scope to get only active variants
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Get formatted variant display name with all values
     */
    public function getFullNameAttribute()
    {
        $values = is_array($this->variant_values) ? implode(', ', $this->variant_values) : $this->variant_values;
        return "{$this->variant_name}: {$values}";
    }

    /**
     * Get count of variant values
     */
    public function getValuesCountAttribute()
    {
        return is_array($this->variant_values) ? count($this->variant_values) : 0;
    }
}
