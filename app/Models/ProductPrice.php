<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'variant_id',
        'variant_value',
        'pricing_mode',
        'supplier_price',
        'selling_price',
        'retail_price',
        'wholesale_price',
        'distributor_price',
        'discount_price'
    ];

    protected $casts = [
        'supplier_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'retail_price' => 'decimal:2',
        'wholesale_price' => 'decimal:2',
        'distributor_price' => 'decimal:2',
        'discount_price' => 'decimal:2',
    ];

    /**
     * Get the Product that owns this price information
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(ProductDetail::class, 'product_id');
    }

    /**
     * Get the variant this price belongs to (nullable for single pricing)
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    /**
     * Check if this is a variant-based price
     */
    public function isVariantBased(): bool
    {
        return $this->pricing_mode === 'variant' && $this->variant_id !== null;
    }

    /**
     * Check if this is a single price
     */
    public function isSinglePrice(): bool
    {
        return $this->pricing_mode === 'single' && $this->variant_id === null;
    }

    /**
     * Calculate the profit margin percentage
     */
    public function getProfitMarginAttribute()
    {
        if ($this->supplier_price > 0) {
            $price = $this->discount_price ?? $this->selling_price;
            return (($price - $this->supplier_price) / $this->supplier_price) * 100;
        }
        return 0;
    }
    public function detail()
    {
        return $this->belongsTo(ProductDetail::class, 'product_id');
    }
}
