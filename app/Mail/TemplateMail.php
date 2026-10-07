<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** One mailable for every templated email. Holds only plain strings, so it queues safely. */
class TemplateMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array  $branding  name, color, logo_url, footer
     * @param  array  $files  list of ['disk' => , 'path' => , 'name' => ]
     */
    public function __construct(
        public string $mailSubject,
        public string $bodyHtml,
        public array $branding = [],
        public array $fileList = [],
        public ?string $actionUrl = null,
        public ?string $actionLabel = null,
    ) {
        $this->onQueue('emails');
    }

    public function envelope(): Envelope
    {
        $fromName = $this->branding['name'] ?? config('mail.from.name');

        return new Envelope(from: new Address(config('mail.from.address'), $fromName), subject: $this->mailSubject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.template');
    }

    public function attachments(): array
    {
        return array_map(fn ($file) => Attachment::fromStorageDisk($file['disk'], $file['path'])->as($file['name']), $this->fileList);
    }
}
