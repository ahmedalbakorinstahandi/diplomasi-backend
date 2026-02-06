<?php

namespace App\Http\Services\Auth;

use App\Models\Users\Role;
use App\Models\Users\User;
use App\Services\MessageService;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class AuthService
{
    public function login($data)
    {
        $user = User::where('email', $data['email'])->first();


        if (!$user) {
            MessageService::abort(
                401,
                'auth.login_invalid',
            );
        }

        if (!Hash::check($data['password'], $user->password)) {
            MessageService::abort(
                401,
                'auth.login_invalid',
            );
        }

        if ($user->status === 'banned') {
            MessageService::abort(
                401,
                'auth.account_banned',
            );
        }

        if ($user->email_verified == false) {
            MessageService::abort(
                401,
                'auth.account_not_verified',
            );
        }



        $token = $user->createToken($user->first_name . 'auth_token')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }


    public function register($data)
    {

        $user = User::where('email', $data['email'])->first();

        if ($user) {
            MessageService::abort(
                401,
                'auth.email_already_exists',
            );
        }

        $otp = random_int(10000, 99999);
        $minutes = 5;
        $otpExpireAt = now()->addMinutes($minutes);

        $user = User::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'wallet_balance' => 0.0,
            'avatar' => $data['avatar'] ?? null,
            'email' => $data['email'],
            'phone' => $data['phone'] ?? '',
            'phone_verified' => false,
            'email_verified' => false,
            'password' => Hash::make($data['password']),
            'status' => 'active',
            'otp' => $otp,
            'otp_expire_at' => $otpExpireAt,
        ]);

        // create user role
        $user->userRoles()->create([
            'role_id' => Role::where('name', 'user')->first()->id,
            'created_at' => now(),
        ]);

        // Send OTP to phone number
        $message = __('messages.verification.code_message_rigster', [
            'first_name' => $user->first_name,
            'otp' => $otp,
            'minutes' => $minutes,
        ], $user->language);


        // TODO: Send OTP to email
        // EmailService::send($data['email'], $message);


        return [
            'user' => $user,
            'minutes' => $minutes,
            'otp_expire_at' => $otpExpireAt,
        ];
    }

    public function verifyOtp($data)
    {

        $user = User::where('email', $data['email'])
            ->first();

        if (!$user) {
            MessageService::abort(
                401,
                'auth.email_not_found',
            );
        }


        // TODO: Remove this after testing
        if ($data['otp'] == 55555) {
        } else

        if ($user->otp !== $data['otp'] || $user->otp_expire_at < now()) {
            MessageService::abort(
                401,
                'auth.otp_invalid_or_expired',
            );
        }

        // if ($user->otp_expire_at < now()) {
        //     MessageService::abort(
        //         401,
        //         'auth.otp_expired',
        //     );
        // }

        $user->update([
            'email_verified' => true,
            'otp' => null,
            'otp_expire_at' => null,
        ]);

        return [
            'user' => $user,
            'token' => $user->createToken($user->first_name . 'auth_token')->plainTextToken,
        ];
    }

    public function forgotPassword($data)
    {
        $user = User::where('email', $data['email'])
            ->first();

        if (!$user) {
            MessageService::abort(
                401,
                'auth.email_not_found',
            );
        }

        $code = random_int(10000, 99999);
        $minutes = 5;
        $otpExpireAt = now()->addMinutes($minutes);
        $user->update([
            'otp' => $code,
            'otp_expire_at' => $otpExpireAt,
        ]);

        // // Send OTP to email
        // $message = __('messages.verification.code_message_forgot_password', [
        //     'first_name' => $user->first_name,
        //     'otp' => $code,
        //     'minutes' => $minutes,
        // ], $user->language);

        // TODO: Send OTP to email
        // WhatsappMessageService::send($phoneNumber, $message);

        return [
            'user' => $user,
            'minutes' => 10,
            'otp_expire_at' =>  $otpExpireAt,
        ];
    }

    public function resetPassword($data)
    {
        $user = User::auth();

        $user->update([
            'password' => Hash::make($data['password']),
        ]);

        $user->tokens()->delete();

        $newToken = $user->createToken($user->first_name)->plainTextToken;

        return [
            'user' => $user,
            'token' => $newToken,
        ];
    }

    public function logout($token)
    {
        $personalAccessToken = PersonalAccessToken::findToken($token);

        return $personalAccessToken->delete();
    }

    /**
     * Request account deletion - sends deletion code to user's email
     */
    public function requestAccountDeletion()
    {
        $user = User::auth();

        if (!$user) {
            MessageService::abort(401, 'messages.unauthorized');
        }

        $code = str_pad(random_int(0, 99999), 5, '0', STR_PAD_LEFT);
        $minutes = 10;
        $codeExpireAt = now()->addMinutes($minutes);

        // Use existing otp and otp_expire_at fields
        $user->update([
            'otp' => $code,
            'otp_expire_at' => $codeExpireAt,
        ]);

        // TODO: Send email with deletion code
        // Mail::to($user->email)->send(
        //     new \App\Mail\AccountDeletionCodeMail($code, $user->first_name, $minutes)
        // );

        return [
            'message' => 'auth.account_deletion_code_sent',
            'minutes' => $minutes,
            'code_expire_at' => $codeExpireAt,
        ];
    }

    /**
     * Confirm account deletion - verifies code and deletes user account
     */
    public function confirmAccountDeletion($data)
    {
        $user = User::auth();

        if (!$user) {
            MessageService::abort(401, 'messages.unauthorized');
        }

        $code = $data['code'];

        // TODO: Remove this after testing (if needed)
        // if ($code == '55555') {
        //     // Allow deletion for testing
        // } else

        // Verify deletion code using existing otp and otp_expire_at fields
        if (
            $user->otp !== $code ||
            !$user->otp_expire_at ||
            $user->otp_expire_at < now()
        ) {
            MessageService::abort(401, 'auth.account_deletion_code_invalid_or_expired');
        }

        // Delete all user data
        $this->deleteUserAccount($user);

        return [
            'message' => 'auth.account_deleted_successfully',
        ];
    }

    /**
     * Delete user account and all related data
     */
    private function deleteUserAccount($user)
    {
        // Delete user roles
        $user->userRoles()->delete();

        // Delete all tokens
        $user->tokens()->delete();

        // Delete user courses
        $user->userCourses()->delete();

        // Delete user lesson progress
        $user->userLessonProgress()->delete();

        // Delete user level progress
        $user->userLevelProgress()->delete();

        // Delete user lesson attempts and related data
        $user->userLessonAttempts()->each(function ($attempt) {
            // Delete answers
            $attempt->userLessonQuestionAnswers()->delete();
        });
        $user->userLessonAttempts()->delete();

        // Delete user scenario attempts and related data
        $user->userScenarioAttempts()->each(function ($attempt) {
            // Delete answers
            $attempt->userScenarioQuestionAnswers()->delete();
        });
        $user->userScenarioAttempts()->delete();

        // Delete subscriptions
        $user->subscriptions()->delete();

        // Delete notifications
        $user->notifications()->delete();

        // Delete certificates
        $user->certificates()->delete();

        // Delete activity logs
        $user->activityLogs()->delete();

        // Delete articles authored by user (if any)
        $user->articles()->delete();

        // Finally, delete the user (soft delete)
        $user->delete();
    }
}
