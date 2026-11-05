<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AnnualSloganController;
use App\Http\Controllers\Api\CommunityController;
use App\Http\Controllers\Api\FormController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\LookupController;
use App\Http\Controllers\Api\PollController;
use App\Http\Controllers\Api\PublicController;
use App\Http\Controllers\Api\ResourceController;
use App\Http\Controllers\Api\SystemController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\UserAdminController;
use App\Services\ResourceRegistry;
use App\Support\Api;
use Illuminate\Support\Facades\Route;

Route::pattern('id', '[0-9]+');
Route::prefix('v1')->middleware('throttle:api')->group(function () {
    Route::get('health', [SystemController::class, 'health']);
    Route::get('home', [PublicController::class, 'home']);
    Route::get('portals/{slug}', [PublicController::class, 'home']);
    Route::get('settings', [PublicController::class, 'settings']);
    Route::get('menus', [PublicController::class, 'menus']);
    Route::get('search', [PublicController::class, 'search']);
    Route::post('assistant/query', [PublicController::class, 'assistant'])->middleware('throttle:contact');
    Route::get('contents', [PublicController::class, 'contents']);
    Route::get('contents/by-slug/{type}/{slug}', [PublicController::class, 'contentSlug']);
    Route::get('contents/{id}', [PublicController::class, 'content']);
    foreach (['news' => 'news', 'announcements' => 'announcement', 'resolutions' => 'resolution', 'pages' => 'page', 'publications' => 'publication', 'tenders' => 'tender', 'contracts' => 'contract', 'calls' => 'call', 'events' => 'event', 'duties' => 'duty', 'documents' => 'document'] as $route => $type) {
        Route::get($route, [PublicController::class, 'contents'])->defaults('type', $type);
    }
    Route::get('counties/by-slug/{slug}', [PublicController::class, 'county']);
    foreach (['categories', 'counties', 'organizations', 'members', 'services', 'links', 'faqs', 'quotes', 'galleries', 'polls', 'forms'] as $resource) {
        Route::get($resource, [PublicController::class, 'catalog'])->defaults('resource', $resource);
        Route::get($resource.'/{id}', [PublicController::class, 'catalogShow'])->defaults('resource', $resource);
    }
    Route::get('polls/{id}/results', [PollController::class, 'result']);
    Route::get('forum/topics', [CommunityController::class, 'topics']);
    Route::get('forum/topics/{id}', [CommunityController::class, 'topic']);
    Route::post('contact', [TicketController::class, 'contact'])->middleware('throttle:contact');
    Route::post('events/subscribe', [\App\Http\Controllers\Api\EventSubscriptionController::class, 'request'])->middleware('throttle:contact');
    Route::post('events/confirm', [\App\Http\Controllers\Api\EventSubscriptionController::class, 'confirm'])->middleware('throttle:auth');
    Route::post('newsletter/subscribe', [CommunityController::class, 'subscribe'])->middleware('throttle:contact');
    Route::post('newsletter/confirm', [CommunityController::class, 'confirm'])->middleware('throttle:auth');
    Route::post('newsletter/unsubscribe', [CommunityController::class, 'unsubscribe'])->middleware('throttle:auth');
    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth');
        Route::post('register', [AuthController::class, 'requestOtp'])->middleware('throttle:otp');
        Route::post('citizen/login', [AuthController::class, 'citizenLogin'])->middleware('throttle:otp');
        Route::post('otp/request', [AuthController::class, 'requestOtp'])->middleware('throttle:otp');
        Route::post('otp/verify', [AuthController::class, 'verifyOtp'])->middleware('throttle:auth');
        Route::post('password/forgot', [AuthController::class, 'forgot'])->middleware('throttle:auth');
        Route::post('password/reset', [AuthController::class, 'reset'])->middleware('throttle:auth');
        Route::middleware(['auth:sanctum', 'active'])->group(function () {
            Route::get('me', [AuthController::class, 'me']);
            Route::patch('profile', [AuthController::class, 'profile']);
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('logout-all', [AuthController::class, 'logoutAll']);
            Route::post('token/rotate', [AuthController::class, 'rotate']);
            Route::put('password', [AuthController::class, 'password']);
        });
    });
    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::get('media', [MediaController::class, 'index'])->middleware('portal');
        Route::post('media', [MediaController::class, 'store'])->middleware('portal');
        Route::get('media/{id}/download', [MediaController::class, 'download'])->middleware('portal');
        Route::delete('media/{id}', [MediaController::class, 'destroy'])->middleware('portal');
        Route::post('contents/{id}/comments', [CommunityController::class, 'comment']);
        Route::post('polls/{id}/vote', [PollController::class, 'vote']);
        Route::post('forum/topics', [CommunityController::class, 'createTopic']);
        Route::post('forum/topics/{id}/replies', [CommunityController::class, 'reply']);
        Route::prefix('citizen')->group(function () {
            Route::get('dashboard', [SystemController::class, 'citizenDashboard']);
            Route::get('tickets', [TicketController::class, 'index']);
            Route::post('tickets', [TicketController::class, 'store']);
            Route::get('tickets/{reference}', [TicketController::class, 'show']);
            Route::post('tickets/{reference}/messages', [TicketController::class, 'reply']);
            Route::post('forms/{id}/submissions', [FormController::class, 'submit']);
            Route::get('submissions', [FormController::class, 'mine']);
            Route::get('submissions/{reference}', [FormController::class, 'showMine']);
            Route::get('notifications', [SystemController::class, 'notifications']);
            Route::post('notifications/read-all', [SystemController::class, 'markAllRead']);
            Route::post('notifications/{id}/read', [SystemController::class, 'markRead'])->where('id', '[0-9a-fA-F-]{36}');
        });
        Route::prefix('admin')->middleware(['staff', 'portal'])->group(function () {
            Route::prefix('custom-pages')->name('admin.custom-pages.')->group(function () {
                Route::get('/', [\App\Http\Controllers\Api\CustomPageController::class, 'index'])->name('index');
                Route::post('/', [\App\Http\Controllers\Api\CustomPageController::class, 'store'])->name('store');
                Route::get('images', [\App\Http\Controllers\Api\CustomPageController::class, 'images'])->name('images');
                Route::post('image', [\App\Http\Controllers\Api\CustomPageController::class, 'image'])->name('image');
                Route::get('{id}', [\App\Http\Controllers\Api\CustomPageController::class, 'show'])->whereNumber('id')->name('show');
                Route::patch('{id}', [\App\Http\Controllers\Api\CustomPageController::class, 'update'])->whereNumber('id')->name('update');
                Route::delete('{id}', [\App\Http\Controllers\Api\CustomPageController::class, 'destroy'])->whereNumber('id')->name('destroy');
                Route::post('{id}/restore', [\App\Http\Controllers\Api\CustomPageController::class, 'restore'])->whereNumber('id')->name('restore');
            });
            Route::get('county-portal', [\App\Http\Controllers\Api\CountyPortalController::class, 'show']);
            Route::put('county-portal', [\App\Http\Controllers\Api\CountyPortalController::class, 'update']);
            Route::post('county-portal/menus', [\App\Http\Controllers\Api\CountyPortalController::class, 'menu']);
            Route::get('resources', [SystemController::class, 'schema']);
            Route::get('lookups/{resource}', [LookupController::class, 'index']);
            Route::get('dashboard', [SystemController::class, 'dashboard'])->middleware('permission:dashboard.view');
            Route::get('exports/{resource}', [SystemController::class, 'export']);
            Route::get('audit-logs', [SystemController::class, 'audit'])->middleware('permission:audit.view');
            Route::get('annual-slogan', [AnnualSloganController::class, 'show']);
            Route::post('annual-slogan', [AnnualSloganController::class, 'update'])->name('admin.annual-slogan.update');
            Route::get('settings', [SystemController::class, 'settings'])->middleware('permission:settings.manage');
            Route::put('settings', [SystemController::class, 'updateSettings'])->middleware('permission:settings.manage');
            Route::middleware('permission:users.manage')->group(function () {
                Route::get('users', [UserAdminController::class, 'index']);
                Route::post('users', [UserAdminController::class, 'store']);
                Route::get('users/{id}', [UserAdminController::class, 'show']);
                Route::patch('users/{id}', [UserAdminController::class, 'update']);
                Route::delete('users/{id}', [UserAdminController::class, 'destroy']);
            });
            Route::middleware('permission:roles.manage')->group(function () {
                Route::get('roles', [UserAdminController::class, 'roles']);
                Route::get('roles/{id}', [UserAdminController::class, 'showRole'])->whereNumber('id');
                Route::post('roles', [UserAdminController::class, 'createRole']);
                Route::patch('roles/{id}', [UserAdminController::class, 'updateRole']);
                Route::delete('roles/{id}', [UserAdminController::class, 'deleteRole']);
            });
            Route::middleware('permission:tickets.manage')->group(function () {
                Route::get('reports/options', [TicketController::class, 'options']);
                Route::get('reports', [TicketController::class, 'adminIndex'])->defaults('reports_only', true);
                Route::get('reports/{reference}', [TicketController::class, 'adminShow'])->defaults('reports_only', true);
                Route::patch('reports/{reference}', [TicketController::class, 'update'])->defaults('reports_only', true);
                Route::post('reports/{reference}/messages', [TicketController::class, 'adminReply'])->defaults('reports_only', true);
                Route::get('tickets', [TicketController::class, 'adminIndex']);
                Route::get('tickets/{reference}', [TicketController::class, 'adminShow']);
                Route::patch('tickets/{reference}', [TicketController::class, 'update']);
                Route::post('tickets/{reference}/messages', [TicketController::class, 'adminReply']);
            });
            Route::middleware('permission:forms.manage')->group(function () {
                Route::get('submissions', [FormController::class, 'index']);
                Route::get('submissions/{reference}', [FormController::class, 'show']);
                Route::patch('submissions/{reference}', [FormController::class, 'review']);
            });
            Route::get('moderation/{kind}', [CommunityController::class, 'moderateList']);
            Route::get('moderation/{kind}/{id}', [CommunityController::class, 'moderationShow'])->whereNumber('id');
            Route::patch('moderation/{kind}/{id}', [CommunityController::class, 'moderate']);
            Route::middleware('permission:newsletter.manage')->group(function () {
                Route::get('subscribers', [CommunityController::class, 'subscribers']);
                Route::get('subscribers/{id}', [CommunityController::class, 'showSubscriber'])->whereNumber('id');
                Route::patch('subscribers/{id}', [CommunityController::class, 'updateSubscriber']);
                Route::delete('subscribers/{id}', [CommunityController::class, 'deleteSubscriber']);
            });
            $names = implode('|', array_keys(ResourceRegistry::all()));
            Route::get('{resource}', [ResourceController::class, 'index'])->where('resource', $names);
            Route::post('{resource}', [ResourceController::class, 'store'])->where('resource', $names);
            Route::post('{resource}/reorder', [ResourceController::class, 'reorder'])->where('resource', $names);
            Route::post('{resource}/{id}/restore', [ResourceController::class, 'restore'])->where('resource', $names);
            Route::get('{resource}/{id}', [ResourceController::class, 'show'])->where('resource', $names);
            Route::match(['put', 'patch'],'{resource}/{id}',[ResourceController::class, 'update'])->where('resource',$names);
            Route::delete('{resource}/{id}',[ResourceController::class, 'destroy'])->where('resource',$names);
        });
    });
});
Route::fallback(fn () => Api::error('مسیر API پیدا نشد.',404));
