<?php

namespace Masgeek\N8nMailer;

use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\Email;

/**
 * A callable that handles the n8n webhook response.
 *
 * @param array{status_code: int, body: string} $response
 */
final class ResponseHandler
{
    /** @var callable(array{status_code: int, body: string}, Email, Envelope): (string|null) */
    private $callback;

    /**
     * @param callable(array{status_code: int, body: string}, Email, Envelope): (string|null) $callback
     */
    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    /**
     * @param array{status_code: int, body: string} $response
     */
    public function __invoke(array $response, Email $email, Envelope $envelope): ?string
    {
        /** @var string|null */
        return ($this->callback)($response, $email, $envelope);
    }
}
