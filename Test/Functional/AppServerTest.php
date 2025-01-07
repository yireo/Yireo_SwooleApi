<?php
declare(strict_types=1);

namespace Yireo\SwooleApi\Test\Functional;

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;

class AppServerTest extends TestCase
{
    public function testServerStatus()
    {
        $graphQLquery = '{"query": "query { courses { id } }"}';

        $response = (new Client)->request('post', 'http://localhost:4000/graphql', [
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'body' => $graphQLquery
        ]);
        $body = $response->getBody();
        $data = json_decode($body->getContents());
        $this->assertNotEmpty($data);
        $this->assertArrayHasKey('courses', $data);
    }
}
