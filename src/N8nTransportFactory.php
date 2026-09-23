<?php

namespace Masgeek\N8nMailer;

use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class N8nTransportFactory extends AbstractTransportFactory
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
        parent::__construct();
    }

    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();

        if (!in_array($scheme, $this->getSupportedSchemes(), true)) {
            throw new UnsupportedSchemeException($dsn, 'n8n', $this->getSupportedSchemes());
        }

        $url = sprintf('%s://%s', $scheme, $dsn->getHost());

        $port = $dsn->getPort();
        if ($port !== null && $port !== ('https' === $scheme ? 443 : 80)) {
            $url .= sprintf(':%d', $port);
        }

        return new N8nTransport($url, $this->httpClient, $this->parseAuth($dsn));
    }

    public function createFromString(string $dsn): TransportInterface
    {
        $parsed = parse_url($dsn);

        if ($parsed === false || !isset($parsed['scheme'], $parsed['host'])) {
            throw new \InvalidArgumentException(sprintf('Invalid DSN "%s".', $dsn));
        }

        $scheme = $parsed['scheme'];
        if (!in_array($scheme, $this->getSupportedSchemes(), true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported scheme "%s". Expected: %s.', $scheme, implode(', ', $this->getSupportedSchemes())));
        }

        // Build the full URL preserving path, query, fragment
        $url = $scheme . '://' . $parsed['host'];
        if (isset($parsed['port'])) {
            $defaultPort = 'https' === $scheme ? 443 : 80;
            if ($parsed['port'] !== $defaultPort) {
                $url .= ':' . $parsed['port'];
            }
        }
        if (isset($parsed['path'])) {
            $url .= $parsed['path'];
        }
        if (isset($parsed['query'])) {
            $url .= '?' . $parsed['query'];
        }
        if (isset($parsed['fragment'])) {
            $url .= '#' . $parsed['fragment'];
        }

        // Parse auth from userinfo
        $auth = ['type' => 'none'];
        if (isset($parsed['user'])) {
            $user = $parsed['user'];
            $pass = $parsed['pass'] ?? null;

            if ($pass !== null) {
                $auth = ['type' => 'basic', 'username' => $user, 'password' => $pass];
            } else {
                $auth = ['type' => 'bearer', 'token' => $user];
            }
        }

        return new N8nTransport($url, $this->httpClient, $auth);
    }

    /**
     * @return list<string>
     */
    protected function getSupportedSchemes(): array
    {
        return ['https', 'http'];
    }

    /**
     * @return array{type: string, username?: string, password?: string, token?: string}
     */
    private function parseAuth(Dsn $dsn): array
    {
        $user = $dsn->getUser();
        $password = $dsn->getPassword();

        if ($user !== null && $password !== null) {
            return ['type' => 'basic', 'username' => $user, 'password' => $password];
        }

        if ($user !== null) {
            return ['type' => 'bearer', 'token' => $user];
        }

        return ['type' => 'none'];
    }
}
