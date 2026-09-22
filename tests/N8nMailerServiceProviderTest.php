<?php

use Illuminate\Support\Facades\Mail;

uses(\Masgeek\N8nMailer\Tests\N8nTestCase::class);

it('loads service provider', function () {
    $mailer = Mail::mailer('n8n');

    expect($mailer)->not->toBeNull();
});

it('registers n8n mail driver', function () {
    $mailer = Mail::mailer('n8n');

    expect($mailer)->toBeInstanceOf(\Illuminate\Mail\Mailer::class);
});

it('reads config from services.php', function () {
    $config = $this->app['config']->get('services.n8n');

    expect($config['url'])->toBe('https://n8n.example.com/webhook/test')
        ->and($config['timeout'])->toBe(15);
});

it('throws exception when url is missing', function () {
    $this->app['config']->set('services.n8n.url', null);
    $this->app['config']->set('mail.mailers.n8n', [
        'transport' => 'n8n',
    ]);

    Mail::mailer('n8n');
})->throws(\InvalidArgumentException::class, 'n8n webhook URL is required');

it('accepts auth config', function () {
    $this->app['config']->set('services.n8n.auth', [
        'type' => 'basic',
        'username' => 'user',
        'password' => 'pass',
    ]);

    $mailer = Mail::mailer('n8n');

    expect($mailer)->toBeInstanceOf(\Illuminate\Mail\Mailer::class);
});
