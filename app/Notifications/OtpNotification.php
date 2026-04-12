<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
// use Twilio client for SMS if needed
// Twilio client is optional; ensure SDK may not be installed in all environments

class OtpNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $otpCode;
    protected $channel; // 'email' or 'sms'

    public function __construct(string $otpCode, string $channel = 'email')
    {
        $this->otpCode = $otpCode;
        $this->channel = $channel;
    }

    public function via($notifiable)
    {
        if ($this->channel === 'sms' && !empty($notifiable->phone)) {
            return [];
        }

        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Your verification code')
            ->line('Your OTP code is: ' . $this->otpCode)
            ->line('It will expire in 10 minutes.')
            ->line('If you did not request this, ignore this message.');
    }

    // helper that can be called from controller to send SMS via Twilio
    public function toSms($notifiable)
    {
        if (empty($notifiable->phone)) {
            return;
        }

        $sid = config('services.twilio.sid');
        $token = config('services.twilio.token');
        $from = config('services.twilio.from');

        if (!$sid || !$token || !$from) {
            return;
        }

        // Only use Twilio if the client class is available (package installed)
        if (!class_exists('\Twilio\\Rest\\Client')) {
            // SDK not installed; we can't send SMS here
            return;
        }

        $client = new \Twilio\Rest\Client($sid, $token);
        $client->messages->create($notifiable->phone, [
            'from' => $from,
            'body' => "Your verification code: {$this->otpCode} (expires in 10 minutes)"
        ]);
    }
}
