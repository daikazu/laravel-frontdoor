<?php

declare(strict_types=1);

namespace Daikazu\LaravelFrontdoor\Mail;

use Daikazu\LaravelFrontdoor\Contracts\AccountData;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public ?AccountData $account = null;

    public function setAccount(AccountData $account): static
    {
        $this->account = $account;

        return $this;
    }

    public function envelope(): Envelope
    {
        /** @var string $appName */
        $appName = config('app.name');

        /** @var string $subject */
        $subject = config('frontdoor.mail.welcome_subject', 'Welcome to '.$appName);

        return new Envelope(
            from: $this->fromAddress(),
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'frontdoor::mail.welcome',
            with: [
                'account' => $this->account,
                'appName' => config('app.name'),
            ],
        );
    }

    protected function fromAddress(): Address
    {
        /** @var string $address */
        $address = config('frontdoor.mail.from.address') ?? config('mail.from.address', 'hello@example.com');

        /** @var string|null $name */
        $name = config('frontdoor.mail.from.name') ?? config('mail.from.name', 'Example');

        return new Address($address, $name);
    }
}
