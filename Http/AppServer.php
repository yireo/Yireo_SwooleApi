<?php

declare(strict_types=1);

namespace Yireo\SwooleApi\Http;

use Laminas\Http\Header\GenericHeader;
use Laminas\Http\Headers;
use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\ErrorHandler;
use Magento\Framework\App\FrontControllerInterface;
use Magento\Framework\App\Http;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\Request\Http as Request;
use Magento\Framework\App\State;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\GraphQl\Query\Fields;
use Magento\Framework\HTTP\PhpEnvironment\Response;
use Magento\Framework\ObjectManager\ConfigLoaderInterface;
use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;
use Swoole\Http\Server as SwooleServer;
use Throwable;

class AppServer
{
    private ServerGlobals $serverGlobals;

    private RequestLogger $logger;

    public function __construct(
        private DirectoryList $directory,
        private StateResetter $stateResetter,
    ) {
    }

    public function run(Bootstrap $bootstrap)
    {
        $config = new ServerConfig(getenv()); // phpcs:ignore Magento2.Functions.DiscouragedFunction
        $logFile = $this->directory->getPath('log').'/swoole.log';
        $this->logger = new RequestLogger($logFile, $config->isDebug());

        $host = $config->getHost();
        $port = $config->getPort();

        $this->logger->log('Starting server on '.$host.' port '.$port);
        $http = new SwooleServer($host, $port, SWOOLE_BASE, SWOOLE_SOCK_TCP);
        $http->set([
            //'daemonize' => 1,
            //'reactor_num' => 24,
            //'dispatch_mode' => 3,
            //'task_worker_num' => 1,
            //'upload_max_filesize' => 1 * 1024 * 1024 * 1024,
            //'package_max_length' => 1 * 1024 * 1024,
            //'open_tcp_nodelay' => true,
            'http_compression' => false,
            'tcp_fastopen' => true,
            'log_level' => SWOOLE_LOG_ERROR,
            'log_file' => $logFile,
        ]);

        // phpcs:ignore Magento2.Security.Superglobal
        $this->serverGlobals = new ServerGlobals($_SERVER, $config->getTrustedProxies());
        $this->initializeApplication($bootstrap);
        $frontController = $bootstrap->getObjectManager()->get(FrontControllerInterface::class);

        $http->on(
            "request",
            function (SwooleRequest $request, SwooleResponse $response) use ($bootstrap, $frontController) {
                $startTime = microtime(true);
                $json = (string)$request->getContent();
                json_decode($json);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $response->status(500);
                    $response->end('Invalid JSON request');
                    $this->logger->logRequest($json, 500, microtime(true) - $startTime);

                    return;
                }

                $status = 500;
                try {
                    $status = $this->handleRequest($bootstrap, $frontController, $request, $response);
                } finally {
                    $this->stateResetter->reset();
                    $this->logger->logRequest($json, $status, microtime(true) - $startTime);
                }
            }
        );

        $http->start();
    }

    private function handleRequest(
        Bootstrap $bootstrap,
        FrontControllerInterface $frontController,
        SwooleRequest $request,
        SwooleResponse $response
    ): int {
        $this->initializePhpGlobals($request);
        $magentoRequest = $this->initializeRequest($bootstrap, $request);

        try {
            $magentoResponse = $frontController->dispatch($magentoRequest);
        } catch (Throwable $error) {
            $contents = json_encode(['errors' => [$error->getMessage()]]);
            $response->status(500);
            $response->end($contents);

            return 500;
        }

        /** @var Response $magentoResponse */
        if ($magentoResponse->getHeaders()) {
            foreach ($magentoResponse->getHeaders() as $header) {
                $response->setHeader($header->getFieldName(), $header->getFieldValue());
            }
        }

        $response->status($magentoResponse->getStatusCode());
        $response->write($magentoResponse->getBody());
        $response->end();

        return (int)$magentoResponse->getStatusCode();
    }

    private function initializeRequest(Bootstrap $bootstrap, SwooleRequest $request): Request
    {
        $newRequest = $bootstrap->getObjectManager()->create(Request::class);
        $httpRequest = $bootstrap->getObjectManager()->get(Request::class);
        $httpRequest->setServer($newRequest->getServer());
        $httpRequest->setRequestUri($newRequest->getRequestUri());
        $httpRequest->setUri($newRequest->getUri());
        $httpRequest->setPathInfo($newRequest->getPathInfo());

        $headers = new Headers;
        foreach ($request->header as $fieldName => $fieldValue) {
            $headers->addHeader(new GenericHeader($fieldName, $fieldValue));
        }

        $httpRequest->setHeaders($headers);
        $httpRequest->setMethod($newRequest->getMethod());
        $httpRequest->setParams($newRequest->getParams());
        $httpRequest->setContent($request->getContent());
        $httpRequest->setPost($newRequest->getPost());

        return $httpRequest;
    }

    // phpcs:disable Magento2.Security.Superglobal
    private function initializePhpGlobals(SwooleRequest $request): void
    {
        $_SERVER = $this->serverGlobals->build($request->header ?? [], $request->server ?? []);
        $_GET = $request->get ?? [];
        $_POST = $request->post ?? [];
        $_COOKIE = $request->cookie ?? [];
        $_FILES = [];

        $GLOBALS['HTTP_RAW_POST_DATA'] = $request->getContent();
    }
    // phpcs:enable Magento2.Security.Superglobal

    private function initializeApplication(Bootstrap $bootstrap)
    {
        $bootstrap->createApplication(Http::class);
        $objectManager = $bootstrap->getObjectManager();

        $handler = new ErrorHandler();
        set_error_handler([$handler, 'handler']);

        $areaCode = 'graphql';
        $objectManager->get(State::class)->setAreaCode($areaCode);
        $configLoader = $objectManager->get(ConfigLoaderInterface::class);
        $objectManager->configure($configLoader->load($areaCode));

        $objectManager->configure([Fields::class => ['shared' => false]]);
    }
}
