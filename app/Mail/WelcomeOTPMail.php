<?php
// app/Mail/WelcomeOTPMail.php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeOTPMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $otp;

    public function __construct($user, $otp)
    {
        $this->user = $user;
        $this->otp = $otp;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to Onyx PMS - Your OTP for First Login',
        );
    }

    // public function content(): Content
    // {
    //     return new Content(
    //         view: 'emails.welcome-otp',
    //         with: [
    //             'user' => $this->user,
    //             'otp' => $this->otp,
    //             'loginUrl' => env('FRONTEND_URL') . '/auth/login', // Assuming FRONTEND_URL is set in your .env file
    //         ],
    //     );
    // }

    public function content(): Content
{
    return new Content(
        view: 'emails.welcome-otp',
        with: [
            'user'     => $this->user,
            'otp'      => $this->otp,
            'setupUrl' => env('FRONTEND_URL')
                        . '/auth/verify-otp?email=' . urlencode($this->user->email),
        ],
    );
}
}