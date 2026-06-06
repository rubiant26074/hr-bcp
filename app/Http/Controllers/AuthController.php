<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Company;
use App\Models\RoleDefinition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class AuthController extends Controller
{
    public function loginForm()
    {
        return view('auth.login', [
            'error' => session('login_error', ''),
            'success' => session('auth_success', ''),
            'companies' => Company::orderBy('id')->get(),
        ]);
    }

    public function login(Request $request)
    {
        $email = trim((string) $request->input('email'));
        $password = (string) $request->input('password');

        $user = User::where('email', $email)->first();
        $storedHash = (string) ($user->password_hash ?? $user->password ?? '');
        if ($user && $storedHash !== '' && password_verify($password, $storedHash)) {
            if (isset($user->is_active) && (int) $user->is_active !== 1) {
                return back()->with('login_error', 'Akun Anda belum aktif. Silakan hubungi Admin untuk aktivasi.');
            }

            if ($this->requiresEmailVerification($user)) {
                return back()->with('login_error', 'Email belum diverifikasi. Silakan cek email Anda lalu klik link verifikasi.');
            }

            $userArr = [
                'id' => $user->id,
                'company_id' => $user->company_id,
                'employee_id' => $user->employee_id ?? null,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ];
            session(['user' => $userArr]);
            if (!empty($user->company_id)) {
                session(['company_id' => (int) $user->company_id]);
            }
            if (!$request->session()->has('theme')) {
                session(['theme' => 'bcp_form']);
            }
            $request->session()->regenerate();
            return redirect()->route('dashboard');
        }

        return back()->with('login_error', 'Email atau password salah.');
    }

    public function registerForm()
    {
        if (is_logged_in()) {
            return redirect()->route('dashboard');
        }

        return view('auth.register', [
            'error' => session('register_error', ''),
            'success' => session('register_success', ''),
        ]);
    }

    public function register(Request $request)
    {
        if (is_logged_in()) {
            return redirect()->route('dashboard');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $defaultRole = RoleDefinition::where('name', 'Employee')->value('name')
            ?: (RoleDefinition::orderBy('id')->value('name') ?: 'Employee');

        $passwordHash = password_hash((string) $data['password'], PASSWORD_DEFAULT);
        $emailVerificationEnabled = $this->isEmailVerificationEnabled();
        $payload = [
            'company_id' => null,
            'employee_id' => null,
            'name' => trim((string) $data['name']),
            'email' => strtolower(trim((string) $data['email'])),
            'role' => $defaultRole,
        ];

        if (Schema::hasColumn('users', 'password_hash')) {
            $payload['password_hash'] = $passwordHash;
        }
        if (Schema::hasColumn('users', 'password')) {
            $payload['password'] = $passwordHash;
        }
        if (Schema::hasColumn('users', 'must_verify_email')) {
            $payload['must_verify_email'] = $emailVerificationEnabled ? 1 : 0;
        }
        if (Schema::hasColumn('users', 'is_active')) {
            // Semua pendaftaran mandiri (web/mobile) wajib aktivasi admin.
            $payload['is_active'] = 0;
        }
        if (Schema::hasColumn('users', 'email_verified_at')) {
            $payload['email_verified_at'] = $emailVerificationEnabled ? null : now();
        }

        $user = User::create($payload);

        if ($emailVerificationEnabled) {
            $this->issueEmailVerificationToken($user);

            return redirect()->route('register.pending', ['email' => $user->email])
                ->with('register_success', 'Akun berhasil dibuat. Cek email untuk verifikasi lalu tunggu aktivasi Admin.');
        }

        return redirect()->route('login')
            ->with('auth_success', 'Akun berhasil dibuat dan menunggu aktivasi Admin.');
    }

    public function registerPending(Request $request)
    {
        if (is_logged_in()) {
            return redirect()->route('dashboard');
        }

        $email = strtolower(trim((string) $request->query('email', '')));
        return view('auth.register_pending', [
            'email' => $email,
            'success' => session('register_success', ''),
            'error' => session('register_error', ''),
        ]);
    }

    public function resendVerification(Request $request)
    {
        if (is_logged_in()) {
            return redirect()->route('dashboard');
        }

        if (!$this->isEmailVerificationEnabled()) {
            return redirect()->route('login')
                ->with('auth_success', 'Verifikasi email belum diaktifkan di sistem. Silakan login.');
        }

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = strtolower(trim((string) $data['email']));
        $user = User::where('email', $email)->first();
        if (!$user) {
            return redirect()->route('register.pending', ['email' => $email])
                ->with('register_error', 'Email belum terdaftar.');
        }

        if (!$this->requiresEmailVerification($user)) {
            return redirect()->route('login')
                ->with('auth_success', 'Email sudah terverifikasi. Silakan login.');
        }

        $this->issueEmailVerificationToken($user);

        return redirect()->route('register.pending', ['email' => $email])
            ->with('register_success', 'Link verifikasi baru sudah dikirim.');
    }

    public function verify(Request $request, string $token)
    {
        if (!$this->isEmailVerificationEnabled()) {
            return redirect()->route('login')
                ->with('auth_success', 'Verifikasi email belum diaktifkan di sistem. Silakan login.');
        }

        if ($token === '') {
            return redirect()->route('login')->with('login_error', 'Link verifikasi tidak valid.');
        }

        $email = strtolower(trim((string) $request->query('email', '')));
        if ($email === '') {
            return redirect()->route('login')->with('login_error', 'Link verifikasi tidak valid.');
        }

        $user = User::where('email', $email)->first();
        if (!$user) {
            return redirect()->route('login')->with('login_error', 'Link verifikasi tidak valid.');
        }

        if (!$this->requiresEmailVerification($user)) {
            return redirect()->route('login')->with('auth_success', 'Email sudah terverifikasi. Silakan login.');
        }

        $tokenHash = hash('sha256', $token);
        $verification = DB::table('email_verification_tokens')
            ->where('user_id', $user->id)
            ->where('token_hash', $tokenHash)
            ->whereNull('used_at')
            ->first();

        if (!$verification) {
            return redirect()->route('login')->with('login_error', 'Link verifikasi tidak valid.');
        }

        if (Carbon::parse((string) $verification->expires_at)->isPast()) {
            return redirect()->route('register.pending', ['email' => $email])
                ->with('register_error', 'Link verifikasi sudah kedaluwarsa. Silakan kirim ulang.');
        }

        DB::table('email_verification_tokens')
            ->where('user_id', $user->id)
            ->whereNull('used_at')
            ->update([
                'used_at' => now(),
                'updated_at' => now(),
            ]);

        $user->email_verified_at = now();
        $user->must_verify_email = 0;
        $user->save();

        return redirect()->route('login')
            ->with('auth_success', 'Email berhasil diverifikasi. Anda sudah bisa login.');
    }

    public function logout(Request $request)
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }

    private function requiresEmailVerification(User $user): bool
    {
        if (!$this->isEmailVerificationEnabled()) {
            return false;
        }

        $mustVerify = (int) ($user->must_verify_email ?? 0) === 1;
        return $mustVerify && empty($user->email_verified_at);
    }

    private function issueEmailVerificationToken(User $user): void
    {
        if (!Schema::hasTable('email_verification_tokens')) {
            return;
        }

        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expiresAt = now()->addHours(24);

        DB::table('email_verification_tokens')
            ->where('user_id', $user->id)
            ->delete();

        DB::table('email_verification_tokens')->insert([
            'user_id' => $user->id,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
            'used_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $verifyUrl = route('register.verify', [
            'token' => $rawToken,
            'email' => $user->email,
        ]);

        try {
            Mail::send('emails.verify_registration', [
                'name' => (string) $user->name,
                'verifyUrl' => $verifyUrl,
                'expiresAt' => $expiresAt,
            ], function ($message) use ($user): void {
                $message->to((string) $user->email, (string) $user->name)
                    ->subject('Verifikasi Email Akun HR-BCP');
            });
        } catch (\Throwable $e) {
            Log::error('Failed to send registration verification email', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function isEmailVerificationEnabled(): bool
    {
        return Schema::hasTable('email_verification_tokens')
            && Schema::hasColumn('users', 'must_verify_email')
            && Schema::hasColumn('users', 'email_verified_at');
    }
}
