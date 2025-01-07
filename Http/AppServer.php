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
    public function __construct(
        private DirectoryList $directory,
    ) {
    }

    public function run(Bootstrap $bootstrap)
    {
        $host = '0.0.0.0';
        $port = 4000;

        $this->log('Starting server on '.$host.' port '.$port);
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
            'log_file' => $this->directory->getPath('log').'/swoole.log',
        ]);

        $this->initializeApplication($bootstrap);
        $frontController = $bootstrap->getObjectManager()->get(FrontControllerInterface::class);

        $http->on(
            "request",
            function (SwooleRequest $request, SwooleResponse $response) use ($bootstrap, $frontController) {
                foreach ($request->header as $headerName => $headerValue) {
                    $headerName = strtoupper(str_replace('-', '_', $headerName));
                    $_SERVER['HTTP_'.$headerName] = $headerValue;
                }

                $json = $request->getContent();
                if (false === json_validate($json)) {
                    $this->log('Invalid JSON: '.$json);
                    $response->status(500);
                    $response->end('Invalid JSON request');

                    return;
                }

                $this->log('Responding to request '.$request->getContent());
                $this->initializePhpGlobals($request, $response);
                $magentoRequest = $this->initializeRequest($bootstrap, $request);

                try {
                    $magentoResponse = $frontController->dispatch($magentoRequest);
                } catch (Throwable $error) {
                    $contents = json_encode(['errors' => [$error->getMessage()]]);
                    $response->status(500);
                    $response->end($contents);

                    return;
                }

                /** @var Response $magentoResponse */
                if ($magentoResponse->getHeaders()) {
                    foreach ($magentoResponse->getHeaders() as $header) {
                        $response->setHeader($header->getFieldName(), $header->getFieldValue());
                    }
                } else {
                    $this->log('No headers');
                }

                $response->status($magentoResponse->getStatusCode());
                $response->write($magentoResponse->getBody());
                $response->end();
            }
        );

        $http->start();
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

    private function initializePhpGlobals(SwooleRequest $request, SwooleResponse $response)
    {
        $_SERVER['HTTP_HOST'] = $request->header['host'];
        $_SERVER['SERVER_PORT'] = 80;
        $_SERVER['REQUEST_URI'] = $request->server['request_uri'];
        $_SERVER['REQUEST_METHOD'] = $request->server['request_method'];

        if (!empty($request->header['content-type'])) {
            $_SERVER['REDIRECT_HTTP_CONTENT_TYPE'] = $request->header['content-type'];
        } else {
            unset($_SERVER['REDIRECT_HTTP_CONTENT_TYPE']);
        }

        if (!empty($request->header['x-requested-with'])) {
            $_SERVER['HTTP_X_REQUESTED_WITH'] = $request->header['x-requested-with'];
        } else {
            unset($_SERVER['HTTP_X_REQUESTED_WITH']);
        }

        $_GET = $request->get;
        $_POST = $request->post;
        $_COOKIE = $request->cookie;

        $GLOBALS['HTTP_RAW_POST_DATA'] = $request->getContent();
    }

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

    private function log(string $message)
    {
        $logFile = $this->directory->getRoot().'/var/log/swoole.log';
        file_put_contents($logFile, $message."\n", FILE_APPEND);
    }
}
