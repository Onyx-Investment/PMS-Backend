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
            subject: 'Welcome to Onyxial - Your OTP for First Login',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.welcome-otp',
            with: [
                'user' => $this->user,
                'otp' => $this->otp,
            ],
        );
    }
}