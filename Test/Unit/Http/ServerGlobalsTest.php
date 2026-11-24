<?php

declare(strict_types=1);

namespace Yireo\SwooleApi\Test\Unit\Http;

use PHPUnit\Framework\TestCase;
use Yireo\SwooleApi\Http\ServerGlobals;

class ServerGlobalsTest extends TestCase
{
    public function testHeadersOfPreviousRequestDoNotLeak(): void
    {
        $serverGlobals = new ServerGlobals(['PATH' => '/usr/bin', 'HTTP_AUTHORIZATION' => 'Bearer boot']);

        $first = $serverGlobals->build(['authorization' => 'Bearer secret', 'host' => 'example.com'], []);
        $this->assertSame('Bearer secret', $first['HTTP_AUTHORIZATION']);

        $second = $serverGlobals->build(['host' => 'example.com'], []);
        $this->assertArrayNotHasKey('HTTP_AUTHORIZATION', $second);
        $this->assertSame('/usr/bin', $second['PATH']);
    }

    public function testRequestValuesAreTakenFromSwooleServer(): void
    {
        $result = (new ServerGlobals([]))->build(
            ['content-type' => 'application/json', 'host' => 'example.com'],
            [
                'remote_addr' => '203.0.113.7',
                'server_port' => 4000,
                'request_uri' => '/graphql',
                'query_string' => 'query=foo',
                'request_method' => 'post',
            ]
        );

        $this->assertSame('203.0.113.7', $result['REMOTE_ADDR']);
        $this->assertSame(4000, $result['SERVER_PORT']);
        $this->assertSame('/graphql?query=foo', $result['REQUEST_URI']);
        $this->assertSame('POST', $result['REQUEST_METHOD']);
        $this->assertSame('application/json', $result['CONTENT_TYPE']);
        $this->assertSame('example.com', $result['HTTP_HOST']);
        $this->assertArrayNotHasKey('HTTPS', $result);
    }

    public function testMissingValuesFallBackToDefaults(): void
    {
        $result = (new ServerGlobals(['HTTPS' => 'on', 'REMOTE_ADDR' => '10.0.0.1']))->build([], []);

        $this->assertSame('', $result['REMOTE_ADDR']);
        $this->assertSame(80, $result['SERVER_PORT']);
        $this->assertSame('/', $result['REQUEST_URI']);
        $this->assertSame('GET', $result['REQUEST_METHOD']);
        $this->assertArrayNotHasKey('HTTPS', $result);
        $this->assertArrayNotHasKey('HTTP_HOST', $result);
    }

    public function testInvalidHeaderNamesAreSkipped(): void
    {
        $result = (new ServerGlobals([]))->build(['x-foo bar' => 'a', 'x.evil' => 'b', 'x-good-1' => 'c'], []);

        $this->assertSame('c', $result['HTTP_X_GOOD_1']);
        $this->assertCount(1, array_filter(array_keys($result), fn ($key) => str_starts_with($key, 'HTTP_')));
    }

    public function testHttpsIsOnlyTrustedFromTrustedProxy(): void
    {
        $serverGlobals = new ServerGlobals([], ['127.0.0.1']);
        $headers = ['x-forwarded-proto' => 'https'];

        $trusted = $serverGlobals->build($headers, ['remote_addr' => '127.0.0.1']);
        $this->assertSame('on', $trusted['HTTPS']);

        $untrusted = $serverGlobals->build($headers, ['remote_addr' => '203.0.113.7']);
        $this->assertArrayNotHasKey('HTTPS', $untrusted);
    }
}
