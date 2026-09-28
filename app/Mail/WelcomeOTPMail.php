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

    // WelcomeOTPMail.php
public function __construct($user, $otp, public string $mode = 'welcome')
{
    $this->user = $user;
    $this->otp = $otp;
}

public function envelope(): Envelope
{
    return new Envelope(
        subject: $this->mode === 'reset'
            ? 'Password Reset Code — Onyx PMS'
            : 'Welcome to Onyx PMS — Your Setup Code',
    );
}

public function content(): Content
{
    return new Content(
        view: 'emails.welcome-otp',
        with: [
            'user'     => $this->user,
            'otp'      => $this->otp,
            'mode'     => $this->mode,
            'setupUrl' => config('app.frontend_url')
                        . '/auth/verify-otp?email=' . urlencode($this->user->email),
        ],
    );
}
}