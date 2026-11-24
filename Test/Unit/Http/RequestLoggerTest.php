<?php

declare(strict_types=1);

namespace Yireo\SwooleApi\Test\Unit\Http;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yireo\SwooleApi\Http\RequestLogger;

class RequestLoggerTest extends TestCase
{
    private string $logFile;

    protected function setUp(): void
    {
        $this->logFile = (string)tempnam(sys_get_temp_dir(), 'swoole-api-log');
    }

    protected function tearDown(): void
    {
        @unlink($this->logFile);
    }

    public function testRequestIsNotLoggedWithoutDebug(): void
    {
        (new RequestLogger($this->logFile))->logRequest('{"query":"mutation { x }"}', 200, 0.1);

        $this->assertSame('', file_get_contents($this->logFile));
    }

    public function testRequestBodyIsNeverLogged(): void
    {
        $body = '{"query":"mutation generateToken { generateCustomerToken(email: \"a@b.c\", password: \"Secret123\") { token } }"}';
        (new RequestLogger($this->logFile, true))->logRequest($body, 200, 0.0123);

        $contents = (string)file_get_contents($this->logFile);
        $this->assertStringContainsString('operation=generateToken', $contents);
        $this->assertStringContainsString('length='.strlen($body), $contents);
        $this->assertStringContainsString('status=200', $contents);
        $this->assertStringNotContainsString('Secret123', $contents);
        $this->assertStringNotContainsString('a@b.c', $contents);
    }

    public function testLogMessageCannotInjectNewLines(): void
    {
        (new RequestLogger($this->logFile))->log("foo\nFAKE ENTRY\r\nbar");

        $contents = (string)file_get_contents($this->logFile);
        $this->assertSame(1, substr_count($contents, "\n"));
    }

    public function testFailingLogWriteDoesNotThrow(): void
    {
        set_error_handler(function (int $errno, string $errstr): bool {
            throw new \ErrorException($errstr, 0, $errno);
        });

        try {
            (new RequestLogger('/nonexistent-dir/swoole.log'))->log('message');
        } finally {
            restore_error_handler();
        }

        $this->addToAssertionCount(1);
    }

    #[DataProvider('getOperationNameProvider')]
    public function testGetOperationName(string $body, string $expected): void
    {
        $this->assertSame($expected, (new RequestLogger($this->logFile))->getOperationName($body));
    }

    public static function getOperationNameProvider(): array
    {
        return [
            ['{"operationName":"getProducts","query":"query getProducts { a }"}', 'getProducts'],
            ['{"query":"query getProducts { products { id } }"}', 'getProducts'],
            ['{"query":"  mutation placeOrder($a: String) { a }"}', 'placeOrder'],
            ['{"query":"{ products { id } }"}', '-'],
            ['{"operationName":"evil\nname"}', '-'],
            ['{"operationName":["array"]}', '-'],
            ['{"operationName":"'.str_repeat('a', 101).'"}', '-'],
            ['not json', '-'],
            ['', '-'],
        ];
    }
}
