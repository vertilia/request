<?php

namespace Vertilia\Request;

use PHPUnit\Framework\TestCase;
use Vertilia\ValidArray\MutableFiltersInterface;

/**
 * @coversDefaultClass HttpRequest
 */
class HttpRequestTest extends TestCase
{
    protected $temp_file;

    public function setUp()
    {
        $this->temp_file = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($this->temp_file, "Delete me\n");
    }

    public function tearDown()
    {
        unlink($this->temp_file);
    }

    /**
     * @covers ::__construct
     */
    public function testHttpRequestConstruct()
    {
        $request = new HttpRequest([]);
        $this->assertInstanceOf(MutableFiltersInterface::class, $request);
        $this->assertInstanceOf(HttpRequestInterface::class, $request);
    }

    /**
     * @dataProvider httpRequestMultiProvider
     * @covers ::getMethod
     * @covers ::getScheme
     * @covers ::getHost
     * @covers ::getPort
     * @covers ::getPath
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
        $this->assertEquals($method, $request->getMethod());
        $this->assertEquals($scheme, $request->getScheme());
        $this->assertEquals($host, $request->getHost());
        $this->assertEquals($port, $request->getPort());
        $this->assertEquals($path, $request->getPath());
        $this->assertEquals($query, $request->getQuery());
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
     * @covers ::getVarsGet
     * @param string $get
     * @param string $name
     * @param string $value
     */
    public function testRequestGet($get, $name, $value)
    {
        $request = new HttpRequest([], $get);
        $arr = $request->getVarsGet();
        $this->assertArrayHasKey($name, $arr);
        $this->assertEquals($value, $arr[$name]);
    }

    /**
     * @dataProvider httpRequestDecodedArgsProvider
     * @covers ::getVarsPost
     * @param string $post
     * @param string $name
     * @param string $value
     */
    public function testRequestPost($post, $name, $value)
    {
        $request = new HttpRequest([], null, $post);
        $arr = $request->getVarsPost();
        $this->assertArrayHasKey($name, $arr);
        $this->assertEquals($value, $arr[$name]);
    }

    /**
     * @dataProvider httpRequestDecodedArgsProvider
     * @covers ::getCookies
     * @param string $cookies
     * @param string $name
     * @param string $value
     */
    public function testRequestCookies($cookies, $name, $value)
    {
        $request = new HttpRequest([], null, null, $cookies);
        $arr = $request->getCookies();
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
     * @dataProvider httpRequestFilesProvider
     * @covers ::getFiles
     * @param string $files
     * @param string $name
     */
    public function testRequestFiles($files, $name)
    {
        $request = new HttpRequest([], null, null, null, $files);
        $rf = $request->getFiles();
        $this->assertArrayHasKey($name, $rf);
        if (is_array($rf[$name]['error'])) {
            foreach ($rf[$name]['error'] as $key => $err) {
                if ($err == UPLOAD_ERR_OK) {
                    $this->assertEquals(filesize($rf[$name]['tmp_name'][$key]), $rf[$name]['size'][$key]);
                } else {
                    echo "Filename {$rf[$name]['name'][$key]} from group $name lost\n";
                }
            }
        } else {
            $this->assertEquals(filesize($rf[$name]['tmp_name']), $rf[$name]['size']);
        }
    }

    /** data provider */
    public function httpRequestFilesProvider()
    {
        return [
            [
                ['single_file' =>[
                    'name' => 'TestFile.txt',
                    'type' => 'text/plain',
                    'size' => filesize($this->temp_file),
                    'tmp_name' => $this->temp_file,
                    'error' => UPLOAD_ERR_OK,
                ]], 'single_file'
            ],
            [
                ['multiple_files' =>[
                    'name' => ['TestFile1.txt', 'TestFile2.txt'],
                    'type' => ['text/plain', 'text/plain'],
                    'size' => [filesize($this->temp_file), filesize($this->temp_file)],
                    'tmp_name' => [$this->temp_file, $this->temp_file],
                    'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
                ]], 'multiple_files'
            ],
        ];
    }

    /**
     * @dataProvider httpRequestHeadersProvider
     * @covers ::getHeaders
     * @param array $server
     * @param string $name
     * @param string $value
     */
    public function testRequestHeaders($server, $name, $value)
    {
        $request = new HttpRequest($server);
        $arr = $request->getHeaders();
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
     * @covers ::__constructor
     * @covers ::getMethod
     * @covers ::getVarsPost
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
        $request = new HttpRequest($server, $get, $post, $cookie, null, $php_input, $filters);
        $this->assertEquals($value, $request[$name]);
        if (!in_array($request->getMethod(), ['GET', 'POST']) and empty($post) and $php_input) {
            $this->assertGreaterThan(0, count($request->getVarsPost()));
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
    public function testHttpRequestSetFilters($server, $get, $post, $cookie, $php_input, $filters, $name, $value)
    {
        $request = new HttpRequest(
            $server,
            $get,
            $post,
            ['id2' => 15, 'name2' => 'Vostok'] + ($cookie ?: []),
            null,
            $php_input,
            ['id2' => \FILTER_VALIDATE_INT, 'name2' => \FILTER_DEFAULT]
        );

        // check initial values
        $this->assertEquals(15, $request['id2']);
        $this->assertEquals('Vostok', $request['name2']);

        // setFilters()
        $request->setFilters($filters);
        // check expected value
        $this->assertEquals($value, $request[$name]);

        // check initial values unset
        $this->assertNotContains('id2', $request);
        $this->assertNotContains('name2', $request);
    }

    /**
     * @dataProvider httpRequestValidationProvider
     * @covers ::addFilters
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
    public function testHttpRequestAddFilters($server, $get, $post, $cookie, $php_input, $filters, $name, $value)
    {
        $request = new HttpRequest(
            $server,
            ['id2' => 15, 'name2' => 'Vostok'] + ($get ?: []),
            $post,
            $cookie,
            null,
            $php_input,
            ['id2' => \FILTER_VALIDATE_INT, 'name2' => \FILTER_DEFAULT]
        );

        // check initial values
        $this->assertEquals(15, $request['id2']);
        $this->assertEquals('Vostok', $request['name2']);

        $request->addFilters($filters);
        $this->assertEquals($value, $request[$name]);

        // check initial values still present
        $this->assertEquals(15, $request['id2']);
        $this->assertEquals('Vostok', $request['name2']);
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
