<?php

namespace Masgeek\N8nMailer\Tests;

use Masgeek\N8nMailer\Laravel\N8nMailerServiceProvider;
use Orchestra\Testbench\TestCase as TestbenchTestCase;

class N8nTestCase extends TestbenchTestCase
{
    protected function getPackageProviders($app): array
    {
        return [N8nMailerServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('services.n8n-mailer.url', 'https://n8n.example.com/webhook/test');
        $app['config']->set('services.n8n-mailer.timeout', 15);
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('mail.default', 'n8n-mailer');
        $app['config']->set('mail.mailers.n8n-mailer', [
            'transport' => 'n8n-mailer',
        ]);
    }
}
