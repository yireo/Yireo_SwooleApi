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

    private function getValue(string $name): string
    {
        return trim((string)($this->env[$name] ?? ''), " \f\n\r\t\v\x00");
    }
}
