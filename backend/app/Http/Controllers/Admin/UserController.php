<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index()
    {
        $users = User::orderBy('created_at', 'desc')->get();

        return view('admin.users', compact('users'));
    }

    public function toggleStatus(Request $request, User $user)
    {
        if ($request->user()->id === $user->id) {
            return back()->withErrors(['user' => 'Vous ne pouvez pas désactiver votre propre compte administrateur.']);
        }

        $user->is_active = ! $user->is_active;
        $user->save();

        return back()->with('success', 'Statut du compte mis à jour.');
    }
}
