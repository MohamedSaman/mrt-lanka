<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CategoryList extends Model
{
    use HasFactory;
    protected $fillable = [
        'category_name',
    ];

    public function products()
    {
        return $this->hasMany(ProductDetail::class, 'category_id');
    }
}
