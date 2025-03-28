<?php

namespace App\Mail;

use App\Models\Pirep;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminPirepSubmitted extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        private readonly Pirep $pirep,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $address = new Address(
            config('mail.from.address', 'no-reply@phpvms.net'),
            config('mail.from.name')
        );

        return new Envelope(
            from: $address,
            subject: 'New PIREP Submitted',
            tags: ['pirep'],
            metadata: [
                'pirep_id' => $this->pirep->id,
            ]
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'notifications.mail.admin.pirep.submitted',
            with: [
                'pirep' => $this->pirep,
            ]
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
