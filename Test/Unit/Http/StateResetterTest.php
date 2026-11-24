<?php

declare(strict_types=1);

namespace Yireo\SwooleApi\Test\Unit\Http;

use Magento\Framework\HTTP\PhpEnvironment\Response;
use Magento\Framework\ObjectManager\ConfigInterface;
use Magento\Framework\ObjectManager\FactoryInterface;
use Magento\Framework\ObjectManager\ObjectManager;
use Magento\Framework\ObjectManager\Resetter\WeakMapSorter;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Webapi\Model\Authorization\TokenUserContext;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Yireo\SwooleApi\Http\StateResetter;

class StateResetterTest extends TestCase
{
    protected function setUp(): void
    {
        if (!defined('BP')) {
            define('BP', sys_get_temp_dir());
        }
    }

    public function testCachedCustomerIdentityIsCleared(): void
    {
        $tokenUserContext = (new ReflectionClass(TokenUserContext::class))->newInstanceWithoutConstructor();
        $this->setProperty($tokenUserContext, 'isRequestProcessed', true);
        $this->setProperty($tokenUserContext, 'userId', 42);
        $this->setProperty($tokenUserContext, 'userType', 3);

        $objectManager = $this->createObjectManager([TokenUserContext::class => $tokenUserContext]);
        (new StateResetter($objectManager))->reset();

        $this->assertNull($this->getProperty($tokenUserContext, 'isRequestProcessed'));
        $this->assertNull($this->getProperty($tokenUserContext, 'userId'));
        $this->assertNull($this->getProperty($tokenUserContext, 'userType'));
    }

    public function testResponseHeadersAndStatusAreCleared(): void
    {
        $response = new Response();
        $response->setHeader('X-Magento-Tags', 'customer_1');
        $response->setHttpResponseCode(403);

        $objectManager = $this->createObjectManager([Response::class => $response]);
        $stateResetter = new StateResetter($objectManager);
        $stateResetter->reset();

        $this->assertFalse($response->getHeader('X-Magento-Tags'));
        $this->assertSame(200, $response->getStatusCode());

        $response->setHeader('X-Magento-Tags', 'customer_2');
        $stateResetter->reset();
        $this->assertFalse($response->getHeader('X-Magento-Tags'));
    }

    public function testCachedStoresAreCleaned(): void
    {
        $storeRepository = $this->createMock(StoreRepositoryInterface::class);
        $storeRepository->expects($this->once())->method('clean');

        $objectManager = $this->createObjectManager([StoreRepositoryInterface::class => $storeRepository]);
        (new StateResetter($objectManager))->reset();
    }

    private function createObjectManager(array $sharedInstances): ObjectManager
    {
        $config = $this->createMock(ConfigInterface::class);
        $config->method('getPreference')->willReturnArgument(0);
        $sharedInstances[WeakMapSorter::class] = new WeakMapSorter([]);

        return new ObjectManager($this->createMock(FactoryInterface::class), $config, $sharedInstances);
    }

    private function setProperty(object $object, string $name, mixed $value): void
    {
        $property = (new ReflectionClass($object))->getProperty($name);
        $property->setValue($object, $value);
    }

    private function getProperty(object $object, string $name): mixed
    {
        $property = (new ReflectionClass($object))->getProperty($name);

        return $property->getValue($object);
    }
}
