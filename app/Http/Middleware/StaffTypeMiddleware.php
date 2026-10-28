<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class StaffTypeMiddleware
{
    /**
     * Handle an incoming request.
     * 
     * @param string $staffTypes Comma-separated list of allowed staff types (salesman, delivery_man, shop_staff)
     */
    public function handle(Request $request, Closure $next, string $staffTypes): Response
    {
        $user = Auth::user();

        if (!$user) {
            return redirect()->route('login');
        }

        // Admin has access to everything
        if ($user->isAdmin()) {
            return $next($request);
        }

        // Check if user is staff
        if (!$user->isStaff()) {
            abort(403, 'Access denied. Staff access required.');
        }

        // Check if staff type is allowed
        $allowedTypes = explode(',', $staffTypes);

        if (!in_array($user->staff_type, $allowedTypes)) {
            abort(403, 'Access denied. You do not have permission to access this resource.');
        }

        return $next($request);
    }
}
