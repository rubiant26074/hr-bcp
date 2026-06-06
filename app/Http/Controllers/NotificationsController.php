<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationsController extends Controller
{
    public function index(Request $request)
    {
        $user = current_user();
        $companyId = current_company_id();
        $messages = [];

        if ($request->isMethod('post')) {
            $action = (string) $request->input('action', '');
            if ($action === 'read_one') {
                $id = (int) $request->input('id', 0);
                if ($id > 0) {
                    Notification::where('company_id', $companyId)
                        ->where('user_id', (int) ($user['id'] ?? 0))
                        ->where('id', $id)
                        ->update(['is_read' => 1]);
                }
            } elseif ($action === 'read_all') {
                Notification::where('company_id', $companyId)
                    ->where('user_id', (int) ($user['id'] ?? 0))
                    ->update(['is_read' => 1]);
                $messages[] = 'Semua notifikasi ditandai sudah dibaca.';
            }
        }

        $rows = Notification::where('company_id', $companyId)
            ->where('user_id', (int) ($user['id'] ?? 0))
            ->orderByDesc('id')
            ->get();

        return view('modules.notifications.index', compact('user', 'companyId', 'rows', 'messages'));
    }
}
