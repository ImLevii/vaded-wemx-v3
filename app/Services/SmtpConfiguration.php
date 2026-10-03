<?php

namespace App\Services;

use App\Helpers\EnvironmentWriter;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;

class SmtpConfiguration
{
    /** @param array{host: string, port: int, encryption: ?string, username: ?string, password: ?string, mail_from_address: string, mail_from_name: string} $values */
    public function apply(array $values): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.url' => null,
            'mail.mailers.smtp.host' => $values['host'],
            'mail.mailers.smtp.port' => $values['port'],
            'mail.mailers.smtp.encryption' => $values['encryption'],
            'mail.mailers.smtp.scheme' => $values['encryption'] === 'ssl' ? 'smtps' : 'smtp',
            'mail.mailers.smtp.auto_tls' => $values['encryption'] !== null,
            'mail.mailers.smtp.require_tls' => $values['encryption'] === 'tls',
            'mail.mailers.smtp.username' => $values['username'],
            'mail.mailers.smtp.password' => $values['password'],
            'mail.from.address' => $values['mail_from_address'],
            'mail.from.name' => $values['mail_from_name'],
        ]);
        Mail::purge('smtp');
    }

    /** @param array{host: string, port: int, encryption: ?string, username: ?string, password: ?string, mail_from_address: string, mail_from_name: string} $values */
    public function save(array $values): void
    {
        EnvironmentWriter::write([
            'MAIL_MAILER' => 'smtp',
            'MAIL_URL' => null,
            'MAIL_HOST' => $values['host'],
            'MAIL_PORT' => (string) $values['port'],
            'MAIL_USERNAME' => $values['username'],
            'MAIL_PASSWORD' => $values['password'],
            'MAIL_ENCRYPTION' => $values['encryption'],
            'MAIL_SCHEME' => $values['encryption'] === 'ssl' ? 'smtps' : 'smtp',
            'MAIL_FROM_ADDRESS' => $values['mail_from_address'],
            'MAIL_FROM_NAME' => $values['mail_from_name'],
        ]);
        Artisan::call('config:clear', ['--no-interaction' => true]);
        Artisan::call('queue:restart', ['--no-interaction' => true]);
        $this->apply($values);
    }
}
