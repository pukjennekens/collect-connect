<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\SaveAddressRequest;
use App\Models\Address;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Response;

class AddressController extends Controller
{
    public function index(Request $request): Response
    {
        return inertia('account/addresses', [
            'addresses' => ($request->user() ?? abort(401))->addresses()->orderByDesc('is_default')->orderBy('id')->get(),
            'countries' => ShippingMethod::query()->where('is_active', true)->get()->flatMap(fn (ShippingMethod $method): array => array_keys($method->country_regions ?? []))->merge(['NL'])->unique()->values(),
        ]);
    }

    public function store(SaveAddressRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $user = User::query()->lockForUpdate()->findOrFail(($request->user() ?? abort(401))->id);
            $data = [...$request->validated(), 'email' => $request->validated('email') ?: $user->email];
            $identity = collect($data)->except('is_default')->all();
            $existing = $user->addresses()->where($identity)->first();
            $isDefault = $request->boolean('is_default') || $existing?->is_default || ! $user->addresses()->where('is_default', true)->exists();
            if ($isDefault) {
                $user->addresses()->update(['is_default' => false]);
            }
            $user->addresses()->updateOrCreate($identity, ['is_default' => $isDefault]);
        });

        return back()->with('status', 'Adres opgeslagen.');
    }

    public function update(SaveAddressRequest $request, Address $address): RedirectResponse
    {
        Gate::authorize('update', $address);
        DB::transaction(function () use ($request, $address): void {
            $user = User::query()->lockForUpdate()->findOrFail(($request->user() ?? abort(401))->id);
            $isDefault = $request->boolean('is_default') || $address->is_default;
            if ($isDefault) {
                $user->addresses()->update(['is_default' => false]);
            }
            $address->update([...$request->validated(), 'email' => $request->validated('email') ?: $user->email, 'is_default' => $isDefault]);
        });

        return back()->with('status', 'Adres bijgewerkt.');
    }

    public function destroy(Request $request, Address $address): RedirectResponse
    {
        Gate::authorize('delete', $address);
        DB::transaction(function () use ($request, $address): void {
            $user = User::query()->lockForUpdate()->findOrFail(($request->user() ?? abort(401))->id);
            $address->delete();
            if (! $user->addresses()->where('is_default', true)->exists()) {
                $user->addresses()->oldest('id')->first()?->update(['is_default' => true]);
            }
        });

        return back()->with('status', 'Adres verwijderd.');
    }
}
