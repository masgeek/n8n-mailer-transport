<?php

namespace Masgeek\N8nMailer;

use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class N8nTransportFactory extends AbstractTransportFactory
{
    private HttpClientInterface $httpClient;

    public function __construct(HttpClientInterface $httpClient)
    {
        parent::__construct();
        $this->httpClient = $httpClient;
    }

    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();

        if ('https' !== $scheme && 'http' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'n8n', $this->getSupportedSchemes());
        }

        $host = $dsn->getHost();
        $port = $dsn->getPort();

        $url = sprintf('%s://%s', $scheme, $host);
        if ($port !== null && $port !== 80 && $port !== 443) {
            $url .= sprintf(':%d', $port);
        }

        $auth = $this->parseAuthFromDsn($dsn);

        return new N8nTransport($url, $this->httpClient, $auth);
    }

    public function createFromString(string $dsn): TransportInterface
    {
        $parsed = parse_url($dsn);
        if ($parsed === false || !isset($parsed['scheme'], $parsed['host'])) {
            throw new \InvalidArgumentException(sprintf('Invalid DSN: %s', $dsn));
        }

        $scheme = $parsed['scheme'];
        if ('https' !== $scheme && 'http' !== $scheme) {
            throw new \InvalidArgumentException(sprintf('Unsupported scheme "%s". Use https or http.', $scheme));
        }

        $host = $parsed['host'];
        $port = $parsed['port'] ?? null;
        $path = $parsed['path'] ?? '';
        $user = isset($parsed['user']) ? rawurldecode($parsed['user']) : null;
        $password = isset($parsed['pass']) ? rawurldecode($parsed['pass']) : null;

        $url = sprintf('%s://%s', $scheme, $host);
        if ($port !== null && $port !== 80 && $port !== 443) {
            $url .= sprintf(':%d', $port);
        }
        $url .= $path;

        $auth = $this->parseAuthFromCredentials($user, $password);

        return new N8nTransport($url, $this->httpClient, $auth);
    }

    protected function getSupportedSchemes(): array
    {
        return ['https', 'http'];
    }

    /**
     * @return array{type: string, username?: string, password?: string, header?: string, token?: string}
     */
    private function parseAuthFromDsn(Dsn $dsn): array
    {
        return $this->parseAuthFromCredentials($dsn->getUser(), $dsn->getPassword());
    }

    /**
     * @return array{type: string, username?: string, password?: string, header?: string, token?: string}
     */
    private function parseAuthFromCredentials(?string $user, ?string $password): array
    {
        if ($user !== null && $password !== null) {
            return ['type' => 'basic', 'username' => $user, 'password' => $password];
        }

        if ($user !== null) {
            return ['type' => 'jwt', 'token' => $user];
        }

        return ['type' => 'none'];
    }
}
