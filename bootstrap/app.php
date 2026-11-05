<?php

use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\NormalizeIdentity;
use App\Http\Middleware\RequirePermission;
use App\Http\Middleware\RequireStaff;
use App\Support\Api;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up'
    )
    ->withCommands([
        \App\Console\Commands\PrepareStaffPortals::class,
        \App\Console\Commands\CreateStaffManager::class,
        \App\Console\Commands\PrepareLocalAdmin::class,
        \App\Console\Commands\CreatePublicRelations::class,
        \App\Console\Commands\CreateAdministrator::class,
        \App\Console\Commands\PrepareEventAlerts::class,
        \App\Console\Commands\ImportMemberDirectory::class,
        \App\Console\Commands\ImportServiceAgreements::class,
        \App\Console\Commands\PrepareCustomPages::class,
        \App\Console\Commands\ImportUnitDuties::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'active' => EnsureActiveUser::class,
            'permission' => RequirePermission::class,
            'staff' => RequireStaff::class,
            'portal' => \App\Http\Middleware\EnforcePortalScope::class,
        ]);

        $middleware->api(append: [
            NormalizeIdentity::class,
        ]);

        $middleware->trustProxies(at: [
            '127.0.0.1',
            '::1',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) =>
                $request->is('api/*', 'web-api/*', 'session/*')
                || $request->expectsJson()
        );

        $exceptions->render(function (
            ValidationException $e,
            Request $request
        ) {
            if (
                ! $request->is('api/*', 'web-api/*', 'session/*')
                && ! $request->expectsJson()
            ) {
                return null;
            }

            return Api::error(
                'اطلاعات واردشده معتبر نیست.',
                422,
                $e->errors()
            );
        });

        $exceptions->render(function (
            AuthenticationException $e,
            Request $request
        ) {
            if (
                ! $request->is('api/*', 'web-api/*', 'session/*')
                && ! $request->expectsJson()
            ) {
                return null;
            }

            return Api::error(
                'برای ادامه وارد حساب کاربری شوید.',
                401
            );
        });

        $exceptions->render(function (
            Throwable $e,
            Request $request
        ) {
            if (
                ! $request->is('api/*', 'web-api/*', 'session/*')
                && ! $request->expectsJson()
            ) {
                return null;
            }

            if ($e instanceof HttpExceptionInterface) {
                if ($e->getStatusCode() === 429) {
                    return Api::error(
                        'تعداد درخواست‌های شما بیش از حد مجاز است. لطفاً یک دقیقهٔ دیگر دوباره تلاش کنید.',
                        429,
                        [],
                        $e->getHeaders()
                    );
                }

                return Api::error(
                    $e->getMessage() ?: match ($e->getStatusCode()) {
                        404 => 'موردی پیدا نشد.',
                        403 => 'دسترسی مجاز نیست.',
                        default => 'درخواست قابل انجام نیست.',
                    },
                    $e->getStatusCode(),
                    [],
                    $e->getHeaders()
                );
            }

            if (
                $e instanceof QueryException
                && in_array(
                    (string) $e->getCode(),
                    ['23000', '23505', '23503']
                )
            ) {
                return Api::error(
                    'اطلاعات تکراری است یا رکورد وابسته وجود دارد.',
                    409
                );
            }

            if ($e instanceof ModelNotFoundException) {
                return Api::error(
                    'موردی پیدا نشد.',
                    404
                );
            }

            if (! config('app.debug')) {
                return Api::error(
                    'خطای داخلی سرور.',
                    500
                );
            }

            return null;
        });
    })
    ->create();