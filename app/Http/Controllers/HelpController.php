<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HelpController extends Controller
{
    public function index(Request $request)
    {
        $user = current_user();
        return view('modules.help.index', compact('user'));
    }
}
