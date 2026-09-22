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

        if ('n8n+https' !== $scheme && 'n8n+http' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'n8n', $this->getSupportedSchemes());
        }

        $protocol = str_contains($scheme, 'https') ? 'https' : 'http';
        $host = $dsn->getHost();
        $port = $dsn->getPort();
        $path = $dsn->getPath();

        $url = sprintf('%s://%s', $protocol, $host);
        if ($port !== null && $port !== 80 && $port !== 443) {
            $url .= sprintf(':%d', $port);
        }
        $url .= $path;

        $auth = $this->parseAuthFromDsn($dsn);

        return new N8nTransport($url, $this->httpClient, $auth);
    }

    protected function getSupportedSchemes(): array
    {
        return ['n8n+https', 'n8n+http'];
    }

    /**
     * @return array{type: string, username?: string, password?: string, header?: string, token?: string}
     */
    private function parseAuthFromDsn(Dsn $dsn): array
    {
        $user = $dsn->getUser();
        $password = $dsn->getPassword();

        if ($user !== null && $password !== null) {
            return ['type' => 'basic', 'username' => $user, 'password' => $password];
        }

        if ($user !== null) {
            return ['type' => 'jwt', 'token' => $user];
        }

        return ['type' => 'none'];
    }
}
