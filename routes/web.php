<?php

declare(strict_types=1);

use App\Http\Controllers\Account\OrderController as AccountOrderController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CartPageController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PaymentSimulationController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SetController;
use App\Http\Controllers\SetIndexController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\StockNotificationController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::redirect('/contact', '/pages/contact')->name('contact');
Route::get('/account', fn () => redirect()->route('account.orders.index'))->middleware('auth')->name('account');
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/sitemaps/{type}/{page}.xml', [SitemapController::class, 'show'])->whereNumber('page')->name('sitemap.child');

Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/{article:slug}', [BlogController::class, 'show'])->name('blog.show');
Route::get('/pages/{page:slug}', [PageController::class, 'show'])->name('pages.show');

Route::get('/onderdelen', [CatalogController::class, 'parts'])->name('catalog.parts');
Route::get('/minifiguren', [CatalogController::class, 'minifigs'])->name('catalog.minifigs');
Route::get('/zoeken', [CatalogController::class, 'search'])->name('catalog.search');

Route::get('/products/{product}', [ProductController::class, 'show'])->name('product.show');
Route::post('/products/{product}/stock-notifications', [StockNotificationController::class, 'store'])
    ->middleware('throttle:stock-notify')
    ->name('products.stock-notifications.store');

Route::get('/sets', SetIndexController::class)->name('sets.index');
Route::get('/sets/{set}', [SetController::class, 'show'])->name('sets.show');

Route::get('/cart', CartPageController::class)->name('cart.show');
Route::post('/cart/items', [CartController::class, 'store'])
    ->middleware('throttle:cart')->block(30, 10)
    ->name('cart.items.store');
Route::patch('/cart/items/{product}', [CartController::class, 'update'])
    ->middleware('throttle:cart')->block(30, 10)
    ->name('cart.items.update');
Route::delete('/cart/items/{product}', [CartController::class, 'destroy'])
    ->middleware('throttle:cart')->block(30, 10)
    ->name('cart.items.destroy');

Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
Route::post('/checkout', [CheckoutController::class, 'store'])
    ->middleware('throttle:checkout')->block(30, 10)
    ->name('checkout.store');
Route::get('/checkout/bevestiging/{order}', [CheckoutController::class, 'confirmation'])->name('checkout.confirmation');

// Local payment simulator standing in for a PSP's hosted page. Deliberately not
// registered in production, so the route does not exist there at all.
if (app()->environment((array) config('payment.simulator_environments', []))) {
    Route::get('/checkout/betaling/{order}', [PaymentSimulationController::class, 'show'])
        ->name('checkout.payment.simulate');
    Route::post('/checkout/betaling/{order}', [PaymentSimulationController::class, 'store'])
        ->middleware('throttle:checkout')->block(30, 10)
        ->name('checkout.payment.simulate.store');
}

Route::middleware('guest')->group(function (): void {
    Route::get('/forgot-password', [App\Http\Controllers\Auth\PasswordResetController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [App\Http\Controllers\Auth\PasswordResetController::class, 'store'])->middleware('throttle:6,1')->name('password.email');
    Route::get('/reset-password/{token}', [App\Http\Controllers\Auth\PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [App\Http\Controllers\Auth\PasswordResetController::class, 'update'])->middleware('throttle:6,1')->name('password.update');
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->middleware('throttle:login')->block(30, 10);
    Route::get('/register', [SessionController::class, 'registerForm'])->name('register');
    Route::post('/register', [SessionController::class, 'register'])->middleware('throttle:register')->block(30, 10);
});

Route::post('/logout', [SessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->prefix('account')->name('account.')->group(function (): void {
    Route::get('/profile', [App\Http\Controllers\Account\ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [App\Http\Controllers\Account\ProfileController::class, 'update'])->name('profile.update');
    Route::put('/password', [App\Http\Controllers\Account\ProfileController::class, 'password'])->middleware('throttle:6,1')->name('password.update');
    Route::get('/addresses', [App\Http\Controllers\Account\AddressController::class, 'index'])->name('addresses.index');
    Route::post('/addresses', [App\Http\Controllers\Account\AddressController::class, 'store'])->name('addresses.store');
    Route::patch('/addresses/{address}', [App\Http\Controllers\Account\AddressController::class, 'update'])->name('addresses.update');
    Route::delete('/addresses/{address}', [App\Http\Controllers\Account\AddressController::class, 'destroy'])->name('addresses.destroy');
    Route::get('/orders', [AccountOrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [AccountOrderController::class, 'show'])->name('orders.show');
    Route::get('/orders/{order}/summary', [AccountOrderController::class, 'summary'])->name('orders.summary');
    Route::get('/orders/{order}/invoice', [AccountOrderController::class, 'invoice'])->name('orders.invoice');
});
