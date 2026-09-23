<?php

namespace Masgeek\N8nMailer;

use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\Email;

/**
 * A callable that transforms the payload before sending.
 *
 * @param array<string, mixed> $payload
 * @return array<string, mixed>
 */
final class PayloadMapper
{
    /** @var callable(array<string, mixed>, Email, Envelope): array<string, mixed> */
    private $callback;

    /**
     * @param callable(array<string, mixed>, Email, Envelope): array<string, mixed> $callback
     */
    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function __invoke(array $payload, Email $email, Envelope $envelope): array
    {
        return ($this->callback)($payload, $email, $envelope);
    }
}
