<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffReturn extends Model
{
    use HasFactory;

    protected $fillable = [
        'staff_id',
        'sale_id',
        'product_id',
        'customer_id',
        'quantity',
        'unit_price',
        'total_amount',
        'is_damaged',
        'reason',
        'notes',
        'status',
    ];

    protected $casts = [
        'is_damaged' => 'boolean',
        'unit_price' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    /**
     * Get the staff member who processed the return.
     */
    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    /**
     * Get the sale this return is for.
     */
    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * Get the product that was returned.
     */
    public function product()
    {
        return $this->belongsTo(ProductDetail::class);
    }

    /**
     * Get the customer who returned the product.
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
