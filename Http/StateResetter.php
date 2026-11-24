<?php

declare(strict_types=1);

namespace Yireo\SwooleApi\Http;

use Closure;
use Magento\Framework\ObjectManager\Resetter\ResetterFactory;
use Magento\Framework\ObjectManager\Resetter\ResetterInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Api\StoreRepositoryInterface;

class StateResetter
{
    private ?ResetterInterface $resetter = null;

    public function __construct(
        private readonly ObjectManagerInterface $objectManager,
    ) {
    }

    public function reset(): void
    {
        $resetter = $this->getResetter();
        foreach ($this->getSharedInstances() as $instance) {
            if (!is_object($instance) || $instance === $this->objectManager || $instance === $this) {
                continue;
            }

            if ($instance instanceof StoreRepositoryInterface) {
                $instance->clean();
            }

            $resetter->addInstance($instance);
        }

        $resetter->_resetState();
    }

    private function getResetter(): ResetterInterface
    {
        if ($this->resetter === null) {
            $this->resetter = ResetterFactory::create();
            $this->resetter->setObjectManager($this->objectManager);
        }

        return $this->resetter;
    }

    private function getSharedInstances(): array
    {
        $reader = Closure::bind(
            fn () => $this->_sharedInstances ?? [],
            $this->objectManager,
            $this->objectManager::class
        );

        return (array)$reader();
    }
}
