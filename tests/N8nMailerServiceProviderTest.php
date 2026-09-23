<?php

use Illuminate\Support\Facades\Mail;

uses(\Masgeek\N8nMailer\Tests\N8nTestCase::class);

it('loads service provider', function () {
    $mailer = Mail::mailer('n8n-mailer');

    expect($mailer)->not->toBeNull();
});

it('registers n8n-mailer mail driver', function () {
    $mailer = Mail::mailer('n8n-mailer');

    expect($mailer)->toBeInstanceOf(\Illuminate\Mail\Mailer::class);
});

it('reads config from services.php', function () {
    $config = $this->app['config']->get('services.n8n-mailer');

    expect($config['url'])->toBe('https://n8n.example.com/webhook/test')
        ->and($config['timeout'])->toBe(15);
});

it('throws exception when url is missing', function () {
    $this->app['config']->set('services.n8n-mailer.url', null);
    $this->app['config']->set('mail.mailers.n8n-mailer', [
        'transport' => 'n8n-mailer',
    ]);

    Mail::mailer('n8n-mailer');
})->throws(\InvalidArgumentException::class, 'n8n webhook URL is required');

it('accepts auth config', function () {
    $this->app['config']->set('services.n8n-mailer.auth', [
        'type' => 'basic',
        'username' => 'user',
        'password' => 'pass',
    ]);

    $mailer = Mail::mailer('n8n-mailer');

    expect($mailer)->toBeInstanceOf(\Illuminate\Mail\Mailer::class);
});

it('reads retry config from services.php', function () {
    $this->app['config']->set('services.n8n-mailer.max_retries', 3);
    $this->app['config']->set('services.n8n-mailer.retry_delay', 500);

    $config = $this->app['config']->get('services.n8n-mailer');

    expect($config['max_retries'])->toBe(3)
        ->and($config['retry_delay'])->toBe(500);
});
