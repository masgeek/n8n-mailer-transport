<?php

namespace Masgeek\N8nMailer\Exception;

use Symfony\Component\Mailer\Exception\TransportException;

class N8nTransportException extends TransportException
{
    public static function invalidWebhookUrl(string $url): self
    {
        return new self(sprintf('Invalid n8n webhook URL: "%s".', $url));
    }

    public static function requestFailed(string $url, int $statusCode, string $body): self
    {
        return new self(sprintf(
            'Failed to send email to n8n webhook "%s" (HTTP %d): %s',
            $url,
            $statusCode,
            $body
        ));
    }
}
