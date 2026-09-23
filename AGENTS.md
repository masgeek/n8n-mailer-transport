# n8n-mailer-transport

Symfony Mailer transport that POSTs emails as JSON to an n8n webhook URL.

## What this is

A minimal PHP library (`masgeek/n8n-mailer-transport`) providing:
- `N8nTransport` — Symfony Mailer transport (`https://` DSN scheme)
- `N8nTransportFactory` — factory for Symfony's transport auto-discovery
- `N8nMailerServiceProvider` — Laravel integration (registers `n8n-mailer` mail driver)

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

Or via DSN: `https://your-n8n.example.com/webhook/email-id`

### Laravel

Register in `config/mail.php`:
```php
'mailers' => [
    'n8n-mailer' => [
        'transport' => 'n8n-mailer',
    ],
],
```

Add to `config/services.php`:
```php
'n8n-mailer' => [
    'url' => env('N8N_MAILER_URL'),
    'timeout' => 30,
],
```

Service provider auto-discovered via Composer `extra.laravel.providers`.

## Authentication

Supports `none`, `basic`, `header`, and `bearer` auth.

### Laravel (`config/services.php`)

```php
'n8n-mailer' => [
    'url' => env('N8N_MAILER_URL'),
    'auth' => [
        'type' => 'basic',
        'username' => env('N8N_MAILER_USER'),
        'password' => env('N8N_MAILER_PASS'),
    ],
],
```

### Auth types

| Type | Config keys | Result |
|------|-------------|--------|
| `none` | _(none)_ | No auth headers |
| `basic` | `username`, `password` | `Authorization: Basic base64(user:pass)` |
| `header` | `header`, `token` | Custom header (e.g. `X-API-Key: xxx`) |
| `bearer` | `token` | `Authorization: Bearer <token>` |

## Features

- **Retry**: `max_retries` + `retry_delay` for 5xx/network errors
- **Payload mapper**: `PayloadMapper` callback to transform payload
- **Middleware**: `PayloadMiddleware` interface for chaining transforms
- **Response handler**: `ResponseHandler` callback to process n8n response
- **Dynamic URL**: `url_resolver` callback to resolve webhook URL per email

## Payload structure

POST body sent to webhook (JSON):
```json
{
  "subject": "...",
  "from": [{"email": "...", "name": "..."}],
  "to": [{"email": "...", "name": "..."}],
  "cc": [...],
  "cc_count": 0,
  "bcc": [...],
  "bcc_count": 0,
  "replyTo": [...],
  "sender": null,
  "return_path": null,
  "text": "...",
  "text_charset": "utf-8",
  "html": "...",
  "html_charset": "utf-8",
  "date": "2026-09-23T12:00:00+00:00",
  "priority": 3,
  "has_attachments": false,
  "attachments": [{"filename": "...", "contentType": "...", "body": "..."}],
  "headers": {"key": "value"}
}
```

## Project structure

```
src/
  N8nTransport.php          — transport implementation
  N8nTransportFactory.php   — Symfony factory
  PayloadMiddleware.php     — middleware interface
  PayloadMapper.php         — payload transformation
  ResponseHandler.php       — response processing
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
