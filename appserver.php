<?php
declare(strict_types=1);

use Magento\Framework\App\Bootstrap;
use Yireo\SwooleApi\Http\AppServer;

require_once __DIR__ . '/app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$bootstrap->getObjectManager()->get(AppServer::class)->run($bootstrap);
