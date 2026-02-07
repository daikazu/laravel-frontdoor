<?php

declare(strict_types=1);

namespace Daikazu\LaravelFrontdoor\Mail;

use Daikazu\LaravelFrontdoor\Contracts\AccountData;
use Daikazu\LaravelFrontdoor\Contracts\OtpMailable;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable implements OtpMailable
{
    use Queueable;
    use SerializesModels;

    public string $code = '';

    public ?AccountData $account = null;

    public int $expiresInMinutes = 10;

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function setAccount(AccountData $account): static
    {
        $this->account = $account;

        return $this;
    }

    public function setExpiresInMinutes(int $minutes): static
    {
        $this->expiresInMinutes = $minutes;

        return $this;
    }

    public function envelope(): Envelope
    {
        /** @var string $subject */
        $subject = config('frontdoor.mail.subject', 'Your login code');

        return new Envelope(
            from: $this->fromAddress(),
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'frontdoor::mail.otp',
            with: [
                'code' => $this->code,
                'account' => $this->account,
                'expiresInMinutes' => $this->expiresInMinutes,
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
