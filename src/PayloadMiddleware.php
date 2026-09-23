<?php

namespace Masgeek\N8nMailer;

use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\Email;

interface PayloadMiddleware
{
    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function handle(array $payload, Email $email, Envelope $envelope): array;
}
