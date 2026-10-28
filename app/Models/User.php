<?php


namespace App\Models;

use App\Models\Attendance;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'contact',
        'staff_type',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
    ];

    // Relationship: User has many attendances
    public function attendances()
    {
        return $this->hasMany(Attendance::class, 'user_id', 'id');
    }

    // Relationship: User has one UserDetail
    public function userDetail()
    {
        return $this->hasOne(UserDetail::class, 'user_id', 'id');
    }

    // Relationship: User (staff) has many staff products
    public function staffProducts()
    {
        return $this->hasMany(StaffProduct::class, 'staff_id', 'id');
    }

    /**
     * Check if user has a specific permission based on staff type
     */
    public function hasPermission($permissionKey)
    {
        if ($this->role === 'admin') {
            return true; // Admin has all permissions
        }

        if ($this->role !== 'staff') {
            return false;
        }

        // Permission mapping based on staff type
        $staffPermissions = [
            'salesman' => [
                'menu_dashboard',
                'menu_products',
                'menu_products_list',
                'menu_products_brand',
                'menu_products_category',
                'menu_products_variant',
                'menu_sales',
                'menu_sales_add',
                'menu_sales_list',
                'menu_sales_pos',
                'menu_quotation',
                'menu_quotation_add',
                'menu_quotation_list',
                'menu_customer',
                'menu_customer_add',
                'menu_customer_list',
                'menu_return',
                'menu_return_customer_add',
                'menu_return_customer_list'
            ],
            'delivery_man' => [
                'menu_dashboard',
                'menu_products',
                'menu_products_list',
                'menu_sales',
                'menu_sales_list',
                'menu_customer',
                'menu_customer_list',
                'menu_delivery'
            ],
            'shop_staff' => [
                'menu_dashboard',
                'menu_products',
                'menu_products_list',
                'menu_products_brand',
                'menu_products_category',
                'menu_products_variant',
                'menu_sales',
                'menu_sales_add',
                'menu_sales_list',
                'menu_sales_pos',
                'menu_quotation',
                'menu_quotation_add',
                'menu_quotation_list',
                'menu_customer',
                'menu_customer_add',
                'menu_customer_list',
                'menu_purchase',
                'menu_purchase_order',
                'menu_purchase_grn',
                'menu_return',
                'menu_return_customer_add',
                'menu_return_customer_list',
                'menu_return_supplier_add',
                'menu_return_supplier_list',
                'menu_supplier',
                'menu_supplier_add',
                'menu_supplier_list'
            ]
        ];

        $allowedPermissions = $staffPermissions[$this->staff_type] ?? ['menu_dashboard'];
        return in_array($permissionKey, $allowedPermissions);
    }

    /**
     * Check if user is admin
     */
    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    /**
     * Check if user is staff
     */
    public function isStaff()
    {
        return $this->role === 'staff';
    }

    /**
     * Check if user is a salesman
     */
    public function isSalesman()
    {
        return $this->role === 'staff' && $this->staff_type === 'salesman';
    }

    /**
     * Check if user is a delivery man
     */
    public function isDeliveryMan()
    {
        return $this->role === 'staff' && $this->staff_type === 'delivery_man';
    }

    /**
     * Check if user is shop staff
     */
    public function isShopStaff()
    {
        return $this->role === 'staff' && $this->staff_type === 'shop_staff';
    }

    /**
     * Get staff type label
     */
    public function getStaffTypeLabelAttribute()
    {
        return match ($this->staff_type) {
            'salesman' => 'Salesman',
            'delivery_man' => 'Delivery Man',
            'shop_staff' => 'Shop Staff',
            default => 'Staff',
        };
    }

    /**
     * Relationship: Approved sales (for admin)
     */
    public function approvedSales()
    {
        return $this->hasMany(Sale::class, 'approved_by', 'id');
    }

    /**
     * Relationship: Delivered sales (for delivery man)
     */
    public function deliveredSales()
    {
        return $this->hasMany(Sale::class, 'delivered_by', 'id');
    }

    /**
     * Relationship: Collected payments (for delivery man)
     */
    public function collectedPayments()
    {
        return $this->hasMany(Payment::class, 'collected_by', 'id');
    }

    /**
     * Relationship: User has many location records
     */
    public function userLocations()
    {
        return $this->hasMany(UserLocation::class, 'user_id', 'id');
    }

    /**
     * Get latest location for user
     */
    public function latestLocation()
    {
        return $this->hasOne(UserLocation::class, 'user_id', 'id')->latestOfMany();
    }
}
