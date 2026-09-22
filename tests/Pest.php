<?php

use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

function makeEmail(): Email
{
    $email = new Email();
    $email->subject('Test');
    $email->from('sender@example.com');
    $email->to('recipient@example.com');
    $email->text('Test body');

    return $email;
}

function makeFullEmail(): Email
{
    $email = new Email();
    $email->subject('Test Subject');
    $email->from(new Address('sender@example.com', 'Sender'));
    $email->to(new Address('recipient@example.com', 'Recipient'));
    $email->cc(new Address('cc@example.com', 'CC User'));
    $email->replyTo(new Address('reply@example.com', 'Reply User'));
    $email->text('Hello World');
    $email->html('<p>Hello World</p>');

    return $email;
}

function makeEnvelope(): Envelope
{
    return new Envelope(
        new Address('sender@example.com'),
        [new Address('recipient@example.com')]
    );
}
