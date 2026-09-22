<?php

namespace Masgeek\N8nMailer;

use Masgeek\N8nMailer\Exception\N8nTransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\MessageConverter;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class N8nTransport extends AbstractTransport
{
    private string $webhookUrl;
    private HttpClientInterface $client;
    /** @var array{type: string, username?: string, password?: string, header?: string, token?: string} */
    private array $auth;

    /**
     * @param array{type?: string, username?: string, password?: string, header?: string, token?: string} $auth
     */
    public function __construct(
        string $webhookUrl,
        HttpClientInterface $client,
        array $auth = ['type' => 'none']
    ) {
        parent::__construct();
        $this->webhookUrl = $this->validateUrl($webhookUrl);
        $this->client = $client;
        $this->auth = $this->validateAuth($auth);
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

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

        $options = ['json' => $payload];
        $authHeaders = $this->buildAuthHeaders();
        if ($authHeaders !== []) {
            $options['headers'] = $authHeaders;
        }

        $response = $this->client->request('POST', $this->webhookUrl, $options);

        $statusCode = $response->getStatusCode();
        if ($statusCode >= 400) {
            $body = $response->getContent(false);
            throw N8nTransportException::requestFailed($this->webhookUrl, $statusCode, $body);
        }
    }

    public function __toString(): string
    {
        return sprintf('n8n+%s', $this->webhookUrl);
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

    private function formatHeaders($headers): array
    {
        $result = [];
        foreach ($headers->all() as $name => $value) {
            $result[$name] = is_array($value) ? implode(', ', $value) : $value;
        }
        return $result;
    }

    /**
     * @param \Symfony\Component\Mime\Attachment[] $attachments
     * @return array<int, array{filename: string, contentType: string, body: string}>
     */
    private function formatAttachments(array $attachments): array
    {
        $result = [];
        foreach ($attachments as $attachment) {
            $result[] = [
                'filename' => $attachment->getFilename(),
                'contentType' => $attachment->getMediaType() . '/' . $attachment->getSubtype(),
                'body' => base64_encode($attachment->getBody()),
            ];
        }
        return $result;
    }
}
