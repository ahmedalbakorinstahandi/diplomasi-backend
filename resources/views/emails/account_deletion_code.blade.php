{{ __('auth.account_deletion_code_message', [
    'first_name' => $userName,
    'code' => $code,
    'minutes' => $minutes,
], app()->getLocale()) }}
