<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'batch_number',
        'purchase_order_id',
        'variant_id',
        'variant_value',
        'supplier_price',
        'selling_price',
        'wholesale_price',
        'retail_price',
        'distributor_price',
        'quantity',
        'remaining_quantity',
        'received_date',
        'status',
    ];

    protected $casts = [
        'received_date' => 'date',
        'supplier_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'wholesale_price' => 'decimal:2',
        'retail_price' => 'decimal:2',
        'distributor_price' => 'decimal:2',
    ];

    /**
     * Get the product that owns this batch
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(ProductDetail::class, 'product_id');
    }

    /**
     * Get the purchase order that created this batch
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    /**
     * Get the variant associated with this batch
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    /**
     * Generate a unique batch number
     */
    public static function generateBatchNumber($productId)
    {
        $prefix = 'BATCH-' . $productId . '-';
        $date = now()->format('Ymd');
        $count = self::where('product_id', $productId)
            ->whereDate('created_at', now())
            ->count() + 1;

        return $prefix . $date . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Get active batches for a product using FIFO (oldest first)
     */
    public static function getActiveBatches($productId)
    {
        return self::where('product_id', $productId)
            ->where('status', 'active')
            ->where('remaining_quantity', '>', 0)
            ->orderBy('received_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * Deduct quantity from this batch
     */
    public function deduct($quantity)
    {
        if ($quantity > $this->remaining_quantity) {
            throw new \Exception("Insufficient quantity in batch {$this->batch_number}");
        }

        $this->remaining_quantity -= $quantity;

        if ($this->remaining_quantity == 0) {
            $this->status = 'depleted';
        }

        $this->save();

        return $this;
    }

    /**
     * Check if batch is depleted
     */
    public function isDepleted()
    {
        return $this->remaining_quantity == 0 || $this->status == 'depleted';
    }
}
