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
        $app['config']->set('services.n8n.url', 'https://n8n.example.com/webhook/test');
        $app['config']->set('services.n8n.timeout', 15);
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('mail.default', 'n8n');
        $app['config']->set('mail.mailers.n8n', [
            'transport' => 'n8n',
        ]);
    }
}
