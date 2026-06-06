<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class AccountController extends Controller
{
    public function index(Request $request)
    {
        $current = current_user();
        $user = $current ? User::find((int) ($current['id'] ?? 0)) : null;
        if (!$user) {
            abort(404, 'User not found');
        }

        if ($request->isMethod('post')) {
            $data = $request->validate([
                'password' => ['nullable','string','min:8','confirmed'],
                'signature_file' => ['nullable','file','max:5120'],
            ]);

            if (!empty($data['password'])) {
                $user->password_hash = password_hash((string) $data['password'], PASSWORD_DEFAULT);
            }

            if ($request->hasFile('signature_file')) {
                $file = $request->file('signature_file');
                if ($file->isValid()) {
                    $ext = strtolower($file->getClientOriginalExtension());
                    $allowed = ['jpg','jpeg','png'];
                    $mime = $file->getMimeType();
                    $allowedMime = ['image/jpeg','image/png'];
                    if (!in_array($ext, $allowed, true) || !in_array($mime, $allowedMime, true)) {
                        return back()->withErrors(['signature_file' => 'Tanda tangan harus JPG/PNG.'])->withInput();
                    }
                    $dir = public_path('uploads/signatures');
                    if (!File::exists($dir)) {
                        File::makeDirectory($dir, 0755, true);
                    }
                    $filename = 'signature_' . uniqid() . '.' . $ext;
                    $file->move($dir, $filename);
                    $user->signature_path = 'uploads/signatures/' . $filename;
                }
            }

            $user->save();
            return redirect()->route('account', ['saved' => 1]);
        }

        return view('modules.account.index', compact('user'));
    }
}
