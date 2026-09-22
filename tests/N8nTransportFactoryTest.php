<?php

use Masgeek\N8nMailer\N8nTransport;
use Masgeek\N8nMailer\N8nTransportFactory;
use Symfony\Contracts\HttpClient\HttpClientInterface;

beforeEach(function () {
    $this->httpClient = $this->createMock(HttpClientInterface::class);
    $this->factory = new N8nTransportFactory($this->httpClient);
});

it('creates transport with https scheme', function () {
    $transport = $this->factory->createFromString('https://n8n.example.com/webhook/test');

    expect($transport)->toBeInstanceOf(N8nTransport::class)
        ->and((string) $transport)->toBe('https://n8n.example.com/webhook/test');
});

it('creates transport with http scheme', function () {
    $transport = $this->factory->createFromString('http://localhost:8080/webhook/test');

    expect($transport)->toBeInstanceOf(N8nTransport::class)
        ->and((string) $transport)->toBe('http://localhost:8080/webhook/test');
});

it('creates transport with custom port', function () {
    $transport = $this->factory->createFromString('https://n8n.example.com:9090/webhook/test');

    expect((string) $transport)->toBe('https://n8n.example.com:9090/webhook/test');
});

it('omits default https port 443', function () {
    $transport = $this->factory->createFromString('https://n8n.example.com:443/webhook/test');

    expect((string) $transport)->toBe('https://n8n.example.com/webhook/test');
});

it('omits default http port 80', function () {
    $transport = $this->factory->createFromString('http://n8n.example.com:80/webhook/test');

    expect((string) $transport)->toBe('http://n8n.example.com/webhook/test');
});

it('creates transport with basic auth from dsn', function () {
    $transport = $this->factory->createFromString('https://user:pass@n8n.example.com/webhook/test');

    expect($transport)->toBeInstanceOf(N8nTransport::class);
});

it('creates transport with jwt auth from dsn', function () {
    $transport = $this->factory->createFromString('https://my-token@n8n.example.com/webhook/test');

    expect($transport)->toBeInstanceOf(N8nTransport::class);
});

it('throws exception for invalid dsn', function () {
    $this->factory->createFromString('not-a-url');
})->throws(\InvalidArgumentException::class, 'Invalid DSN');

it('throws exception for unsupported scheme', function () {
    $this->factory->createFromString('smtp://n8n.example.com/webhook/test');
})->throws(\InvalidArgumentException::class, 'Unsupported scheme');

it('supports https and http schemes', function () {
    $reflection = new ReflectionMethod($this->factory, 'getSupportedSchemes');
    $schemes = $reflection->invoke($this->factory);

    expect($schemes)->toContain('https')
        ->toContain('http');
});
