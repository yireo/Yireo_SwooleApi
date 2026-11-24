<?php

declare(strict_types=1);

namespace Yireo\SwooleApi\Http;

class ServerConfig
{
    public function __construct(
        private readonly array $env = [],
    ) {
    }

    public function isDebug(): bool
    {
        return in_array(strtolower($this->getValue('SWOOLE_API_DEBUG')), ['1', 'true', 'yes', 'on'], true);
    }

    public function getHost(): string
    {
        $host = $this->getValue('SWOOLE_API_HOST');
        if (filter_var($host, FILTER_VALIDATE_IP) === false) {
            return '127.0.0.1';
        }

        return $host;
    }

    public function getPort(): int
    {
        return $this->getInt('SWOOLE_API_PORT', 4000, 1, 65535);
    }

    public function getTrustedProxies(): array
    {
        $value = $this->getValue('SWOOLE_API_TRUSTED_PROXIES');
        if ($value === '') {
            return ['127.0.0.1', '::1'];
        }

        $proxies = array_map(fn ($proxy) => trim($proxy, " \f\n\r\t\v\x00"), explode(',', $value));

        return array_values(array_filter($proxies, fn ($proxy) => filter_var($proxy, FILTER_VALIDATE_IP) !== false));
    }

    private function getInt(string $name, int $default, int $min, int $max): int
    {
        $value = filter_var(
            $this->getValue($name),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => $min, 'max_range' => $max]]
        );

        return $value === false ? $default : $value;
    }

    private function getValue(string $name): string
    {
        return trim((string)($this->env[$name] ?? ''), " \f\n\r\t\v\x00");
    }
}
