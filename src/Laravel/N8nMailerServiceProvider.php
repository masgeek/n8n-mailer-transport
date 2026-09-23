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
        Mail::extend('n8n-mailer', function (array $config = []) {
            $url = $config['url'] ?? config('services.n8n-mailer.url');
            if (empty($url)) {
                throw new \InvalidArgumentException(
                    'n8n webhook URL is required. Set "url" in your mail mailer config or services.n8n-mailer.url.'
                );
            }

            $timeout = $config['timeout'] ?? config('services.n8n-mailer.timeout', 30);

            $client = HttpClient::create([
                'timeout' => $timeout,
            ]);

            $auth = $config['auth'] ?? config('services.n8n-mailer.auth', ['type' => 'none']);

            $options = [
                'max_retries' => $config['max_retries'] ?? config('services.n8n-mailer.max_retries', 0),
                'retry_delay' => $config['retry_delay'] ?? config('services.n8n-mailer.retry_delay', 1000),
                'middleware' => $config['middleware'] ?? [],
            ];

            if (isset($config['payload_mapper'])) {
                $options['payload_mapper'] = $config['payload_mapper'];
            }

            if (isset($config['response_handler'])) {
                $options['response_handler'] = $config['response_handler'];
            }

            return new N8nTransport($url, $client, $auth, $options);
        });
    }
}
