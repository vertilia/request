<?php

namespace Vertilia\Request;

use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass HttpRequest
 */
class HttpRequestTest extends TestCase
{
    /**
     * @param array $args
     * @return HttpRequest
     */
    public function getHttpRequest(array $args = []): HttpRequest
    {
        return new HttpRequest(
            $args['server'] ?? [],
            $args['get'] ?? null,
            $args['post'] ?? null,
            $args['cookies'] ?? null,
            $args['php_input'] ?? null
        );
    }

    /**
     * @covers ::__construct
     */
    public function testHttpRequestConstruct()
    {
        $request = new HttpRequest([]);
        $this->assertInstanceOf(HttpRequest::class, $request);
    }

    /**
     * @dataProvider httpRequestMultiProvider
     * @covers ::getHttpMethod
     * @covers ::getHttpScheme
     * @covers ::getHttpHost
     * @covers ::getHttpPort
     * @covers ::getHttpPath
     * @param array $server
     * @param string $method
     * @param string $scheme
     * @param string $host
     * @param int $port
     * @param string $path
     * @param string $query
     */
    public function testHttpRequestMulti($server, $method, $scheme, $host, $port, $path, $query)
    {
        $request = new HttpRequest($server);
        $this->assertEquals($method, $request->getHttpMethod());
        $this->assertEquals($scheme, $request->getHttpScheme());
        $this->assertEquals($host, $request->getHttpHost());
        $this->assertEquals($port, $request->getHttpPort());
        $this->assertEquals($path, $request->getHttpPath());
        $this->assertEquals($query, $request->getHttpQuery());
    }

    /** data provider */
    public function httpRequestMultiProvider()
    {
        return [
            [
                [
                    'REQUEST_METHOD' => 'GET',
                    'REQUEST_SCHEME' => 'http',
                    'HTTP_HOST' => 'localhost',
                    'SERVER_PORT' => 80,
                    'REQUEST_URI' => '/users/?limit=10',
                ],
                'GET', 'http', 'localhost', 80, '/users/', 'limit=10',
            ],
            [
                [
                    'REQUEST_METHOD' => 'POST',
                    'REQUEST_SCHEME' => 'https',
                    'HTTP_HOST' => 'localhost',
                    'SERVER_PORT' => 443,
                    'REQUEST_URI' => '/',
                ],
                'POST', 'https', 'localhost', 443, '/', '',
            ],
            [
                [
                    'REQUEST_METHOD' => 'PUT',
                    'HTTP_HOST' => 'localhost',
                    'HTTPS' => 'on',
                    'REQUEST_URI' => '/index.php',
                ],
                'PUT', 'https', 'localhost', 443, '/index.php', '',
            ],
            [
                [
                    'REQUEST_METHOD' => 'PATCH',
                    'HTTP_HOST' => 'localhost:9000',
                    'HTTPS' => 'off',
                    'REQUEST_URI' => '/users/1',
                    'QUERY_STRING' => 'name=lerdorf',
                ],
                'PATCH', '', 'localhost', 9000, '/users/1', 'name=lerdorf',
            ],
            [
                [
                    'HTTP_HOST' => 'localhost',
                ],
                '', '', 'localhost', 80, '', '',
            ],
            [
                [],
                '', '', '', 0, '', '',
            ],
        ];
    }

    /**
     * @dataProvider httpRequestDecodedArgsProvider
     * @covers ::getHttpGet
     * @param string $get
     * @param string $name
     * @param string $value
     */
    public function testRequestGet($get, $name, $value)
    {
        $request = new HttpRequest([], $get);
        $arr = $request->getHttpGet();
        $this->assertArrayHasKey($name, $arr);
        $this->assertEquals($value, $arr[$name]);
    }

    /**
     * @dataProvider httpRequestDecodedArgsProvider
     * @covers ::getHttpPost
     * @param string $post
     * @param string $name
     * @param string $value
     */
    public function testRequestPost($post, $name, $value)
    {
        $request = new HttpRequest([], null, $post);
        $arr = $request->getHttpPost();
        $this->assertArrayHasKey($name, $arr);
        $this->assertEquals($value, $arr[$name]);
    }

    /**
     * @dataProvider httpRequestDecodedArgsProvider
     * @covers ::getHttpCookies
     * @param string $cookies
     * @param string $name
     * @param string $value
     */
    public function testRequestCookies($cookies, $name, $value)
    {
        $request = new HttpRequest([], null, null, $cookies);
        $arr = $request->getHttpCookies();
        $this->assertArrayHasKey($name, $arr);
        $this->assertEquals($value, $arr[$name]);
    }

    /** data provider */
    public function httpRequestDecodedArgsProvider()
    {
        $sample = ['a' => 'b', 'c' => null];

        return [
            [$sample, 'a', 'b'],
            [$sample, 'c', null],
        ];
    }

    /**
     * @dataProvider httpRequestHeadersProvider
     * @covers ::getHttpCookies
     * @param array $server
     * @param string $name
     * @param string $value
     */
    public function testRequestHeaders($server, $name, $value)
    {
        $request = new HttpRequest($server);
        $arr = $request->getHttpHeaders();
        $this->assertArrayHasKey($name, $arr);
        $this->assertEquals($value, $arr[$name]);
    }

