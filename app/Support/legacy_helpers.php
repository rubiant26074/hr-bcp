<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

if (!function_exists('h')) {
    function h($str): string
    {
        return htmlspecialchars((string) $str, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('format_currency')) {
    function format_currency($amount): string
    {
        return 'Rp ' . number_format((float) $amount, 0, ',', '.');
    }
}

if (!function_exists('format_currency_id')) {
    function format_currency_id($amount, int $decimals = 2, bool $withRp = false): string
    {
        $formatted = number_format((float) $amount, $decimals, ',', '.');
        return $withRp ? ('Rp ' . $formatted) : $formatted;
    }
}

if (!function_exists('asset_url')) {
    function asset_url(string $path): string
    {
        return asset(ltrim($path, '/'));
    }
}

if (!function_exists('current_user')) {
    function current_user(): ?array
    {
        return session('user');
    }
}

if (!function_exists('is_logged_in')) {
    function is_logged_in(): bool
    {
        return current_user() !== null;
    }
}

if (!function_exists('current_company_id')) {
    function current_company_id(): int
    {
        if (session()->has('company_id')) {
            return (int) session('company_id');
        }
        $user = current_user();
        return $user && !empty($user['company_id']) ? (int) $user['company_id'] : 1;
    }
}

if (!function_exists('is_global_role')) {
    function is_global_role(?string $role): bool
    {
        return in_array(trim((string) $role), ['Super Admin', 'CEO', 'CFA', 'HR1', 'HR2'], true);
    }
}

if (!function_exists('current_user_has_global_scope')) {
    function current_user_has_global_scope(?array $user = null): bool
    {
        $user = $user ?? current_user();
        $role = trim((string) ($user['role'] ?? ''));
        if (is_global_role($role)) {
            return true;
        }
        return in_array(strtoupper($role), ['HR', 'HR1', 'HR2'], true);
    }
}

if (!function_exists('format_date_id')) {
    function format_date_id($dbDate): string
    {
        $dbDate = trim((string) $dbDate);
        if ($dbDate === '') {
            return '';
        }

        $dt = DateTime::createFromFormat('Y-m-d', $dbDate);
        if (!$dt || $dt->format('Y-m-d') !== $dbDate) {
            return $dbDate;
        }
        return $dt->format('d/m/Y');
    }
}

if (!function_exists('format_datetime_id')) {
    function format_datetime_id($dbDateTime, bool $withSeconds = false): string
    {
        $dbDateTime = trim((string) $dbDateTime);
        if ($dbDateTime === '') {
            return '';
        }

        $formats = [
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            'd/m/Y H:i:s',
            'd/m/Y H:i',
            'Y-m-d',
        ];
        $dt = null;
        foreach ($formats as $format) {
            $candidate = DateTime::createFromFormat($format, $dbDateTime);
            if ($candidate && $candidate->format($format) === $dbDateTime) {
                $dt = $candidate;
                break;
            }
        }
        if (!$dt) {
            $ts = strtotime($dbDateTime);
            if ($ts === false) {
                return $dbDateTime;
            }
            $dt = (new DateTime())->setTimestamp($ts);
        }

        return $dt->format($withSeconds ? 'd/m/Y H:i:s' : 'd/m/Y H:i');
    }
}

if (!function_exists('format_time_id')) {
    function format_time_id($dbTime, bool $withSeconds = false): string
    {
        $dbTime = trim((string) $dbTime);
        if ($dbTime === '') {
            return '';
        }

        $formats = [
            'H:i:s',
            'H:i',
            'g:i A',
            'g:i a',
            'h:i A',
            'h:i a',
        ];
        foreach ($formats as $format) {
            $dt = DateTime::createFromFormat($format, $dbTime);
            if ($dt && $dt->format($format) === $dbTime) {
                return $dt->format($withSeconds ? 'H:i:s' : 'H:i');
            }
        }

        $ts = strtotime($dbTime);
        if ($ts === false) {
            return $dbTime;
        }
        $dt = (new DateTime())->setTimestamp($ts);
        return $dt->format($withSeconds ? 'H:i:s' : 'H:i');
    }
}

if (!function_exists('date_input_to_db')) {
    function date_input_to_db($inputDate): ?string
    {
        $inputDate = trim((string) $inputDate);
        if ($inputDate === '') {
            return '';
        }

        $formats = [
            'Y-m-d',
            'd/m/Y',
            'j/n/Y',
            'd-m-Y',
            'j-n-Y',
            'm/d/Y',
            'n/j/Y',
            'm-d-Y',
            'n-j-Y',
            'd-M-y',
            'j-M-y',
            'd-M-Y',
            'j-M-Y',
        ];
        foreach ($formats as $format) {
            $dt = DateTime::createFromFormat($format, $inputDate);
            if ($dt && $dt->format($format) === $inputDate) {
                return $dt->format('Y-m-d');
            }
        }

        $idMonths = [
            'januari' => 'January',
            'februari' => 'February',
            'maret' => 'March',
            'april' => 'April',
            'mei' => 'May',
            'juni' => 'June',
            'juli' => 'July',
            'agustus' => 'August',
            'september' => 'September',
            'oktober' => 'October',
            'november' => 'November',
            'desember' => 'December',
        ];
        $normalized = str_ireplace(array_keys($idMonths), array_values($idMonths), $inputDate);
        foreach (['j F Y', 'd F Y'] as $format) {
            $dt = DateTime::createFromFormat($format, $normalized);
            if ($dt && $dt->format($format) === $normalized) {
                return $dt->format('Y-m-d');
            }
        }

        return null;
    }
}

if (!function_exists('date_input_value')) {
    function date_input_value($value): string
    {
        $dbDate = date_input_to_db($value);
        if ($dbDate === null || $dbDate === '') {
            return '';
        }
        return $dbDate;
    }
}

if (!function_exists('legacy_pdo')) {
    function legacy_pdo(): PDO
    {
        return DB::connection()->getPdo();
    }
}

if (!function_exists('rbac_route_allowed')) {
    function rbac_route_allowed(string $role, string $path): bool
    {
        return \App\Support\Rbac::isAllowedForPath($role, $path);
    }
}

if (!function_exists('rbac_key_allowed')) {
    function rbac_key_allowed(string $role, string $permissionKey): bool
    {
        return \App\Support\Rbac::isAllowedForKey($role, $permissionKey);
    }
}

if (!function_exists('normalize_route_path')) {
    function normalize_route_path(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = ltrim($path, '/');
        return $path;
    }
}
