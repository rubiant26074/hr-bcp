<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = current_user();
        $userRole = (string) ($user['role'] ?? '');
        $allowed = $roles;
        $hasRole = in_array($userRole, $allowed, true);
        if (!$hasRole && $userRole !== '') {
            foreach ($allowed as $role) {
                if ($role === 'HR' && preg_match('/^HR\\d+$/', $userRole)) {
                    $hasRole = true;
                    break;
                }
            }
        }
        if (!$user || !$hasRole) {
            abort(403, 'Access denied.');
        }

        $path = ltrim($request->path(), '/');
        $segments = array_values(array_filter(explode('/', $path), static function ($s) {
            return $s !== '';
        }));
        if (!empty($segments)) {
            $last = end($segments);
            if (ctype_digit($last)) {
                array_pop($segments);
                $path = implode('/', $segments);
            }
        }
        $scriptPath = 'modules/' . $path;
        if (!str_ends_with($scriptPath, '.php')) {
            if (str_contains($scriptPath, '/')) {
                if (str_ends_with($scriptPath, '/')) {
                    $scriptPath .= 'index.php';
                } else {
                    $segments = explode('/', $scriptPath);
                    $last = end($segments);
                    if ($last === 'dashboard' || $last === 'company' || $last === 'employees' || $last === 'contracts' || $last === 'rbac' || $last === 'users' || $last === 'settings') {
                        $scriptPath .= '/index.php';
                    } elseif ($last === 'attendance' || $last === 'payroll') {
                        $scriptPath .= '/index.php';
                    } else {
                        $scriptPath .= '.php';
                    }
                }
            } else {
                $scriptPath .= '/index.php';
            }
        }
        if (!rbac_route_allowed($userRole, $scriptPath)) {
            abort(403, 'Access denied by RBAC policy.');
        }

        return $next($request);
    }
}
