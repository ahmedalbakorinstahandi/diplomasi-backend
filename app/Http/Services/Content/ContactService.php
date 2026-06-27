<?php

namespace App\Http\Services\Content;

use App\Http\Permissions\Content\ContactMessagePermission;
use App\Mail\ContactMessageMail;
use App\Models\Content\ContactMessage;
use App\Models\System\Setting;
use App\Services\FilterService;
use App\Services\MessageService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ContactService
{
    public function store(array $data, ?string $ipAddress = null): ContactMessage
    {
        $message = ContactMessage::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'subject' => $data['subject'],
            'message' => $data['message'],
            'ip_address' => $ipAddress,
            'status' => 'new',
        ]);

        $this->sendNotificationEmail($message);

        return $message;
    }

    public function index($filters = [])
    {
        $query = ContactMessage::query();

        $filters['per_page'] = $filters['per_page'] ?? 20;
        $filters['sort_field'] = $filters['sort_field'] ?? 'created_at';
        $filters['sort_order'] = $filters['sort_order'] ?? 'desc';

        $searchFields = ['name', 'email', 'subject', 'message'];
        $numericFields = [];
        $dateFields = ['created_at'];
        $exactMatchFields = ['status'];
        $inFields = [];

        $query = ContactMessagePermission::filterIndex($query);

        return FilterService::applyFilters(
            $query,
            $filters,
            $searchFields,
            $numericFields,
            $dateFields,
            $exactMatchFields,
            $inFields
        );
    }

    public function show(int $id): ContactMessage
    {
        $message = ContactMessage::where('id', $id)->first();
        if (!$message) {
            MessageService::abort(404, 'messages.contact_message.not_found');
        }

        return $message;
    }

    public function markAsRead(ContactMessage $message): ContactMessage
    {
        if ($message->status === 'new') {
            $message->update(['status' => 'read']);
        }

        return $this->show($message->id);
    }

    private function sendNotificationEmail(ContactMessage $message): void
    {
        $supportEmail = Setting::where('key_name', 'support.email')->value('value');

        if (!$supportEmail) {
            Log::warning('Contact message saved but support.email setting is missing.');

            return;
        }

        try {
            Mail::to($supportEmail)->send(new ContactMessageMail($message));
        } catch (\Throwable $e) {
            Log::error('Failed to send contact notification email.', [
                'contact_message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
