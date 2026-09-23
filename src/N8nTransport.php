<?php

namespace Masgeek\N8nMailer;

use Illuminate\Support\Arr;
use Masgeek\N8nMailer\Exception\N8nTransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\MessageConverter;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class N8nTransport extends AbstractTransport
{
    private string $webhookUrl;
    private HttpClientInterface $client;
    /** @var array{type: string, username?: string, password?: string, header?: string, token?: string} */
    private array $auth;
    private int $maxRetries;
    private int $retryDelay;
    /** @var callable(string, Email): string */
    private $webhookUrlResolver;
    /** @var PayloadMapper|null */
    private $payloadMapper;
    /** @var ResponseHandler|null */
    private $responseHandler;
    /** @var list<PayloadMiddleware> */
    private array $middleware = [];

    /**
     * @param array{type: string, username?: string, password?: string, header?: string, token?: string} $auth
     * @param array{max_retries?: int, retry_delay?: int, url_resolver?: callable(string, Email): string, payload_mapper?: PayloadMapper, response_handler?: ResponseHandler, middleware?: list<PayloadMiddleware>} $options
     */
    public function __construct(
        string              $webhookUrl,
        HttpClientInterface $client,
        array               $auth = ['type' => 'none'],
        array               $options = [],
    )
    {
        parent::__construct();
        $this->webhookUrl = $this->validateUrl($webhookUrl);
        $this->client = $client;
        $this->auth = $this->validateAuth($auth);
        $this->maxRetries = $options['max_retries'] ?? 0;
        $this->retryDelay = $options['retry_delay'] ?? 1000;
        $this->webhookUrlResolver = $options['url_resolver'] ?? null;
        $this->payloadMapper = $options['payload_mapper'] ?? null;
        $this->responseHandler = $options['response_handler'] ?? null;
        $this->middleware = $options['middleware'] ?? [];
    }

    /**
     * @param array{type: string, username?: string, password?: string, header?: string, token?: string} $auth
     * @param array{max_retries?: int, retry_delay?: int, url_resolver?: callable(string, Email): string, payload_mapper?: PayloadMapper, response_handler?: ResponseHandler, middleware?: list<PayloadMiddleware>} $options
     */
    public static function create(string $webhookUrl, HttpClientInterface $client, array $auth = ['type' => 'none'], array $options = []): self
    {
        return new self($webhookUrl, $client, $auth, $options);
    }

    protected function doSend(SentMessage $message): void
    {
        $originalMessage = $message->getOriginalMessage();
        if (!$originalMessage instanceof \Symfony\Component\Mime\Message) {
            throw new \RuntimeException('Expected a Symfony Mime Message instance.');
        }
        $email = MessageConverter::toEmail($originalMessage);
        $envelope = $message->getEnvelope();

        $payload = [
            'subject' => $email->getSubject(),
            'from' => $this->formatAddresses($email->getFrom()),
            'to' => $this->formatAddresses($email->getTo()),
            'cc' => $this->formatAddresses($email->getCc()),
            'bcc' => $this->formatAddresses($email->getBcc()),
            'replyTo' => $this->formatAddresses($email->getReplyTo()),
            'text' => $email->getTextBody(),
            'html' => $email->getHtmlBody(),
            'headers' => $this->formatHeaders($email->getHeaders()),
            'attachments' => $this->formatAttachments($email->getAttachments()),
        ];

        if ($this->payloadMapper !== null) {
            $payload = ($this->payloadMapper)($payload, $email, $envelope);
        }

        foreach ($this->middleware as $mw) {
            $payload = $mw->handle($payload, $email, $envelope);
        }

        $webhookUrl = $this->resolveWebhookUrl($envelope, $email);

        $options = ['json' => $payload];
        $authHeaders = $this->buildAuthHeaders();
        if ($authHeaders !== []) {
            $options['headers'] = $authHeaders;
        }

        $response = $this->sendWithRetry($webhookUrl, $options);

        if ($this->responseHandler !== null) {
            ($this->responseHandler)(
                [
                    'status_code' => $response['status_code'],
                    'body' => $response['body'],
                ],
                $email,
                $envelope
            );
        }
    }


    public function __toString(): string
    {
        return $this->webhookUrl;
    }

    /**
     * @param array<string, mixed> $options
     * @return array{status_code: int, body: string}
     */
    private function sendWithRetry(string $url, array $options): array
    {
        $lastException = null;
        $attempts = $this->maxRetries + 1;

        for ($i = 0; $i < $attempts; $i++) {
            try {
                $response = $this->client->request('POST', $url, $options);
                $statusCode = $response->getStatusCode();
                $body = $response->getContent(false);

                if ($statusCode < 400) {
                    return ['status_code' => $statusCode, 'body' => $body];
                }

                if ($statusCode < 500 || $i === $attempts - 1) {
                    throw N8nTransportException::requestFailed($url, $statusCode, $body);
                }

                $lastException = N8nTransportException::requestFailed($url, $statusCode, $body);
            } catch (\Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface $e) {
                if ($i === $attempts - 1) {
                    throw N8nTransportException::requestFailed($url, 0, $e->getMessage());
                }
                $lastException = N8nTransportException::requestFailed($url, 0, $e->getMessage());
            }

            if ($i < $attempts - 1) {
                usleep($this->retryDelay * 1000);
            }
        }

        throw $lastException;
    }

    private function resolveWebhookUrl(\Symfony\Component\Mailer\Envelope $envelope, Email $email): string
    {
        if ($this->webhookUrlResolver !== null) {
            $resolved = ($this->webhookUrlResolver)($this->webhookUrl, $email);
            return $this->validateUrl($resolved);
        }

        return $this->webhookUrl;
    }

    /**
     * @return array<string, string>
     */
    private function buildAuthHeaders(): array
    {
        return match ($this->auth['type']) {
            'basic' => [
                'Authorization' => 'Basic ' . base64_encode(
                        ($this->auth['username'] ?? '') . ':' . ($this->auth['password'] ?? '')
                    ),
            ],
            'header' => [
                $this->auth['header'] => $this->auth['token'] ?? '',
            ],
            'jwt' => [
                'Authorization' => 'Bearer ' . ($this->auth['token'] ?? ''),
            ],
            default => [],
        };
    }

    private function validateUrl(string $url): string
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw N8nTransportException::invalidWebhookUrl($url);
        }

        $parsed = parse_url($url);
        if (!isset($parsed['scheme'], $parsed['host'])) {
            throw N8nTransportException::invalidWebhookUrl($url);
        }

        $scheme = strtolower($parsed['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw N8nTransportException::invalidWebhookUrl($url);
        }

        return $url;
    }

    /**
     * @param array{type?: string, username?: string, password?: string, header?: string, token?: string} $auth
     * @return array{type: string, username?: string, password?: string, header?: string, token?: string}
     */
    private function validateAuth(array $auth): array
    {
        $type = $auth['type'] ?? 'none';

        if (!in_array($type, ['none', 'basic', 'header', 'jwt'], true)) {
            throw new \InvalidArgumentException(
                sprintf('Invalid auth type "%s". Expected: none, basic, header, or jwt.', $type)
            );
        }

        if ($type === 'basic' && empty($auth['username'])) {
            throw new \InvalidArgumentException('Basic auth requires a "username".');
        }

        if ($type === 'header' && (empty($auth['header']) || empty($auth['token']))) {
            throw new \InvalidArgumentException('Header auth requires "header" and "token".');
        }

        if ($type === 'jwt' && empty($auth['token'])) {
            throw new \InvalidArgumentException('JWT auth requires a "token".');
        }

        return $auth;
    }

    /**
     * @param Address[] $addresses
     * @return array<int, array{email: string, name: string}>
     */
    private function formatAddresses(?array $addresses): array
    {
        if ($addresses === null) {
            return [];
        }

        return array_map(
            fn(Address $addr) => [
                'email' => $addr->getAddress(),
                'name' => $addr->getName(),
            ],
            $addresses
        );
    }

    /**
     * @return array<string, string>
     */
    private function formatHeaders(Headers $headers): array
    {
        $result = [];
        foreach ($headers->all() as $name => $value) {
            $result[$name] = is_array($value) ? implode(', ', $value) : $value;
        }
        return $result;
    }

    /**
     * @param DataPart[] $attachments
     * @return array<int, array{filename: string, contentType: string, body: string}>
     */
    private function formatAttachments(array $attachments): array
    {
        $result = [];
        foreach ($attachments as $attachment) {
            $result[] = [
                'filename' => $attachment->getFilename(),
                'contentType' => $attachment->getMediaType() . '/' . $attachment->getMediaSubtype(),
                'body' => base64_encode($attachment->getBody()),
            ];
        }
        return $result;
    }
}
