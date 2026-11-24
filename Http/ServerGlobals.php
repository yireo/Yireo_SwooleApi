<?php

declare(strict_types=1);

namespace Yireo\SwooleApi\Http;

class ServerGlobals
{
    private const REQUEST_KEYS = [
        'HTTPS',
        'REMOTE_ADDR',
        'SERVER_PORT',
        'REQUEST_URI',
        'REQUEST_METHOD',
        'QUERY_STRING',
        'CONTENT_TYPE',
        'CONTENT_LENGTH',
    ];

    private readonly array $baseServer;

    public function __construct(
        array $baseServer,
        private readonly array $trustedProxies = ['127.0.0.1', '::1'],
    ) {
        $this->baseServer = array_filter(
            $baseServer,
            fn ($key) => !$this->isRequestKey((string)$key),
            ARRAY_FILTER_USE_KEY
        );
    }

    public function build(array $headers, array $server): array
    {
        $result = $this->baseServer;

        foreach ($headers as $name => $value) {
            $name = strtoupper(str_replace('-', '_', (string)$name));
            if (!preg_match('/^[A-Z0-9_]+$/', $name)) {
                continue;
            }

            $result['HTTP_'.$name] = (string)$value;
        }

        if (isset($headers['content-type'])) {
            $result['CONTENT_TYPE'] = (string)$headers['content-type'];
            $result['REDIRECT_HTTP_CONTENT_TYPE'] = (string)$headers['content-type'];
        }

        if (isset($headers['content-length'])) {
            $result['CONTENT_LENGTH'] = (string)$headers['content-length'];
        }

        $queryString = (string)($server['query_string'] ?? '');
        $requestUri = (string)($server['request_uri'] ?? '/');
        $result['REQUEST_URI'] = $queryString !== '' ? $requestUri.'?'.$queryString : $requestUri;
        $result['QUERY_STRING'] = $queryString;
        $result['REQUEST_METHOD'] = strtoupper((string)($server['request_method'] ?? 'GET'));
        $result['REMOTE_ADDR'] = (string)($server['remote_addr'] ?? '');
        $result['SERVER_PORT'] = (int)($server['server_port'] ?? 80);

        if ($this->isSecure($headers, $result['REMOTE_ADDR'])) {
            $result['HTTPS'] = 'on';
        }

        return $result;
    }

    private function isSecure(array $headers, string $remoteAddress): bool
    {
        if (!in_array($remoteAddress, $this->trustedProxies, true)) {
            return false;
        }

        return strtolower((string)($headers['x-forwarded-proto'] ?? '')) === 'https';
    }

    private function isRequestKey(string $key): bool
    {
        return in_array($key, self::REQUEST_KEYS, true)
            || str_starts_with($key, 'HTTP_')
            || str_starts_with($key, 'REDIRECT_HTTP_');
    }
}