    /** data provider */
    public function httpRequestHeadersProvider()
    {
        return [
            [['HTTP_HOST' => 'localhost'], 'host', 'localhost'],
            [['HTTP_CACHE_CONTROL' => 'max-age=0'], 'cache-control', 'max-age=0'],
        ];
    }

    /**
     * @dataProvider httpRequestValidationProvider
     * @covers ::setFilters
     * @covers ::filter
     * @covers ::offsetGet
     * @param array $server
     * @param array $get
     * @param array $post
     * @param array $cookie
     * @param string $php_input
     * @param array $filters
     * @param string $name
     * @param string $value
     */
    public function testHttpRequestFilter($server, $get, $post, $cookie, $php_input, $filters, $name, $value)
    {
        $request = new HttpRequest($server, $get, $post, $cookie, $php_input, $filters);
        $this->assertEquals($value, $request[$name]);
        if (!in_array($request->getHttpMethod(), ['GET', 'POST']) and empty($post) and $php_input) {
            $this->assertGreaterThan(0, count($request->getHttpPost()));
        }
    }

    /**
     * @dataProvider httpRequestValidationProvider
     * @covers ::setFilters
     * @covers ::filter
     * @covers ::offsetGet
     * @param array $server
     * @param array $get
     * @param array $post
     * @param array $cookie
     * @param string $php_input
     * @param array $filters
     * @param string $name
     * @param string $value
     */
    public function testHttpRequestFilterSet($server, $get, $post, $cookie, $php_input, $filters, $name, $value)
    {
        $request = new HttpRequest($server, $get, $post, $cookie, $php_input);
        $request->setFilters($filters);
        $this->assertEquals($value, $request[$name]);
    }

    /** data provider */
    public function httpRequestValidationProvider()
    {
        $server_get = [
            'HTTP_COOKIE' => 'ln=en',
            'HTTP_ACCEPT_LANGUAGE' => 'fr-FR,fr;q=0.9,en-US;q=0.8,en;q=0.7,ru;q=0.6',
            'HTTP_ACCEPT_ENCODING' => 'gzip, deflate, br',
            'HTTP_ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
            'HTTP_DNT' => '1',
            'HTTP_USER_AGENT' => implode(' ', [
                'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_6)',
                'AppleWebKit/537.36 (KHTML, like Gecko)',
                'Chrome/73.0.3683.103',
                'Safari/537.36'
            ]),
            'HTTP_UPGRADE_INSECURE_REQUESTS' => '1',
            'HTTP_CACHE_CONTROL' => 'max-age=0',
            'HTTP_CONNECTION' => 'keep-alive',
            'HTTP_HOST' => 'localhost',
            'REQUEST_METHOD' => 'GET',
            'REQUEST_SCHEME' => 'http',
            'HTTP_HOST' => 'localhost:9000',
            'SERVER_PORT' => 80,
            'REQUEST_URI' => '/users/?limit=10',
        ];
        $get = [
            'limit' => 10,
        ];
        $cookie = [
            'ln' => 'en',
        ];

        $server_put = [
            'REQUEST_METHOD' => 'PUT',
            'HTTP_CONTENT_TYPE' => 'application/x-www-form-urlencoded'
            ] + $server_get;
        $php_input = 'id=123&name%5B%5D=Amundsen-Scott&name%5B%5D=Dome%20Fuji';

        $server_patch = [
            'REQUEST_METHOD' => 'PATCH',
            'HTTP_CONTENT_TYPE' => 'application/json'
            ] + $server_get;
        $php_input_patch = '{"id":"123","name":["Amundsen-Scott","Dome Fuji"]}';

        $filter2 = [
            'id' => \FILTER_VALIDATE_INT,
            'name' => ['filter' => \FILTER_SANITIZE_STRING, 'flags' => \FILTER_REQUIRE_ARRAY]
        ];

        return [
            'from get: limit' =>
                [$server_get, $get, null, $cookie, null, ['limit' => \FILTER_VALIDATE_INT], 'limit', 10],
            'from get: ln' =>
                [$server_get, $get, null, $cookie, null, ['ln' => \FILTER_SANITIZE_STRING], 'ln', 'en'],
            'from header: cookie' =>
                [$server_get, $get, null, $cookie, null, ['cookie' => \FILTER_SANITIZE_STRING], 'cookie', 'ln=en'],

            'from php_input: id' =>
                [$server_put, null, null, null, $php_input, $filter2, 'id', 123],
            'from php_input: name' =>
                [$server_put, null, null, null, $php_input, $filter2, 'name', ['Amundsen-Scott', 'Dome Fuji']],
            'from php_input: name err' =>
                [$server_put, null, null, null, 'id=123&name=Dome%20Fuji', $filter2, 'name', false],

            'from php_input_patch: id' =>
                [$server_patch, null, null, null, $php_input_patch, $filter2, 'id', 123],
            'from php_input_patch: name' =>
                [$server_patch, null, null, null, $php_input_patch, $filter2, 'name', ['Amundsen-Scott', 'Dome Fuji']],
            'from php_input_patch: name err' =>
                [$server_patch, null, null, null, '{"id":"123","name":"Dome Fuji"}', $filter2, 'name', false],
        ];
    }
}