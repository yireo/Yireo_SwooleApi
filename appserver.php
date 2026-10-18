<?php
declare(strict_types=1);

use Magento\Framework\App\Bootstrap;
use Yireo\SwooleApi\Http\AppServer;

require_once __DIR__ . '/app/bootstrap.php'; // phpcs:ignore Magento2.Security.IncludeFile

$bootstrap = Bootstrap::create(__DIR__, $_SERVER); // phpcs:ignore Magento2.Security.Superglobal
$bootstrap->getObjectManager()->get(AppServer::class)->run($bootstrap);
