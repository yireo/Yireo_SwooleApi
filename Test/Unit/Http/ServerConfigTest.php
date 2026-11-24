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
}
