<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OvertimeRequestNotification extends Notification
{
    use Queueable;

    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [

            'title' => $this->data['title'],

            'message' => $this->data['message'],

            'user_id' => $this->data['user_id'],

            'user_name' => $this->data['user_name'],

            'minutes' => $this->data['minutes'],

            'reason' => $this->data['reason'],

            'overtime_id' => $this->data['overtime_id'],

            'type' => 'overtime_request',

            'created_at' => now()

        ];
    }
}
