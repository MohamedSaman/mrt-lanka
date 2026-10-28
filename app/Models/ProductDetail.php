<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'model',
        'image',
        'description',
        'barcode',
        'status',
        'sale_bonus',
        'brand_id',
        'category_id',
        'supplier_id',
        'variant_id',
    ];

    public function price(): HasOne
    {
        return $this->hasOne(ProductPrice::class, 'product_id');
    }

    public function stock(): HasOne
    {
        return $this->hasOne(ProductStock::class, 'product_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(BrandList::class, 'brand_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CategoryList::class, 'category_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(ProductSupplier::class, 'supplier_id');
    }

    /**
     * Get the variant this product uses
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    /**
     * Get all prices for this product (including variant prices)
     */
    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class, 'product_id');
    }

    /**
     * Get all stock records for this product (including variant stocks)
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(ProductStock::class, 'product_id');
    }

    /**
     * Check if product has variants
     */
    public function hasVariants(): bool
    {
        return $this->variant_id !== null;
    }

    public function returns()
    {
        return $this->hasMany(ReturnsProduct::class, 'product_id');
    }

    public function batches()
    {
        return $this->hasMany(ProductBatch::class, 'product_id');
    }

    public function activeBatches()
    {
        return $this->hasMany(ProductBatch::class, 'product_id')
            ->where('status', 'active')
            ->where('remaining_quantity', '>', 0)
            ->orderBy('received_date', 'asc')
            ->orderBy('id', 'asc');
    }

    public function detail()
    {
        return $this->hasOne(ProductDetail::class, 'code');
    }

    /**
     * Get the product image URL or default image
     *
     * @return string
     */
    public function getImageAttribute($value)
    {
        // If image exists, return it; otherwise return default image path
        return $value ?: 'images/product.jpg';
    }
}
