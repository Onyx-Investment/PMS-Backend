<?php
// app/Mail/OTPMail.php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OTPMail extends Mailable
{
    use Queueable, SerializesModels;

    public $otp;
    public $user;
    public $isWelcome;

    /**
     * Create a new message instance.
     */
    public function __construct($user, $otp, $isWelcome = false)
    {
        $this->user = $user;
        $this->otp = $otp;
        $this->isWelcome = $isWelcome;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = $this->isWelcome ? 'Welcome to Onyx PMS - Your OTP for First Login' : 'Your OTP for Login';
        
        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.otp',
            with: [
                'user' => $this->user,
                'otp' => $this->otp,
                'isWelcome' => $this->isWelcome,
                'loginUrl' => env('FRONTEND_URL') . '/auth/login', // Assuming FRONTEND_URL is set in your .env file
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}