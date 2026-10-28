<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffExpense extends Model
{
    use HasFactory;

    protected $fillable = [
        'staff_id',
        'expense_type',
        'amount',
        'description',
        'expense_date',
        'receipt_image',
        'status',
        'admin_notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'date',
    ];

    /**
     * Get the staff member who created this expense.
     */
    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    /**
     * Scope to get pending expenses
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to get approved expenses
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope to get rejected expenses
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }
}
