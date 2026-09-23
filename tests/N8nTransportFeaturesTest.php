<?php

use Masgeek\N8nMailer\Exception\N8nTransportException;
use Masgeek\N8nMailer\N8nTransport;
use Masgeek\N8nMailer\PayloadMapper;
use Masgeek\N8nMailer\PayloadMiddleware;
use Masgeek\N8nMailer\ResponseHandler;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

const FEATURE_WEBHOOK_URL = 'https://n8n.example.com/webhook/test-123';

beforeEach(function () {
    $this->client = $this->createMock(HttpClientInterface::class);
});

it('retries on 5xx failure and succeeds', function () {
    $failResponse = $this->createMock(ResponseInterface::class);
    $failResponse->method('getStatusCode')->willReturn(500);
    $failResponse->method('getContent')->willReturn('Server Error');

    $successResponse = $this->createMock(ResponseInterface::class);
    $successResponse->method('getStatusCode')->willReturn(200);

    $this->client->expects($this->exactly(2))
        ->method('request')
        ->willReturnOnConsecutiveCalls($failResponse, $successResponse);

    $transport = new N8nTransport(
        FEATURE_WEBHOOK_URL,
        $this->client,
        ['type' => 'none'],
        ['max_retries' => 1, 'retry_delay' => 1]
    );

    $transport->send(makeEmail(), makeEnvelope());
});

it('throws after max retries exhausted', function () {
    $failResponse = $this->createMock(ResponseInterface::class);
    $failResponse->method('getStatusCode')->willReturn(500);
    $failResponse->method('getContent')->willReturn('Server Error');

    $this->client->method('request')->willReturn($failResponse);

    $transport = new N8nTransport(
        FEATURE_WEBHOOK_URL,
        $this->client,
        ['type' => 'none'],
        ['max_retries' => 2, 'retry_delay' => 1]
    );

    $transport->send(makeEmail(), makeEnvelope());
})->throws(N8nTransportException::class, 'HTTP 500');

it('does not retry on 4xx errors', function () {
    $failResponse = $this->createMock(ResponseInterface::class);
    $failResponse->method('getStatusCode')->willReturn(401);
    $failResponse->method('getContent')->willReturn('Unauthorized');

    $this->client->expects($this->once())
        ->method('request')
        ->willReturn($failResponse);

    $transport = new N8nTransport(
        FEATURE_WEBHOOK_URL,
        $this->client,
        ['type' => 'none'],
        ['max_retries' => 3, 'retry_delay' => 1]
    );

    $transport->send(makeEmail(), makeEnvelope());
})->throws(N8nTransportException::class, 'HTTP 401');

it('applies payload mapper', function () {
    $response = $this->createMock(ResponseInterface::class);
    $response->method('getStatusCode')->willReturn(200);

    $this->client->expects($this->once())
        ->method('request')
        ->with('POST', FEATURE_WEBHOOK_URL, $this->callback(function ($options) {
            return isset($options['json']['custom_field'])
                && $options['json']['custom_field'] === 'added';
        }))
        ->willReturn($response);

    $mapper = new PayloadMapper(function (array $payload, Email $email, Envelope $envelope) {
        $payload['custom_field'] = 'added';
        return $payload;
    });

    $transport = new N8nTransport(
        FEATURE_WEBHOOK_URL,
        $this->client,
        ['type' => 'none'],
        ['payload_mapper' => $mapper]
    );

    $transport->send(makeEmail(), makeEnvelope());
});

it('applies middleware', function () {
    $response = $this->createMock(ResponseInterface::class);
    $response->method('getStatusCode')->willReturn(200);

    $this->client->expects($this->once())
        ->method('request')
        ->with('POST', FEATURE_WEBHOOK_URL, $this->callback(function ($options) {
            return isset($options['json']['middleware_field'])
                && $options['json']['middleware_field'] === 'processed';
        }))
        ->willReturn($response);

    $middleware = new class implements PayloadMiddleware {
        public function handle(array $payload, Email $email, Envelope $envelope): array
        {
            $payload['middleware_field'] = 'processed';
            return $payload;
        }
    };

    $transport = new N8nTransport(
        FEATURE_WEBHOOK_URL,
        $this->client,
        ['type' => 'none'],
        ['middleware' => [$middleware]]
    );

    $transport->send(makeEmail(), makeEnvelope());
});

