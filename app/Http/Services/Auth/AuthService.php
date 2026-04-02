<?php

namespace App\Http\Services\Auth;

use App\Http\Notifications\AccountNotification;
use App\Mail\AccountDeletionCodeMail;
use App\Mail\VerificationCodeMail;
use App\Models\Users\Role;
use App\Models\Users\User;
use App\Services\MessageService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\PersonalAccessToken;

class AuthService
{
    public function guestStart(): array
    {
        $userRole = Role::where('name', 'user')->first();
        if (!$userRole) {
            MessageService::abort(500, 'messages.role.not_found');
        }

        $user = User::create([
            'first_name' => 'Learner',
            'last_name' => 'Guest',
            'email' => null,
            'phone' => null,
            'phone_verified' => false,
            'email_verified' => false,
            'password' => null,
            'status' => 'active',
            'is_guest' => true,
            'guest_last_active_at' => now(),
            'language' => 'en',
        ]);

        $user->userRoles()->create([
            'role_id' => $userRole->id,
            'created_at' => now(),
        ]);

        return [
            'user' => $user,
            'token' => $user->createToken('guest_auth_token')->plainTextToken,
        ];
    }

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
            'phone' => $data['phone'] ?? null,
            'phone_verified' => false,
            'email_verified' => false,
            'password' => Hash::make($data['password']),
            'status' => 'active',
            'is_guest' => false,
            'otp' => $otp,
            'otp_expire_at' => $otpExpireAt,
        ]);

        // create user role
        $user->userRoles()->create([
            'role_id' => Role::where('name', 'user')->first()->id,
            'created_at' => now(),
        ]);

        $this->sendVerificationEmail(
            $user->email,
            $user->first_name,
            $otp,
            $minutes,
            'register'
        );

        return [
            'user' => $user,
            'minutes' => $minutes,
            'otp_expire_at' => $otpExpireAt,
        ];
    }

    public function registerFromGuest(array $data): array
    {
        $authUser = User::auth();
        if (!$authUser) {
            MessageService::abort(401, 'messages.unauthorized');
        }

        return DB::transaction(function () use ($authUser, $data) {
            $user = User::where('id', $authUser->id)->lockForUpdate()->first();
            if (!$user) {
                MessageService::abort(404, 'messages.user.not_found');
            }

            if (!$user->is_guest) {
                MessageService::abort(400, 'auth.not_guest_account');
            }

            if (!empty($user->email) || !empty($user->phone)) {
                MessageService::abort(400, 'auth.guest_conversion_already_started');
            }

            $emailExists = User::query()
                ->where('id', '!=', $user->id)
                ->where('email', $data['email'])
                ->whereNull('deleted_at')
                ->exists();
            if ($emailExists) {
                MessageService::abort(401, 'auth.email_already_exists');
            }

            if (!empty($data['phone'])) {
                $phoneExists = User::query()
                    ->where('id', '!=', $user->id)
                    ->where('phone', $data['phone'])
                    ->whereNull('deleted_at')
                    ->exists();
                if ($phoneExists) {
                    MessageService::abort(401, 'auth.phone_already_exists');
                }
            }

            $otp = random_int(10000, 99999);
            $minutes = 5;
            $otpExpireAt = now()->addMinutes($minutes);

            $user->update([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'avatar' => $data['avatar'] ?? null,
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make($data['password']),
                'phone_verified' => false,
                'email_verified' => false,
                'is_guest' => false,
                'otp' => (string) $otp,
                'otp_expire_at' => $otpExpireAt,
            ]);

            $this->sendVerificationEmail(
                $user->email,
                $user->first_name,
                (string) $otp,
                $minutes,
                'register'
            );

            return [
                'user' => $user->fresh(),
                'minutes' => $minutes,
                'otp_expire_at' => $otpExpireAt,
            ];
        });
    }

    public function verifyOtp($data)
    {
        $authenticatedUser = User::auth();
        if (!$authenticatedUser) {
            $bearerToken = request()->bearerToken();
            if ($bearerToken) {
                $personalToken = PersonalAccessToken::findToken($bearerToken);
                if ($personalToken && $personalToken->tokenable instanceof User) {
                    $authenticatedUser = $personalToken->tokenable;
                }
            }
        }
        $user = null;
        $rotateAllTokens = false;

        if ($authenticatedUser) {
            $user = User::where('id', $authenticatedUser->id)->lockForUpdate()->first();
            if (!$user) {
                MessageService::abort(401, 'messages.unauthorized');
            }

            if (!empty($data['email']) && !empty($user->email) && strcasecmp((string) $data['email'], (string) $user->email) !== 0) {
                MessageService::abort(401, 'auth.email_not_found');
            }

            $rotateAllTokens = true;
        } else {
            $user = User::where('email', $data['email'])->first();
        }

        if (!$user) {
            MessageService::abort(
                401,
                'auth.email_not_found',
            );
        }

        // Normalize OTP type: DB may store it as string, request may send it as int.
        $otpFromDb = isset($user->otp) ? (string) $user->otp : null;
        $otpFromRequest = isset($data['otp']) ? (string) $data['otp'] : null;

        if ($otpFromDb === null ||
            $otpFromRequest === null ||
            $otpFromDb !== $otpFromRequest ||
            $user->otp_expire_at < now()) {
            MessageService::abort(
                401,
                'auth.otp_invalid_or_expired',
            );
        }

        $user->update([
            'email_verified' => true,
            'otp' => null,
            'otp_expire_at' => null,
        ]);

        if (!$user->is_guest && !$user->guest_converted_at && $rotateAllTokens) {
            $user->update([
                'guest_converted_at' => now(),
                'registration_completed_at' => now(),
            ]);
        }

        AccountNotification::verified((int) $user->id);

        if ($rotateAllTokens) {
            $user->tokens()->delete();
        }

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

        $this->sendVerificationEmail(
            $user->email,
            $user->first_name,
            (string) $code,
            $minutes,
            'forgot_password'
        );

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

        AccountNotification::passwordChanged((int) $user->id);

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

        $user->update([
            'otp' => $code,
            'otp_expire_at' => $codeExpireAt,
        ]);

        try {
            $defaultMailer = (string) config('mail.default');
            if (in_array($defaultMailer, ['log', 'array'], true)) {
                throw new \RuntimeException("Mail is not configured for delivery. MAIL_MAILER={$defaultMailer}");
            }

            $locale = app()->getLocale();
            app()->setLocale('ar');
            $subject = __('auth.account_deletion_code_subject');
            app()->setLocale($locale);

            Mail::to($user->email)->send(
                new AccountDeletionCodeMail($subject, $code, $user->first_name, $minutes)
            );
        } catch (\Throwable $e) {
            Log::error('Account deletion code email failed', [
                'error' => $e->getMessage(),
                'mail_default' => config('mail.default'),
                'to' => $user->email,
            ]);
            MessageService::abort(500, 'auth.email_send_failed', [], [
                'reason' => $e->getMessage(),
                'mail_default' => config('mail.default'),
            ]);
        }

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
            (string) $user->otp !== (string) $code ||
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

    /**
     * Send verification/OTP email in Arabic only, using default mailer (no-reply@).
     *
     * @param  'register'|'forgot_password'  $type
     */
    protected function sendVerificationEmail(string $to, string $firstName, string $otp, int $minutes, string $type): void
    {
        try {
            $defaultMailer = (string) config('mail.default');
            if (in_array($defaultMailer, ['log', 'array'], true)) {
                throw new \RuntimeException("Mail is not configured for delivery. MAIL_MAILER={$defaultMailer}");
            }

            $locale = app()->getLocale();
            app()->setLocale('ar');

            $subject = $type === 'forgot_password'
                ? __('auth.forgot_password_code_email_subject')
                : __('auth.verification_code_email_subject');
            $title = $type === 'forgot_password'
                ? __('auth.forgot_password_code_email_title')
                : __('auth.verification_code_email_title');
            $intro = $type === 'forgot_password'
                ? __('auth.forgot_password_code_email_intro')
                : __('auth.verification_code_email_intro');

            app()->setLocale($locale);

            Mail::to($to)->send(new VerificationCodeMail(
                $subject,
                $title,
                $firstName,
                $intro,
                $otp,
                $minutes
            ));
        } catch (\Throwable $e) {
            Log::error('Verification email failed', [
                'error' => $e->getMessage(),
                'mail_default' => config('mail.default'),
                'to' => $to,
                'type' => $type,
            ]);
            MessageService::abort(500, 'auth.email_send_failed', [], [
                'reason' => $e->getMessage(),
                'mail_default' => config('mail.default'),
            ]);
        }
    }
}
