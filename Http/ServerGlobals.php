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

    private const FORWARDING_HEADERS = [
        'forwarded',
        'client-ip',
        'x-client-ip',
        'x-real-ip',
        'x-cluster-client-ip',
        'true-client-ip',
        'cf-connecting-ip',
        'ssl-offloaded',
        'front-end-https',
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

    public function filterHeaders(array $headers, string $remoteAddress): array
    {
        if ($this->isTrustedProxy($remoteAddress)) {
            return $headers;
        }

        return array_filter(
            $headers,
            fn ($name) => !$this->isForwardingHeader(strtolower((string)$name)),
            ARRAY_FILTER_USE_KEY
        );
    }

    public function build(array $headers, array $server): array
    {
        $result = $this->baseServer;
        $headers = $this->filterHeaders($headers, (string)($server['remote_addr'] ?? ''));

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
        if (!$this->isTrustedProxy($remoteAddress)) {
            return false;
        }

        return strtolower((string)($headers['x-forwarded-proto'] ?? '')) === 'https';
    }

    private function isTrustedProxy(string $remoteAddress): bool
    {
        return in_array($remoteAddress, $this->trustedProxies, true);
    }

    private function isForwardingHeader(string $name): bool
    {
        return str_starts_with($name, 'x-forwarded-') || in_array($name, self::FORWARDING_HEADERS, true);
    }

    private function isRequestKey(string $key): bool
    {
        return in_array($key, self::REQUEST_KEYS, true)
            || str_starts_with($key, 'HTTP_')
            || str_starts_with($key, 'REDIRECT_HTTP_');
    }
}
