<?php

namespace Meva\Entities\Marketing\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

/**
 * One campaign, addressed to one person.
 *
 * The HTML arrives already rendered, so nothing about the message changes
 * between the preview and the inbox. The headers matter as much as the body:
 * since 2024 Gmail and Yahoo hold bulk senders to one-click unsubscribe, and a
 * message without List-Unsubscribe is far likelier to land in spam.
 */
class CampaignMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $subjectLine,
        // Not $html: Mailable declares that property itself, and redeclaring
        // it is a fatal error rather than an override.
        public string $htmlBody,
        public string $plain,
        public string $unsubscribeUrl,
        public string $campaignKey,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        // The plain-text half goes through a one-line view because a Content
        // takes a view name for text, not a string.
        return new Content(
            htmlString: $this->htmlBody,
            text: 'emails.plain',
            with: ['plain' => $this->plain],
        );
    }

    public function headers(): Headers
    {
        return new Headers(text: [
            // One-click unsubscribe: the mail client shows its own button, and
            // people use that instead of pressing "spam".
            'List-Unsubscribe' => '<'.$this->unsubscribeUrl.'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
            // Lets the mailbox group a campaign's replies and lets us read
            // bounces back to the campaign they came from.
            'X-Entity-Ref-ID' => $this->campaignKey,
            'X-Campaign-Id' => $this->campaignKey,
        ]);
    }
}
