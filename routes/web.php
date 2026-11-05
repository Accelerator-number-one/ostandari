<?php

use App\Http\Controllers\Web\AdminController;
use App\Http\Controllers\Web\AuthPageController;
use App\Http\Controllers\Web\PublicSiteController;
use App\Http\Controllers\Web\SessionController;
use App\Http\Middleware\WebStaff;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\{StaffPageController, StaffPortalController, StaffPublicController};
use App\Http\Middleware\StaffPortalOwner;

Route::get('/staff/{slug}/pages/{path}', [StaffPublicController::class, 'page'])->where('slug', '[a-z0-9-]+')->where('path', '[\pL\pN][\pL\pN_-]*');
Route::get('/staff/{slug}/content/{id}', StaffPublicController::class)->where('slug', '[a-z0-9-]+')->whereNumber('id');
Route::get('/staff/{slug}', StaffPublicController::class)->where('slug', '[a-z0-9-]+');
Route::get('/staff-admin/{slug}/login', [StaffPortalController::class, 'loginPage'])->where('slug', '[a-z0-9-]+');
Route::post('/staff-admin/{slug}/login', [StaffPortalController::class, 'login'])->where('slug', '[a-z0-9-]+')
    ->middleware([\App\Http\Middleware\NormalizeIdentity::class, 'throttle:auth']);
Route::prefix('staff-admin/{slug}')->where(['slug' => '[a-z0-9-]+'])->middleware(StaffPortalOwner::class)->group(function () {
    Route::get('/', [StaffPortalController::class, 'dashboard']);
    Route::post('profile', [StaffPortalController::class, 'profile']);
    Route::post('logout', [StaffPortalController::class, 'logout']);
    Route::post('password', [StaffPortalController::class, 'password'])->middleware('throttle:auth');
    Route::get('pages/new', [StaffPageController::class, 'editor']);
    Route::post('pages', [StaffPageController::class, 'save']);
    Route::get('pages/{id}/edit', [StaffPageController::class, 'editor'])->whereNumber('id');
    Route::post('pages/{id}', [StaffPageController::class, 'save'])->whereNumber('id');
    Route::post('pages/{id}/delete', [StaffPageController::class, 'remove'])->whereNumber('id');
    Route::post('pages/{id}/restore', [StaffPageController::class, 'restore'])->whereNumber('id');
    Route::get('items/new', [StaffPortalController::class, 'editor']);
    Route::post('items', [StaffPortalController::class, 'save']);
    Route::get('items/{id}/edit', [StaffPortalController::class, 'editor'])->whereNumber('id');
    Route::post('items/{id}', [StaffPortalController::class, 'save'])->whereNumber('id');
    Route::post('items/{id}/delete', [StaffPortalController::class, 'remove'])->whereNumber('id');
    Route::post('items/{id}/restore', [StaffPortalController::class, 'restore'])->whereNumber('id');
    Route::post('assets', [StaffPortalController::class, 'upload']);
});
Route::prefix('web-api/v1/staff-portals/{slug}')->where(['slug' => '[a-z0-9-]+'])->middleware(StaffPortalOwner::class)->group(function () {
    Route::get('assets', [StaffPortalController::class, 'assets']);
    Route::post('assets', [StaffPortalController::class, 'upload']);
});
Route::get('/staff-assets/{id}/{name}', [StaffPortalController::class, 'image'])->whereNumber('id')->where('name', '[a-zA-Z0-9-]+\.(?:jpg|jpeg|png|webp)')
    ->withoutMiddleware([\Illuminate\Cookie\Middleware\EncryptCookies::class,\Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,\Illuminate\Session\Middleware\StartSession::class,\Illuminate\View\Middleware\ShareErrorsFromSession::class,\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class]);

// The same controllers, permissions and tenant checks as the external API,
// with Laravel's web session and CSRF middleware for the Blade application.
Route::prefix('web-api')->name('web.')->middleware(\App\Http\Middleware\NormalizeIdentity::class)->group(base_path('routes/api.php'));
Route::post('/session/login', [SessionController::class, 'login'])->middleware('throttle:auth');
Route::post('/session/verify', [SessionController::class, 'verify'])->middleware('throttle:auth');
Route::post('/session/logout', [SessionController::class, 'logout']);
Route::get('/login', [AuthPageController::class, 'login'])->name('login');
Route::get('/register', [AuthPageController::class, 'register']);
Route::get('/panel/admin/login', [AuthPageController::class, 'staff']);
Route::get('/county-admin/{slug}/login', [AuthPageController::class, 'county'])->where('slug', '[a-z0-9-]+');
Route::get('/county-admin/{slug}/{path?}', [AdminController::class, 'county'])->middleware(WebStaff::class)
    ->where(['slug' => '[a-z0-9-]+', 'path' => '.*'])->name('county.admin');
Route::get('/panel/admin/{path?}', [AdminController::class, 'show'])->middleware(WebStaff::class)->where('path', '.*');
Route::get('/citizen', [AuthPageController::class, 'citizen']);
Route::withoutMiddleware([\Illuminate\Cookie\Middleware\EncryptCookies::class,\Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,\Illuminate\Session\Middleware\StartSession::class,\Illuminate\View\Middleware\ShareErrorsFromSession::class,\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class])->group(function () {
    Route::get('/media-assets/{id}/{name}', [\App\Http\Controllers\Web\PublicImageController::class, 'show'])->whereNumber('id')->where('name','[a-zA-Z0-9-]+\.(?:jpg|jpeg|png|webp)');
    Route::get('/storage/{path}', [\App\Http\Controllers\Web\PublicImageController::class, 'legacy'])->where('path','[a-zA-Z0-9/_-]+\.(?:jpg|jpeg|png|webp)');
});
Route::get('/page-assets/{id}/{name}', \App\Http\Controllers\Web\PageImageController::class)->whereNumber('id')->where('name','[a-zA-Z0-9-]+\.(?:jpg|jpeg|png|webp)')
    ->withoutMiddleware([\Illuminate\Cookie\Middleware\EncryptCookies::class,\Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,\Illuminate\Session\Middleware\StartSession::class,\Illuminate\View\Middleware\ShareErrorsFromSession::class,\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class]);
Route::get('/{path?}', PublicSiteController::class)->where('path', '.*');
