<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BrandList extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'brand_name',
    ];

    /**
     * Get all products for this brand
     */
    public function products(): HasMany
    {
        return $this->hasMany(ProductDetail::class, 'brand_id');
    }
}
