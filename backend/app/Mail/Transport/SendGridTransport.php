<?php

namespace App\Mail\Transport;

use Illuminate\Support\Facades\Http;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;

class SendGridTransport extends AbstractTransport
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $endpoint = 'https://api.sendgrid.com',
        ?EventDispatcherInterface $dispatcher = null,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($dispatcher, $logger);
    }

    protected function doSend(SentMessage $message): void
    {
        /** @var Email $email */
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        $payload = [
            'personalizations' => [
                ['to' => $this->recipients($email->getTo())],
            ],
            'from' => $this->from($email),
            'subject' => $email->getSubject(),
            'content' => $this->content($email),
        ];

        $replyTo = $email->getReplyTo();

        if ($replyTo !== []) {
            $payload['reply_to'] = ['email' => $replyTo[0]->getAddress()];
        }

        $response = Http::withToken($this->apiKey)
            ->acceptJson()
            ->asJson()
            ->post($this->endpoint.'/v3/mail/send', $payload);

        if (! $response->successful()) {
            throw new RuntimeException(sprintf(
                'SendGrid API error (%d): %s',
                $response->status(),
                $response->body()
            ));
        }
    }

    public function __toString(): string
    {
        return 'sendgrid';
    }

    private function from(Email $email): array
    {
        $from = $email->getFrom()[0] ?? null;

        if (! $from) {
            throw new RuntimeException('SendGrid requires a "from" address.');
        }

        return array_filter([
            'email' => $from->getAddress(),
            'name' => $from->getName(),
        ], fn ($value) => $value !== null && $value !== '');
    }

    private function recipients(array $addresses): array
    {
        return array_map(
            fn (Address $address) => ['email' => $address->getAddress()],
            $addresses
        );
    }

    private function content(Email $email): array
    {
        $content = [];

        if ($text = $email->getTextBody()) {
            $content[] = ['type' => 'text/plain', 'value' => $text];
        }

        if ($html = $email->getHtmlBody()) {
            $content[] = ['type' => 'text/html', 'value' => $html];
        }

        if ($content === []) {
            throw new RuntimeException('SendGrid requires a text or HTML body.');
        }

        return $content;
    }
}
