<?php

namespace Tests\Feature\Mail;

use App\Mail\Transport\SendGridTransport;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class SendGridTransportTest extends TestCase
{
    public function test_posts_message_to_sendgrid_api(): void
    {
        Http::fake();

        $transport = new SendGridTransport('test_api_key');

        $email = (new Email)
            ->from(new Address('panneoartisan@gmail.com', 'Pannéo'))
            ->to(new Address('client@example.com'))
            ->subject('Pannéo — Confirmez votre adresse e-mail')
            ->text('Pannéo : votre code de vérification est 123456. Valable 5 minutes.');

        $transport->send($email);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://api.sendgrid.com/v3/mail/send'
                && $request->hasHeader('Authorization', 'Bearer test_api_key')
                && $request->hasHeader('Content-Type', 'application/json')
                && $request['personalizations'][0]['to'][0]['email'] === 'client@example.com'
                && $request['from']['email'] === 'panneoartisan@gmail.com'
                && $request['from']['name'] === 'Pannéo'
                && $request['subject'] === 'Pannéo — Confirmez votre adresse e-mail'
                && $request['content'][0]['type'] === 'text/plain'
                && str_contains($request['content'][0]['value'], '123456');
        });
    }

    public function test_throws_when_sendgrid_rejects_the_request(): void
    {
        Http::fake([
            'https://api.sendgrid.com/v3/mail/send' => Http::response('Unauthorized', 401),
        ]);

        $transport = new SendGridTransport('bad_key');

        $email = (new Email)
            ->from(new Address('panneoartisan@gmail.com', 'Pannéo'))
            ->to(new Address('client@example.com'))
            ->subject('Test')
            ->text('Bonjour');

        $this->expectException(RuntimeException::class);

        $transport->send($email);
    }

    public function test_can_send_through_the_sendgrid_mailer_with_mail_raw(): void
    {
        Http::fake();

        config()->set('mail.default', 'sendgrid');
        config()->set('mail.mailers.sendgrid.key', 'test_api_key');
        config()->set('mail.from.address', 'panneoartisan@gmail.com');
        config()->set('mail.from.name', 'Pannéo');

        Mail::raw('Pannéo : votre code est 654321.', function ($message) {
            $message->to('client@example.com')
                ->subject('Pannéo — Réinitialisation de votre mot de passe');
        });

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://api.sendgrid.com/v3/mail/send'
                && $request->hasHeader('Authorization', 'Bearer test_api_key')
                && $request['personalizations'][0]['to'][0]['email'] === 'client@example.com'
                && $request['from']['email'] === 'panneoartisan@gmail.com'
                && $request['subject'] === 'Pannéo — Réinitialisation de votre mot de passe'
                && str_contains($request['content'][0]['value'], '654321');
        });
    }
}