it('applies response handler', function () {
    $response = $this->createMock(ResponseInterface::class);
    $response->method('getStatusCode')->willReturn(200);
    $response->method('getContent')->willReturn('{"id": "abc-123"}');

    $this->client->method('request')->willReturn($response);

    $handled = false;
    $handler = new ResponseHandler(function (array $response, Email $email, Envelope $envelope) use (&$handled) {
        $handled = true;
        expect($response['status_code'])->toBe(200);
        expect($response['body'])->toBe('{"id": "abc-123"}');
        return null;
    });

    $transport = new N8nTransport(
        FEATURE_WEBHOOK_URL,
        $this->client,
        ['type' => 'none'],
        ['response_handler' => $handler]
    );

    $transport->send(makeEmail(), makeEnvelope());

    expect($handled)->toBeTrue();
});

it('resolves dynamic webhook url', function () {
    $response = $this->createMock(ResponseInterface::class);
    $response->method('getStatusCode')->willReturn(200);

    $this->client->expects($this->once())
        ->method('request')
        ->with('POST', 'https://n8n.example.com/webhook/custom-path', $this->anything())
        ->willReturn($response);

    $resolver = function (string $defaultUrl, Email $email) {
        return 'https://n8n.example.com/webhook/custom-path';
    };

    $transport = new N8nTransport(
        FEATURE_WEBHOOK_URL,
        $this->client,
        ['type' => 'none'],
        ['url_resolver' => $resolver]
    );

    $transport->send(makeEmail(), makeEnvelope());
});

it('resolves dynamic url from recipient', function () {
    $response = $this->createMock(ResponseInterface::class);
    $response->method('getStatusCode')->willReturn(200);

    $this->client->expects($this->once())
        ->method('request')
        ->with('POST', 'https://n8n.example.com/webhook/acme-corp-com', $this->anything())
        ->willReturn($response);

    $resolver = function (string $defaultUrl, Email $email) {
        $to = $email->getTo()[0] ?? null;
        if ($to) {
            $domain = substr(strrchr($to->getAddress(), '@'), 1);
            $slug = str_replace('.', '-', $domain);
            return "https://n8n.example.com/webhook/{$slug}";
        }
        return $defaultUrl;
    };

    $transport = new N8nTransport(
        FEATURE_WEBHOOK_URL,
        $this->client,
        ['type' => 'none'],
        ['url_resolver' => $resolver]
    );

    $email = new Email();
    $email->subject('Test');
    $email->from('sender@example.com');
    $email->to('user@acme-corp.com');
    $email->text('Hello');

    $transport->send($email, makeEnvelope());
});

it('combines mapper, middleware, and handler', function () {
    $response = $this->createMock(ResponseInterface::class);
    $response->method('getStatusCode')->willReturn(200);
    $response->method('getContent')->willReturn('ok');

    $this->client->method('request')->willReturn($response);

    $order = [];

    $mapper = new PayloadMapper(function (array $payload) use (&$order) {
        $order[] = 'mapper';
        $payload['from_mapper'] = true;
        return $payload;
    });

    $middleware = new class ($order) implements PayloadMiddleware {
        private array $order;
        public function __construct(array &$order)
        {
            $this->order = &$order;
        }
        public function handle(array $payload, Email $email, Envelope $envelope): array
        {
            $this->order[] = 'middleware';
            $payload['from_middleware'] = true;
            return $payload;
        }
    };

    $handler = new ResponseHandler(function (array $response) use (&$order) {
        $order[] = 'handler';
        return null;
    });

    $transport = new N8nTransport(
        FEATURE_WEBHOOK_URL,
        $this->client,
        ['type' => 'none'],
        [
            'payload_mapper' => $mapper,
            'middleware' => [$middleware],
            'response_handler' => $handler,
        ]
    );

    $transport->send(makeEmail(), makeEnvelope());

    expect($order)->toBe(['mapper', 'middleware', 'handler']);
});
