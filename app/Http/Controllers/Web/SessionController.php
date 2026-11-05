<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Api\AuthController;
use App\Services\OtpService;
use App\Support\Api;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

/** The website uses an HttpOnly Laravel session; API bearer tokens never reach its JS. */
final class SessionController
{
    public function login(Request $request, AuthController $auth)
    {
        $request->validate(['audience'=>'required|in:staff,citizen']);
        return $this->establish($request, $auth->login($request));
    }

    public function verify(Request $request, AuthController $auth, OtpService $otp)
    {
        return $this->establish($request, $auth->verifyOtp($request, $otp));
    }

    private function establish(Request $request, $response)
    {
        $result = $response->getData(true);
        $access = PersonalAccessToken::findToken($result['data']['token']);
        abort_unless($access && $access->tokenable->is_active, 401);
        Auth::guard('web')->login($access->tokenable);
        $request->session()->regenerate();
        $access->delete();
        unset($result['data']['token'], $result['data']['token_type'], $result['data']['expires_in']);
        return response()->json($result);
    }

    public function logout(Request $request)
    {
        if ($request->boolean('all') && $request->user()) $request->user()->tokens()->delete();
        Auth::guard('web')->logout();
        Auth::guard('sanctum')->forgetUser();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return Api::ok(null);
    }
}
