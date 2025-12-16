<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\VerifyCodeRequest;
use App\Http\Resources\Users\UserResource;
use App\Http\Services\Auth\AuthService;
use App\Services\FirebaseService;
use App\Services\ResponseService;

class AuthController extends Controller
{
    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function login(LoginRequest $request)
    {
        $data = $this->authService->login($request->validated());

        // FirebaseService::subscribeToAllTopic($request, $data['user']);

        return ResponseService::response([
            'status' => 200,
            'access_token' => $data['token'],
            'message' => 'auth.login_success',
            'data' => new UserResource($data['user']),
        ]);
    }

    public function register(RegisterRequest $request)
    {
        $data = $this->authService->register($request->validated());

        return ResponseService::response([
            'status' => 200,
            'message' => 'auth.we_sent_verification_code_to_your_email',
            'data' => new UserResource($data['user']),
            'info' => [
                'code_duration' => $data['minutes'],
                'otp_expire_at' => $data['otp_expire_at'],
            ],
        ]);
    }


    public function forgotPassword(ForgotPasswordRequest $request)
    {
        $data = $this->authService->forgotPassword($request->validated());

        return ResponseService::response([
            'status' => 200,
            'message' => 'auth.we_sent_verification_code_to_your_email',
            'data' => new UserResource($data['user']),
            'info' => [
                'code_duration' => $data['minutes'],
                'otp_expire_at' => $data['otp_expire_at'],
            ],
        ]);
    }

    public function verifyOtp(VerifyCodeRequest $request)
    {
        $data = $this->authService->verifyOtp($request->all());

        return ResponseService::response([
            'status' => 200,
            'access_token' => $data['token'],
            'message' => 'auth.otp_verified',
            'data' => new UserResource($data['user']),
        ]);
    }



    public function resetPassword(ResetPasswordRequest $request)
    {
        $data = $this->authService->resetPassword($request->all());

        return ResponseService::response([
            'status' => 200,
            'access_token' => $data['token'],
            'message' => 'auth.password_reset_success',
            'data' => new UserResource($data['user']),
        ]);
    }

    public function logout()
    {

        $token = request()->bearerToken();

        $this->authService->logout($token);

        return ResponseService::response([
            'status' => 200,
            'message' => 'auth.user_logged_out_successfully',
        ]);
    }
}
