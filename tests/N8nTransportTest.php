<?php

use Masgeek\N8nMailer\Exception\N8nTransportException;
use Masgeek\N8nMailer\N8nTransport;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

const WEBHOOK_URL = 'https://n8n.example.com/webhook/test-123';

beforeEach(function () {
    $this->client = $this->createMock(HttpClientInterface::class);
});

it('constructs with valid url', function () {
    $transport = new N8nTransport(WEBHOOK_URL, $this->client);

    expect((string) $transport)->toBe(WEBHOOK_URL);
});

it('throws exception for invalid url', function () {
    new N8nTransport('not-a-url', $this->client);
})->throws(N8nTransportException::class);

it('throws exception for ftp scheme', function () {
    new N8nTransport('ftp://n8n.example.com/webhook', $this->client);
})->throws(N8nTransportException::class);

it('returns url in toString', function () {
    $transport = new N8nTransport(WEBHOOK_URL, $this->client);

    expect((string) $transport)->toBe(WEBHOOK_URL);
});

it('does not send auth headers when type is none', function () {
    $response = $this->createMock(ResponseInterface::class);
    $response->method('getStatusCode')->willReturn(200);

    $this->client->expects($this->once())
        ->method('request')
        ->with('POST', WEBHOOK_URL, $this->callback(fn($options) => !isset($options['headers'])))
        ->willReturn($response);

    $transport = new N8nTransport(WEBHOOK_URL, $this->client);
    $transport->send(makeEmail(), makeEnvelope());
});

it('sends basic auth authorization header', function () {
    $response = $this->createMock(ResponseInterface::class);
    $response->method('getStatusCode')->willReturn(200);

    $this->client->expects($this->once())
        ->method('request')
        ->with('POST', WEBHOOK_URL, $this->callback(function ($options) {
            return isset($options['headers']['Authorization'])
                && str_starts_with($options['headers']['Authorization'], 'Basic ');
        }))
        ->willReturn($response);

    $auth = ['type' => 'basic', 'username' => 'user', 'password' => 'pass'];
    $transport = new N8nTransport(WEBHOOK_URL, $this->client, $auth);
    $transport->send(makeEmail(), makeEnvelope());
});

it('sends bearer auth header', function () {
    $response = $this->createMock(ResponseInterface::class);
    $response->method('getStatusCode')->willReturn(200);

    $this->client->expects($this->once())
        ->method('request')
        ->with('POST', WEBHOOK_URL, $this->callback(function ($options) {
            return isset($options['headers']['Authorization'])
                && $options['headers']['Authorization'] === 'Bearer my-jwt-token';
        }))
        ->willReturn($response);

    $auth = ['type' => 'bearer', 'token' => 'my-jwt-token'];
    $transport = new N8nTransport(WEBHOOK_URL, $this->client, $auth);
    $transport->send(makeEmail(), makeEnvelope());
});

it('sends header auth custom header', function () {
    $response = $this->createMock(ResponseInterface::class);
    $response->method('getStatusCode')->willReturn(200);

    $this->client->expects($this->once())
        ->method('request')
        ->with('POST', WEBHOOK_URL, $this->callback(function ($options) {
            return isset($options['headers']['X-API-Key'])
                && $options['headers']['X-API-Key'] === 'secret-key';
        }))
        ->willReturn($response);

    $auth = ['type' => 'header', 'header' => 'X-API-Key', 'token' => 'secret-key'];
    $transport = new N8nTransport(WEBHOOK_URL, $this->client, $auth);
    $transport->send(makeEmail(), makeEnvelope());
});

it('throws exception for invalid auth type', function () {
    new N8nTransport(WEBHOOK_URL, $this->client, ['type' => 'oauth']);
})->throws(\InvalidArgumentException::class, 'Invalid auth type "oauth"');

it('throws exception for basic auth without username', function () {
    new N8nTransport(WEBHOOK_URL, $this->client, ['type' => 'basic']);
})->throws(\InvalidArgumentException::class, 'Basic auth requires a "username"');

it('throws exception for header auth without header', function () {
    new N8nTransport(WEBHOOK_URL, $this->client, ['type' => 'header', 'token' => 'key']);
})->throws(\InvalidArgumentException::class, 'Header auth requires "header" and "token"');

it('throws exception for bearer auth without token', function () {
    new N8nTransport(WEBHOOK_URL, $this->client, ['type' => 'bearer']);
})->throws(\InvalidArgumentException::class, 'Bearer auth requires a "token"');

it('throws exception on 4xx response', function () {
    $response = $this->createMock(ResponseInterface::class);
    $response->method('getStatusCode')->willReturn(401);
    $response->method('getContent')->willReturn('Unauthorized');

    $this->client->method('request')->willReturn($response);

    $transport = new N8nTransport(WEBHOOK_URL, $this->client);
    $transport->send(makeEmail(), makeEnvelope());
})->throws(N8nTransportException::class, 'HTTP 401');

it('throws exception on 5xx response', function () {
    $response = $this->createMock(ResponseInterface::class);
    $response->method('getStatusCode')->willReturn(500);
    $response->method('getContent')->willReturn('Internal Server Error');

    $this->client->method('request')->willReturn($response);

    $transport = new N8nTransport(WEBHOOK_URL, $this->client);
    $transport->send(makeEmail(), makeEnvelope());
})->throws(N8nTransportException::class, 'HTTP 500');

it('sends full email with correct payload', function () {
    $response = $this->createMock(ResponseInterface::class);
    $response->method('getStatusCode')->willReturn(200);

    $this->client->expects($this->once())
        ->method('request')
        ->with('POST', WEBHOOK_URL, $this->callback(function ($options) {
            $json = $options['json'];

            return $json['subject'] === 'Test Subject'
                && $json['text'] === 'Hello World'
                && $json['html'] === '<p>Hello World</p>'
                && !empty($json['from'])
                && !empty($json['to']);
        }))
        ->willReturn($response);

    $transport = new N8nTransport(WEBHOOK_URL, $this->client);
    $transport->send(makeFullEmail(), makeEnvelope());
});

it('allows http url for localhost', function () {
    $transport = new N8nTransport('http://localhost:8080/webhook/test', $this->client);

    expect((string) $transport)->toBe('http://localhost:8080/webhook/test');
});
