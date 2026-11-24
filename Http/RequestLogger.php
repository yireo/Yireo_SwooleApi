<?php

declare(strict_types=1);

namespace Yireo\SwooleApi\Http;

use Throwable;

class RequestLogger
{
    private const OPERATION_NAME_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]{0,99}$/';
    private const QUERY_NAME_PATTERN = '/^\s*(?:query|mutation|subscription)\s+([A-Za-z_][A-Za-z0-9_]*)/';

    public function __construct(
        private readonly string $logFile,
        private readonly bool $debug = false,
    ) {
    }

    public function log(string $message): void
    {
        $message = str_replace(["\r", "\n"], ' ', $message);
        try {
            // phpcs:ignore Magento2.Functions.DiscouragedFunction
            file_put_contents($this->logFile, date('c').' '.$message."\n", FILE_APPEND | LOCK_EX);
        } catch (Throwable) {
            return;
        }
    }

    public function logRequest(string $body, int $status, float $duration): void
    {
        if (!$this->debug) {
            return;
        }

        $this->log(sprintf(
            'Request operation=%s length=%d status=%d duration=%.1fms',
            $this->getOperationName($body),
            strlen($body),
            $status,
            $duration * 1000
        ));
    }

    public function getOperationName(string $body): string
    {
        $data = json_decode($body, true);
        if (!is_array($data)) {
            return '-';
        }

        $operationName = $data['operationName'] ?? null;
        if (empty($operationName) && is_string($data['query'] ?? null)
            && preg_match(self::QUERY_NAME_PATTERN, $data['query'], $match)) {
            $operationName = $match[1];
        }

        if (!is_string($operationName) || !preg_match(self::OPERATION_NAME_PATTERN, $operationName)) {
            return '-';
        }

        return $operationName;
    }
}
