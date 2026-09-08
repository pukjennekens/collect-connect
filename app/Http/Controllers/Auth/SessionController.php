<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Response;

class SessionController extends Controller
{
    public function create(): Response
    {
        return inertia('auth/login');
    }

    public function store(LoginRequest $request, CartService $cartService): RedirectResponse
    {
        $credentials = $request->safe()->only(['email', 'password']);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Ongeldige inloggegevens.']);
        }

        $request->session()->regenerate();
        $cartService->mergeGuestCartIntoUser(Auth::id() ?? abort(401));

        return redirect()->intended(route('account.orders.index'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    public function registerForm(): Response
    {
        return inertia('auth/register');
    }

    public function register(RegisterRequest $request, CartService $cartService): RedirectResponse
    {
        $validated = $request->validated();

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        Auth::login($user);
        $request->session()->regenerate();
        $cartService->mergeGuestCartIntoUser($user->id);

        return redirect()->route('account.orders.index');
    }
}
