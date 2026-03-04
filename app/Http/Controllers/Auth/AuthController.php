<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Notifications\AccountNotification;
use App\Http\Requests\Auth\ConfirmAccountDeletionRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\RequestAccountDeletionRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\VerifyCodeRequest;
use App\Http\Resources\Users\UserResource;
use App\Http\Services\Auth\AuthService;
use App\Models\Users\User;
use App\Services\FirebaseService;
use App\Services\ResponseService;
use Illuminate\Support\Facades\DB;

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

        $this->syncDeviceTokenAndNotifyIfNew($request->input('device_token'), $request, $data['user']);

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
        $this->syncDeviceTokenAndNotifyIfNew($request->input('device_token'), $request, $data['user']);

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

    /**
     * Request account deletion - sends deletion code to user's email
     */
    public function requestAccountDeletion(RequestAccountDeletionRequest $request)
    {
        $data = $this->authService->requestAccountDeletion();

        return ResponseService::response([
            'status' => 200,
            'message' => $data['message'],
            'info' => [
                'code_duration' => $data['minutes'],
                'code_expire_at' => $data['code_expire_at'],
            ],
        ]);
    }

    /**
     * Confirm account deletion - verifies code and deletes user account
     */
    public function confirmAccountDeletion(ConfirmAccountDeletionRequest $request)
    {
        $data = $this->authService->confirmAccountDeletion($request->validated());

        return ResponseService::response([
            'status' => 200,
            'message' => $data['message'],
        ]);
    }

    private function syncDeviceTokenAndNotifyIfNew(?string $deviceToken, VerifyCodeRequest|LoginRequest $request, User $user): void
    {
        $deviceToken = trim((string) $deviceToken);
        if ($deviceToken === '') {
            return;
        }

        $isKnownToken = DB::table('personal_access_tokens')
            ->where('tokenable_id', $user->id)
            ->where('device_token', $deviceToken)
            ->exists();

        FirebaseService::subscribeToAllTopic($request, $user);

        if ($isKnownToken) {
            return;
        }

        AccountNotification::newDeviceLogin((int) $user->id);
    }
}
