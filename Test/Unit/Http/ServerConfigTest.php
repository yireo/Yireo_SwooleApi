<?php

declare(strict_types=1);

namespace Yireo\SwooleApi\Test\Unit\Http;

use PHPUnit\Framework\TestCase;
use Yireo\SwooleApi\Http\ServerConfig;

class ServerConfigTest extends TestCase
{
    public function testDebugIsDisabledByDefault(): void
    {
        $this->assertFalse((new ServerConfig([]))->isDebug());
        $this->assertFalse((new ServerConfig(['SWOOLE_API_DEBUG' => '0']))->isDebug());
    }

    public function testDebugCanBeEnabled(): void
    {
        $this->assertTrue((new ServerConfig(['SWOOLE_API_DEBUG' => '1']))->isDebug());
        $this->assertTrue((new ServerConfig(['SWOOLE_API_DEBUG' => ' TRUE ']))->isDebug());
    }

    public function testHostAndPortDefaultToLocalhost(): void
    {
        $config = new ServerConfig([]);
        $this->assertSame('127.0.0.1', $config->getHost());
        $this->assertSame(4000, $config->getPort());
    }

    public function testHostAndPortCanBeConfigured(): void
    {
        $config = new ServerConfig(['SWOOLE_API_HOST' => '0.0.0.0', 'SWOOLE_API_PORT' => '9501']);
        $this->assertSame('0.0.0.0', $config->getHost());
        $this->assertSame(9501, $config->getPort());
    }

    public function testInvalidHostAndPortFallBackToDefaults(): void
    {
        $config = new ServerConfig(['SWOOLE_API_HOST' => 'not an ip', 'SWOOLE_API_PORT' => '70000']);
        $this->assertSame('127.0.0.1', $config->getHost());
        $this->assertSame(4000, $config->getPort());

        $this->assertSame(4000, (new ServerConfig(['SWOOLE_API_PORT' => 'abc']))->getPort());
    }

    public function testTrustedProxies(): void
    {
        $this->assertSame(['127.0.0.1', '::1'], (new ServerConfig([]))->getTrustedProxies());

        $config = new ServerConfig(['SWOOLE_API_TRUSTED_PROXIES' => '10.0.0.1, bogus ,10.0.0.2']);
        $this->assertSame(['10.0.0.1', '10.0.0.2'], $config->getTrustedProxies());
    }

    public function testLimitsHaveSafeDefaults(): void
    {
        $config = new ServerConfig([]);
        $this->assertSame(500, $config->getMaxRequest());
        $this->assertSame(1048576, $config->getPackageMaxLength());
    }

    public function testLimitsCanBeConfigured(): void
    {
        $config = new ServerConfig(['SWOOLE_API_MAX_REQUEST' => '100', 'SWOOLE_API_PACKAGE_MAX_LENGTH' => '2097152']);
        $this->assertSame(100, $config->getMaxRequest());
        $this->assertSame(2097152, $config->getPackageMaxLength());
    }

    public function testInvalidLimitsFallBackToDefaults(): void
    {
        $config = new ServerConfig(['SWOOLE_API_MAX_REQUEST' => '0', 'SWOOLE_API_PACKAGE_MAX_LENGTH' => '999999999999']);
        $this->assertSame(500, $config->getMaxRequest());
        $this->assertSame(1048576, $config->getPackageMaxLength());
    }
}
