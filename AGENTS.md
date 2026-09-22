# n8n-mailer-transport

Symfony Mailer transport that POSTs emails as JSON to an n8n webhook URL.

## What this is

A minimal PHP library (`masgeek/n8n-mailer-transport`) providing:
- `N8nTransport` — Symfony Mailer transport (`n8n+https://` DSN scheme)
- `N8nTransportFactory` — factory for Symfony's transport auto-discovery
- `N8nMailerServiceProvider` — Laravel integration (registers `n8n` mail driver)

## Install

```bash
composer install
```

Requires PHP 8.4+, Symfony HttpClient + Mailer (7.x or 8.x).

## Usage

### Symfony Mailer

```php
$mailer = new Mailer(new N8nTransport('https://your-n8n.example.com/webhook/email-id'));
```

Or via DSN: `n8n+https://your-n8n.example.com/webhook/email-id`

### Laravel

Register in `config/mail.php`:
```php
'mailers' => [
    'n8n' => [
        'transport' => 'n8n',
        'url' => env('N8N_WEBHOOK_URL'),
    ],
],
```

Add to `config/services.php`:
```php
'n8n' => [
    'url' => env('N8N_WEBHOOK_URL'),
    'timeout' => 30,
],
```

Service provider auto-discovered via Composer `extra.laravel.providers`.

## Authentication

Supports `none`, `basic`, `header`, and `jwt` auth.

### Laravel (`config/services.php`)

```php
'n8n' => [
    'url' => env('N8N_WEBHOOK_URL'),
    'auth' => [
        'type' => 'basic',
        'username' => env('N8N_WEBHOOK_USER'),
        'password' => env('N8N_WEBHOOK_PASS'),
    ],
],
```

### Symfony DSN

```php
// Basic auth (user:pass in URL)
$mailer = new Mailer(N8nTransportFactory::create(
    Dsn::fromString('n8n+https://user:pass@your-n8n.example.com/webhook/id')
));

// JWT/Bearer token (token as user in URL)
$mailer = new Mailer(N8nTransportFactory::create(
    Dsn::fromString('n8n+https://your-token@your-n8n.example.com/webhook/id')
));
```

### Auth types

| Type | Config keys | Result |
|------|-------------|--------|
| `none` | _(none)_ | No auth headers |
| `basic` | `username`, `password` | `Authorization: Basic base64(user:pass)` |
| `header` | `header`, `token` | Custom header (e.g. `X-API-Key: xxx`) |
| `jwt` | `token` | `Authorization: Bearer <token>` |

## Payload structure

POST body sent to webhook (JSON):
```json
{
  "subject": "...",
  "from": [{"email": "...", "name": "..."}],
  "to": [{"email": "...", "name": "..."}],
  "cc": [...],
  "bcc": [...],
  "replyTo": [...],
  "text": "...",
  "html": "...",
  "headers": {"key": "value"},
  "attachments": [{"filename": "...", "contentType": "...", "body": "..."}]
}
```

## Project structure

```
src/
  N8nTransport.php          — transport implementation
  N8nTransportFactory.php   — Symfony factory
  Exception/
    N8nTransportException.php — custom exceptions
  Laravel/
    N8nMailerServiceProvider.php — Laravel auto-discovery
```

## Error handling

- `N8nTransportException` thrown for invalid URLs and failed HTTP requests
- URL validation ensures scheme is http/https
- HTTP 4xx/5xx responses throw exceptions with status code and body
- Auth config validated on construction
