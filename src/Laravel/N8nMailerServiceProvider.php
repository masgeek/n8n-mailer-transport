<?php

namespace Masgeek\N8nMailer\Laravel;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use Masgeek\N8nMailer\N8nTransport;
use Symfony\Component\HttpClient\HttpClient;

class N8nMailerServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Mail::extend('n8n', function (array $config = []) {
            $url = $config['url'] ?? config('services.n8n.url');
            if (empty($url)) {
                throw new \InvalidArgumentException(
                    'n8n webhook URL is required. Set "url" in your mail mailer config or services.n8n.url.'
                );
            }

            $timeout = $config['timeout'] ?? config('services.n8n.timeout', 30);

            $client = HttpClient::create([
                'timeout' => $timeout,
            ]);

            $auth = $config['auth'] ?? config('services.n8n.auth', ['type' => 'none']);

            return new N8nTransport($url, $client, $auth);
        });
    }
}
