# n8n-mailer-transport

A Symfony Mailer transport that POSTs emails as JSON to an [n8n](https://n8n.io) webhook URL. Works as a drop-in mail driver for Laravel too.

## Why

Route outgoing emails through n8n workflows — forward to Slack, log to a database, trigger automations, or send via any n8n-supported provider. No SMTP required.

## Requirements

- PHP 8.4+
- Symfony HttpClient 7.x or 8.x
- Symfony Mailer 7.x or 8.x
- Laravel 11+ (optional, for Laravel integration)

## Install

```bash
composer require masgeek/n8n-mailer-transport
```

## Quick start

### Symfony

```php
use Masgeek\N8nMailer\N8nTransport;
use Symfony\Component\Mailer\Mailer;

$mailer = new Mailer(
    new N8nTransport('https://your-n8n.example.com/webhook/email-id')
);
```

### Laravel

`config/mail.php`:

```php
'mailers' => [
    'n8n-mailer' => [
        'transport' => 'n8n-mailer',
    ],
],
```

`config/services.php`:

```php
'n8n-mailer' => [
    'url' => env('N8N_MAILER_URL'),
],
```

Send with `Mail::to('user@example.com')->send(new YourMailable())`.

Service provider auto-discovered via Composer.

---

## Authentication

Supports four auth modes. Configure via `config/services.php` (Laravel) or programmatically (Symfony).

### `none` — No authentication

Default. No auth headers sent.

**Laravel:**

```php
// config/services.php
'n8n-mailer' => [
    'url' => env('N8N_MAILER_URL'),
    // auth defaults to ['type' => 'none']
],
```

**Symfony:**

```php
$transport = new N8nTransport(
    'https://n8n.example.com/webhook/id',
    $client,
    ['type' => 'none']
);
```

---

### `basic` — HTTP Basic Auth

Sends `Authorization: Basic base64(username:password)`.

**Laravel:**

```php
// config/services.php
'n8n-mailer' => [
    'url' => env('N8N_MAILER_URL'),
    'auth' => [
        'type' => 'basic',
        'username' => env('N8N_MAILER_USER'),
        'password' => env('N8N_MAILER_PASS'),
    ],
],
```

```env
# .env
N8N_MAILER_URL=https://n8n.example.com/webhook/email-id
N8N_MAILER_USER=my-username
N8N_MAILER_PASS=my-password
```

**Symfony (programmatic):**

```php
use Masgeek\N8nMailer\N8nTransport;

$transport = new N8nTransport(
    'https://n8n.example.com/webhook/id',
    $client,
    ['type' => 'basic', 'username' => 'my-username', 'password' => 'my-password']
);
```

**Symfony (DSN string):**

```php
use Masgeek\N8nMailer\N8nTransportFactory;

$factory = new N8nTransportFactory($client);
$transport = $factory->createFromString('https://my-username:my-password@n8n.example.com/webhook/id');
```

**Resulting header:**

```
Authorization: Basic bXktdXNlcm5hbWU6bXktcGFzc3dvcmQ=
```

---

### `bearer` — Bearer Token

Sends `Authorization: Bearer <token>`. Use this for n8n API tokens or any bearer token authentication.

**Laravel:**

```php
// config/services.php
'n8n-mailer' => [
    'url' => env('N8N_MAILER_URL'),
    'auth' => [
        'type' => 'bearer',
        'token' => env('N8N_MAILER_TOKEN'),
    ],
],
```

```env
# .env
N8N_MAILER_URL=https://n8n.example.com/webhook/email-id
N8N_MAILER_TOKEN=eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
```

**Symfony (programmatic):**

```php
use Masgeek\N8nMailer\N8nTransport;

$transport = new N8nTransport(
    'https://n8n.example.com/webhook/id',
    $client,
    ['type' => 'bearer', 'token' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...']
);
```

**Symfony (DSN string):**

```php
use Masgeek\N8nMailer\N8nTransportFactory;

$factory = new N8nTransportFactory($client);
$transport = $factory->createFromString('https://your-token@n8n.example.com/webhook/id');
```

**Resulting header:**

```
Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
```

---

### `header` — Custom Header

Sends a custom header with a token value. Use this for API keys, HMAC signatures, or any non-standard auth scheme.

**Laravel:**

```php
// config/services.php
'n8n-mailer' => [
    'url' => env('N8N_MAILER_URL'),
    'auth' => [
        'type' => 'header',
        'header' => 'X-API-Key',
        'token' => env('N8N_MAILER_API_KEY'),
    ],
],
```

```env
# .env
N8N_MAILER_URL=https://n8n.example.com/webhook/email-id
N8N_MAILER_API_KEY=sk_live_abc123...
```

**Symfony (programmatic):**

```php
use Masgeek\N8nMailer\N8nTransport;

// API key
$transport = new N8nTransport(
    'https://n8n.example.com/webhook/id',
    $client,
    ['type' => 'header', 'header' => 'X-API-Key', 'token' => 'sk_live_abc123...']
);

// HMAC signature
$transport = new N8nTransport(
    'https://n8n.example.com/webhook/id',
    $client,
    ['type' => 'header', 'header' => 'X-Webhook-Signature', 'token' => 'sha256=...']
);
```

**Resulting header:**

```
X-API-Key: sk_live_abc123...
```

---

### Auth types summary

| Type | Config keys | Header sent |
|------|-------------|-------------|
| `none` | _(none)_ | _(none)_ |
| `basic` | `username`, `password` | `Authorization: Basic base64(user:pass)` |
| `bearer` | `token` | `Authorization: Bearer <token>` |
| `header` | `header`, `token` | `<header>: <token>` |

---

## Payload

### Default payload structure

POST body sent to your webhook (JSON):

```json
{
  "subject": "Meeting Tomorrow",
  "from": [{"email": "sender@example.com", "name": "Sender"}],
  "to": [{"email": "recipient@example.com", "name": "Recipient"}],
  "cc": [{"email": "cc@example.com", "name": "CC User"}],
  "cc_count": 1,
  "bcc": [],
  "bcc_count": 0,
  "replyTo": [],
  "sender": null,
  "return_path": null,
  "text": "Plain text body",
  "text_charset": "utf-8",
  "html": "<p>HTML body</p>",
  "html_charset": "utf-8",
  "date": "2026-09-23T12:00:00+00:00",
  "priority": 3,
  "has_attachments": true,
  "attachments": [
    {
      "filename": "document.pdf",
      "contentType": "application/pdf",
      "body": "JVBERi0xLjQK..."
    }
  ],
  "headers": {
    "X-Custom-Header": "value",
    "Content-Type": "text/html"
  }
}
```

### Payload fields

| Field | Type | Description |
|-------|------|-------------|
| `subject` | `string` | Email subject line |
| `from` | `array` | Sender addresses with `email` and `name` |
| `to` | `array` | Primary recipients with `email` and `name` |
| `cc` | `array` | Carbon copy recipients |
| `cc_count` | `int` | Number of CC recipients |
| `bcc` | `array` | Blind carbon copy recipients |
| `bcc_count` | `int` | Number of BCC recipients |
| `replyTo` | `array` | Reply-to addresses |
| `sender` | `array\|null` | Sender address (differs from `from` when set explicitly) |
| `return_path` | `string\|null` | Return-path (bounce) address |
| `text` | `string\|null` | Plain text body |
| `text_charset` | `string\|null` | Plain text character set |
| `html` | `string\|null` | HTML body |
| `html_charset` | `string\|null` | HTML character set |
| `date` | `string\|null` | Email date (ISO 8601 format) |
| `priority` | `int` | Email priority (1 = high, 3 = normal, 5 = low) |
| `has_attachments` | `bool` | Whether the email has attachments |
| `attachments` | `array` | File attachments with `filename`, `contentType`, and base64 `body` |
| `headers` | `array` | All email headers as key-value pairs |

### Sending attachments

Attachments are automatically included in the payload as base64-encoded strings:

```php
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

$email = new Email();
$email->subject('Report attached');
$email->from('sender@example.com');
$email->to('recipient@example.com');
$email->text('Please find the report attached.');
$email->attachFromPath('/path/to/report.pdf');
```

### Custom headers

Any headers set on the email are included in the `headers` field:

```php
$email = new Email();
$email->getHeaders()->addTextHeader('X-Campaign-Id', 'spring-2026');
$email->getHeaders()->addTextHeader('X-Priority', '1');
```

---

## Customizing the payload

### Payload mapper

Transform the payload before it's sent using `PayloadMapper`. The callback receives the default payload, the `Email` object, and the `Envelope`.

**Laravel (via transport options):**

This requires creating a custom mail transport or using the Symfony approach. See the Symfony example below.

**Symfony:**

```php
use Masgeek\N8nMailer\N8nTransport;
use Masgeek\N8nMailer\PayloadMapper;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\Email;

$mapper = new PayloadMapper(function (array $payload, Email $email, Envelope $envelope) {
    // Add custom fields
    $payload['campaign_id'] = 'spring-2026';
    $payload['source'] = 'transactional';
    $payload['metadata'] = [
        'timestamp' => time(),
        'recipient_domain' => substr(strrchr($email->getTo()[0]->getAddress(), '@'), 1),
    ];

    // Remove fields you don't need
    unset($payload['bcc']);

    // Rename fields
    $payload['recipients'] = $payload['to'];
    unset($payload['to']);

    return $payload;
});

$transport = new N8nTransport(
    'https://n8n.example.com/webhook/id',
    $client,
    ['type' => 'none'],
    ['payload_mapper' => $mapper]
);
```

**Result:**

```json
{
  "subject": "Meeting Tomorrow",
  "from": [{"email": "sender@example.com", "name": "Sender"}],
  "recipients": [{"email": "recipient@example.com", "name": "Recipient"}],
  "cc": [],
  "replyTo": [],
  "text": "Plain text body",
  "html": "<p>HTML body</p>",
  "headers": {},
  "attachments": [],
  "campaign_id": "spring-2026",
  "source": "transactional",
  "metadata": {
    "timestamp": 1758500000,
    "recipient_domain": "example.com"
  }
}
```

### Middleware

Chain multiple transformations via the `PayloadMiddleware` interface. Middleware runs after the payload mapper.

```php
use Masgeek\N8nMailer\PayloadMiddleware;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\Email;

class AddTimestampMiddleware implements PayloadMiddleware
{
    public function handle(array $payload, Email $email, Envelope $envelope): array
    {
        $payload['sent_at'] = date('c');
        return $payload;
    }
}

class RecipientCountMiddleware implements PayloadMiddleware
{
    public function handle(array $payload, Email $email, Envelope $envelope): array
    {
        $payload['recipient_count'] = count($email->getTo());
        return $payload;
    }
}

$transport = new N8nTransport(
    'https://n8n.example.com/webhook/id',
    $client,
    ['type' => 'none'],
    ['middleware' => [new AddTimestampMiddleware(), new RecipientCountMiddleware()]]
);
```

**Execution order:** payload mapper → middleware\[0\] → middleware\[1\] → ... → send

### Combining mapper and middleware

```php
$mapper = new PayloadMapper(function (array $payload) {
    $payload['custom'] = true;
    return $payload;
});

$middleware = new class implements PayloadMiddleware {
    public function handle(array $payload, Email $email, Envelope $envelope): array
    {
        $payload['processed'] = true;
        return $payload;
    }
};

$transport = new N8nTransport(
    'https://n8n.example.com/webhook/id',
    $client,
    ['type' => 'none'],
    [
        'payload_mapper' => $mapper,
        'middleware' => [$middleware],
    ]
);

// Result: { ..., "custom": true, "processed": true }
```

---

## Features

### Retry on failure

Automatically retries on 5xx errors and network failures.

**Symfony:**

```php
$transport = new N8nTransport(
    'https://n8n.example.com/webhook/id',
    $client,
    ['type' => 'none'],
    [
        'max_retries' => 3,    // retry up to 3 times (4 total attempts)
        'retry_delay' => 1000, // 1 second between retries (in ms)
    ]
);
```

**Laravel (`config/services.php`):**

```php
'n8n-mailer' => [
    'url' => env('N8N_MAILER_URL'),
    'max_retries' => 3,
    'retry_delay' => 1000,
],
```

**Behavior:**

| Response | Retries? | Why |
|----------|----------|-----|
| 2xx | No | Success |
| 3xx | No | Redirect (treated as success) |
| 4xx | No | Client error — fails immediately |
| 5xx | Yes | Server error — retryable |
| Network error | Yes | Transport exception — retryable |

---

### Response handler

Process the n8n webhook response after sending.

```php
use Masgeek\N8nMailer\ResponseHandler;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\Email;

$handler = new ResponseHandler(function (array $response, Email $email, Envelope $envelope) {
    // $response['status_code'] — HTTP status code (int)
    // $response['body'] — response body (string)

    $data = json_decode($response['body'], true);

    // Log to your system
    logger()->info('Email sent to n8n', [
        'recipient' => $email->getTo()[0]->getAddress(),
        'tracking_id' => $data['id'] ?? null,
        'status' => $response['status_code'],
    ]);

    // Return value is ignored (reserved for future use)
    return null;
});

$transport = new N8nTransport(
    'https://n8n.example.com/webhook/id',
    $client,
    ['type' => 'none'],
    ['response_handler' => $handler]
);
```

---

### Dynamic webhook URL

Resolve the webhook URL per email — useful for multi-tenant setups or per-recipient routing.

```php
$transport = new N8nTransport(
    'https://n8n.example.com/webhook/default',
    $client,
    ['type' => 'none'],
    [
        'url_resolver' => function (string $defaultUrl, Email $email) {
            $to = $email->getTo()[0] ?? null;
            if ($to) {
                // Route by recipient domain
                $domain = substr(strrchr($to->getAddress(), '@'), 1);
                $slug = str_replace('.', '-', $domain);
                return "https://n8n.example.com/webhook/{$slug}";
            }
            return $defaultUrl;
        },
    ]
);
```

**Example routing:**

| Recipient | Resolved URL |
|-----------|--------------|
| `user@acme.com` | `https://n8n.example.com/webhook/acme-com` |
| `admin@bigcorp.org` | `https://n8n.example.com/webhook/bigcorp-org` |
| No recipient | `https://n8n.example.com/webhook/default` |

---

## Error handling

### N8nTransportException

Thrown for invalid URLs and failed HTTP requests.

```php
use Masgeek\N8nMailer\Exception\N8nTransportException;

try {
    $mailer->send($email);
} catch (N8nTransportException $e) {
    // Invalid URL
    // HTTP 4xx/5xx response
    echo $e->getMessage();
    // e.g., "Failed to send email to n8n webhook "https://..." (HTTP 401): Unauthorized"
}
```

### InvalidArgumentException

Thrown on construction for invalid configuration.

```php
// These throw \InvalidArgumentException:
new N8nTransport('not-a-url', $client);                    // Invalid URL
new N8nTransport($url, $client, ['type' => 'oauth']);      // Invalid auth type
new N8nTransport($url, $client, ['type' => 'basic']);      // Missing username
new N8nTransport($url, $client, ['type' => 'bearer']);        // Missing token
new N8nTransport($url, $client, ['type' => 'header', 'header' => 'X-Key']); // Missing token
```

---

## Full Laravel configuration

### config/mail.php

```php
return [
    'default' => env('MAIL_MAILER', 'n8n-mailer'),

    'mailers' => [
        'n8n-mailer' => [
            'transport' => 'n8n-mailer',
        ],
    ],
];
```

### config/services.php

```php
return [
    'n8n-mailer' => [
        'url' => env('N8N_MAILER_URL'),
        'timeout' => env('N8N_MAILER_TIMEOUT', 30),
        'max_retries' => env('N8N_MAILER_RETRIES', 0),
        'retry_delay' => env('N8N_MAILER_RETRY_DELAY', 1000),
        'auth' => [
            'type' => env('N8N_MAILER_AUTH', 'none'),
            'username' => env('N8N_MAILER_USER'),
            'password' => env('N8N_MAILER_PASS'),
            'token' => env('N8N_MAILER_TOKEN'),
            'header' => env('N8N_MAILER_AUTH_HEADER'),
        ],
    ],
];
```

### .env

```env
N8N_MAILER_URL=https://n8n.example.com/webhook/email-id
N8N_MAILER_TIMEOUT=30
N8N_MAILER_RETRIES=3
N8N_MAILER_RETRY_DELAY=1000
N8N_MAILER_AUTH=basic
N8N_MAILER_USER=my-username
N8N_MAILER_PASS=my-password
```

---

## Testing

```bash
composer test
```

41 tests covering transport, factory, auth, retry, middleware, payload mapping, response handling, and Laravel integration.

## Project structure

```
src/
  N8nTransport.php                — Symfony Mailer transport
  N8nTransportFactory.php         — factory + DSN parsing
  PayloadMiddleware.php           — middleware interface
  PayloadMapper.php               — payload transformation
  ResponseHandler.php             — response processing
  Exception/
    N8nTransportException.php     — transport-specific exceptions
  Laravel/
    N8nMailerServiceProvider.php  — Laravel auto-discovery
tests/
  Pest.php                        — helper functions
  N8nTestCase.php                 — Testbench base class
  N8nTransportTest.php            — transport unit tests
  N8nTransportFactoryTest.php     — factory + DSN tests
  N8nTransportFeaturesTest.php    — retry/mapper/middleware/handler tests
  N8nMailerServiceProviderTest.php — Laravel integration tests
```

## License

MIT
