<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class InitialPasswordController extends Controller
{
    public function edit(Request $request): Response|RedirectResponse
    {
        if (! $request->user()->must_change_password) {
            return to_route('dashboard');
        }

        return Inertia::render('auth/initial-password', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $updated = $user->newQuery()
            ->whereKey($user->getKey())
            ->where('must_change_password', true)
            ->update([
                'password' => Hash::make($request->validated('password')),
                'must_change_password' => false,
            ]);

        if ($updated) {
            $user->refresh();
            $request->session()->regenerate();
        }

        return to_route('dashboard');
    }
}
