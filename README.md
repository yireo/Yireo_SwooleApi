# Yireo SwooleApi

<!-- badges.specs.start -->
![Magento version](https://img.shields.io/badge/Magento-2.4.6%20%7C%202.4.9-orange)
![PHP version](https://img.shields.io/badge/PHP-8.2%E2%80%938.5-777BB4)
![License](https://img.shields.io/badge/License-OSL--3.0-blue)
![Latest Version](https://img.shields.io/packagist/v/yireo/magento2-swoole-api)
<!-- badges.specs.end -->


**Current status: Experimental. If you are not contributing, support requests are not appreciated.**

### Forked from `Swoolegento_Cli`
This was forked from [Swoolegento_Cli](https://github.com/swoolegento/magento2-module). PHP 8.3 has been added. Blackfire probing in the code has been removed. Upgrade commands to Symfony 6. Refactor main CLI command. Remove everything but support for GraphQL.

### Installation
Make sure you have Swoole installed into PHP.

```bash
composer require yireo/magento2-swoole-api:@dev
bin/magento module:enable Yireo_SwooleApi
cp vendor/yireo/magento2-swoole-api/appserver.php .
php ./appserver.php
```

### Configuration
The server is configured with environment variables:

| Variable | Default | Description |
|----------|---------|-------------|
| `SWOOLE_API_HOST` | `127.0.0.1` | IP address to listen on |
| `SWOOLE_API_PORT` | `4000` | Port to listen on |
| `SWOOLE_API_TRUSTED_PROXIES` | `127.0.0.1,::1` | Comma-separated IPs of reverse proxies whose `X-Forwarded-Proto` header is trusted |
| `SWOOLE_API_DEBUG` | `0` | Log metadata (operation name, size, status, duration) of every request to `var/log/swoole.log` |

The server speaks plain HTTP. Do not expose it publicly: run it behind a reverse proxy (like Nginx) that terminates TLS
and applies rate limiting.

### Benchmarks
Original Magento GraphQL request (150ms-500ms):
```bash
time curl -S http://magento/graphql -H 'Content-Type: application/json' -d '{"query": "query {products {id}}"}'
```

Swoole GraphQL request with Swoole running on port 4000 (100ms-350ms):
```bash
time curl -S http://magento:4000/graphql -H 'Content-Type: application/json' -d '{"query": "query {products {id}}"}'
```


## Current status

<!-- badges.test.start -->
![Static Tests](https://img.shields.io/github/actions/workflow/status/yireo/Yireo_SwooleApi/static-tests.yml?label=static-tests)
![Unit Tests](https://img.shields.io/github/actions/workflow/status/yireo/Yireo_SwooleApi/unit-tests.yml?label=unit-tests)
![Integration Tests](https://img.shields.io/github/actions/workflow/status/yireo/Yireo_SwooleApi/integration-tests.yml?label=integration-tests)
![Playwright](https://img.shields.io/github/actions/workflow/status/yireo/Yireo_SwooleApi/playwright.yml?label=playwright)
![DI Compilation](https://img.shields.io/github/actions/workflow/status/yireo/Yireo_SwooleApi/compile.yml?label=compile)
<!-- badges.test.end -->
