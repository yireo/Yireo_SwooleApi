# Yireo SwooleApi

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

### Benchmarks
Original Magento GraphQL request (150ms-500ms):
```bash
time curl -S http://magento/graphql -H 'Content-Type: application/json' -d '{"query": "query {products {id}}"}'
```

Swoole GraphQL request with Swoole running on port 4000 (100ms-350ms):
```bash
time curl -S http://magento:4000/graphql -H 'Content-Type: application/json' -d '{"query": "query {products {id}}"}'
```

