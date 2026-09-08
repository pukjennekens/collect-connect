<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\UpdatePasswordRequest;
use App\Http\Requests\Account\UpdateProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        return inertia('account/profile', ['profile' => ($request->user() ?? abort(401))->only(['name', 'email'])]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user() ?? abort(401);
        $user->fill($request->validated());
        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }
        $user->save();

        return back()->with('status', 'Accountgegevens opgeslagen.');
    }

    public function password(UpdatePasswordRequest $request): RedirectResponse
    {
        ($request->user() ?? abort(401))->forceFill([
            'password' => $request->validated('password'),
            'remember_token' => Str::random(60),
        ])->save();
        $request->session()->regenerate();

        return back()->with('status', 'Wachtwoord gewijzigd.');
    }
}
