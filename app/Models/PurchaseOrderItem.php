<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'variant_id',
        'variant_value',
        'quantity',
        'received_quantity',
        'unit_price',
        'discount',
        'discount_type',
        'status',
    ];

    public function order()
    {
        return $this->belongsTo(PurchaseOrder::class, 'order_id');
    }

    public function product()
    {
        return $this->belongsTo(ProductDetail::class, 'product_id');
    }

    public function variant()
    {
        return $this->belongsTo(\App\Models\ProductVariant::class, 'variant_id');
    }
    public function detail()
    {
        return $this->hasOne(ProductDetail::class, 'code');
    }

    public function getDisplayNameAttribute()
    {
        $product = $this->product;
        if (!$product) return 'N/A';

        $name = trim($product->name ?? '');
        $variantValue = $this->variant_value;
        $variantId = $this->variant_id ?? $product->variant_id ?? null;

        if (!empty($variantValue)) {
            $variantName = $this->variant->variant_name ?? $product->variant->variant_name ?? null;
            if (!$variantName && !empty($variantId)) {
                $variant = \App\Models\ProductVariant::find($variantId);
                $variantName = $variant->variant_name ?? null;
            }

            if (!empty($variantName)) {
                return rtrim(trim($name . ' - ' . $variantName . ': ' . $variantValue));
            }
            return rtrim(trim($name . ' - ' . $variantValue), ' -:');
        }

        return rtrim($name, ' -:');
    }
}
