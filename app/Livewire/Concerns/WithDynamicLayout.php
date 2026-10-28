<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Facades\Auth;

trait WithDynamicLayout
{
    /**
     * Get the layout based on the authenticated user's role.
     *
     * @return string
     */
    public function getLayoutProperty()
    {
        if (!Auth::check()) {
            return 'components.layouts.app';
        }

        $user = Auth::user();

        return match ($user->role) {
            'admin' => 'components.layouts.admin',
            'staff' => 'components.layouts.staff',
            default => 'components.layouts.app',
        };
    }

    /**
     * Public method to get layout (for IDE support).
     *
     * @return string
     */
    public function layout()
    {
        return $this->layout;
    }

    /**
     * Check if the current user is a staff member.
     *
     * @return bool
     */
    public function isStaff(): bool
    {
        return Auth::check() && Auth::user()->role === 'staff';
    }

    /**
     * Check if the current user is an admin.
     *
     * @return bool
     */
    public function isAdmin(): bool
    {
        return Auth::check() && Auth::user()->role === 'admin';
    }

    /**
     * Get the current user's ID.
     *
     * @return int|null
     */
    public function getCurrentUserId(): ?int
    {
        return Auth::id();
    }

    /**
     * Get the sale type based on current user's role.
     * Returns 'staff' for staff users, 'admin' for admin users.
     *
     * @return string
     */
    public function getSaleType(): string
    {
        return $this->isStaff() ? 'staff' : 'admin';
    }

    /**
     * Boot the trait and set the layout dynamically.
     */
    public function bootWithDynamicLayout()
    {
        // This method is called automatically by Livewire when the trait is used
    }
}
