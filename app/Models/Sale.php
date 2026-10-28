<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_id',
        'invoice_number',
        'customer_id',
        'customer_type',
        'subtotal',
        'discount_amount',
        'discount_type',
        'additional_discount_type',
        'additional_discount_percentage',
        'total_amount',
        'payment_type',
        'payment_status',
        'status',
        'notes',
        'due_amount',
        'user_id',
        'sale_type',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'delivered_by',
        'delivered_at',
        'delivery_status',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Generate unique sale ID
    public static function generateSaleId()
    {
        $prefix = 'SALE-';
        $date = now()->format('Ymd');
        $lastSale = self::where('sale_id', 'like', "{$prefix}{$date}%")
            ->orderBy('sale_id', 'desc')
            ->lockForUpdate()
            ->first();

        $nextNumber = 1;

        if ($lastSale) {
            $parts = explode('-', $lastSale->sale_id);
            $lastNumber = intval(end($parts));
            $nextNumber = $lastNumber + 1;
        }

        return $prefix . $date . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    // Generate unique invoice numbers starting from 0678
    public static function generateInvoiceNumber()
    {
        // Get the last invoice number from the database
        $lastSale = self::orderBy('id', 'desc')
            ->lockForUpdate()
            ->first();

        $nextNumber = 678; // Starting number

        if ($lastSale && $lastSale->invoice_number) {
            // Extract number from invoice (remove any prefix)
            $invoiceNumber = $lastSale->invoice_number;

            // If it starts with prefix, remove it, otherwise use as is
            if (strpos($invoiceNumber, 'INV-') === 0) {
                $lastNumber = intval(substr($invoiceNumber, 4));
            } else {
                $lastNumber = intval($invoiceNumber);
            }

            // If the last number is less than 678, start from 678, otherwise increment
            $nextNumber = max(678, $lastNumber + 1);
        }

        return str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    public function returns()
    {
        return $this->hasMany(ReturnsProduct::class, 'sale_id');
    }

    /**
     * Relationship: Approved by admin
     */
    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Relationship: Delivered by delivery man
     */
    public function deliveredBy()
    {
        return $this->belongsTo(User::class, 'delivered_by');
    }

    /**
     * Check if sale is pending approval
     */
    public function isPending()
    {
        return $this->status === 'pending';
    }

    /**
     * Check if sale is approved
     */
    public function isApproved()
    {
        return $this->status === 'confirm';
    }

    /**
     * Check if sale is rejected
     */
    public function isRejected()
    {
        return $this->status === 'rejected';
    }

    /**
     * Check if sale is delivered
     */
    public function isDelivered()
    {
        return $this->delivery_status === 'delivered';
    }

    /**
     * Get pending quantity for a specific product (for available stock calculation)
     */
    public static function getPendingQuantityForProduct($productId, $variantValue = null)
    {
        $query = self::where('status', 'pending')
            ->whereHas('items', function ($q) use ($productId, $variantValue) {
                $q->where('product_id', $productId);
                if ($variantValue) {
                    $q->where('variant_value', $variantValue);
                }
            });

        $sales = $query->with(['items' => function ($q) use ($productId, $variantValue) {
            $q->where('product_id', $productId);
            if ($variantValue) {
                $q->where('variant_value', $variantValue);
            }
        }])->get();

        return $sales->sum(function ($sale) {
            return $sale->items->sum('quantity');
        });
    }
}
