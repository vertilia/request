<?php

namespace Vertilia\Request;

use PHPUnit\Framework\TestCase;
use Vertilia\ValidArray\MutableFiltersInterface;

use const FILTER_DEFAULT;
use const FILTER_REQUIRE_ARRAY;
use const FILTER_VALIDATE_INT;

/**
 * @coversDefaultClass HttpRequest
 */
class HttpRequestTest extends TestCase
{
    protected static string $temp_file = '';

    public static function setUpBeforeClass(): void
    {
        self::$temp_file = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents(self::$temp_file, "Delete me\n");
    }

    public static function tearDownAfterClass(): void
    {
        if (!empty(self::$temp_file)) {
            unlink(self::$temp_file);
        }
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
     * @dataProvider providerHttpRequestMulti
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
    public function testHttpRequestMulti(
        array $server,
        string $method,
        string $scheme,
        string $host,
        int $port,
        string $path,
        string $query
    ) {
        $request = new HttpRequest($server);
        $this->assertEquals($method, $request->getMethod());
        $this->assertEquals($scheme, $request->getScheme());
        $this->assertEquals($host, $request->getHost());
        $this->assertEquals($port, $request->getPort());
        $this->assertEquals($path, $request->getPath());
        $this->assertEquals($query, $request->getQuery());
    }

    /** data provider */
    public static function providerHttpRequestMulti(): array
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
     * @dataProvider providerHttpRequestDecodedArgs
     * @covers ::getVarsServer
     * @param array $server
     * @param string $name
     * @param ?string $value
     */
    public function testRequestServer(array $server, string $name, ?string $value)
    {
        $request = new HttpRequest($server);
        $arr = $request->getVarsServer();
        $this->assertArrayHasKey($name, $arr);
        $this->assertEquals($value, $arr[$name]);
    }

    /**
     * @dataProvider providerHttpRequestDecodedArgs
     * @covers ::getVarsGet
     * @param array $get
     * @param string $name
     * @param ?string $value
     */
    public function testRequestGet(array $get, string $name, ?string $value)
    {
        $request = new HttpRequest([], $get);
        $arr = $request->getVarsGet();
        $this->assertArrayHasKey($name, $arr);
        $this->assertEquals($value, $arr[$name]);
    }

    /**
     * @dataProvider providerHttpRequestDecodedArgs
     * @covers ::getVarsPost
     * @param array $post
     * @param string $name
     * @param ?string $value
     */
    public function testRequestPost(array $post, string $name, ?string $value)
    {
        $request = new HttpRequest([], null, $post);
        $arr = $request->getVarsPost();
        $this->assertArrayHasKey($name, $arr);
        $this->assertEquals($value, $arr[$name]);
    }

    /**
     * @dataProvider providerHttpRequestDecodedArgs
     * @covers ::getCookies
     * @param array $cookies
     * @param string $name
     * @param ?string $value
     */
    public function testRequestCookies(array $cookies, string $name, ?string $value)
    {
        $request = new HttpRequest([], null, null, $cookies);
        $arr = $request->getCookies();
        $this->assertArrayHasKey($name, $arr);
        $this->assertEquals($value, $arr[$name]);
    }

    /** data provider */
    public static function providerHttpRequestDecodedArgs(): array
    {
        $sample = ['a' => 'b', 'c' => null];

        return [
            [$sample, 'a', 'b'],
            [$sample, 'c', null],
        ];
    }

    /**
     * @dataProvider providerHttpRequestFiles
     * @covers ::getFiles
     * @param string[] $files
     * @param string $name
     */
    public function testRequestFiles(array $files, string $name)
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
    public static function providerHttpRequestFiles(): array
    {
        return [
            [
                ['single_file' =>[
                    'name' => 'TestFile.txt',
                    'type' => 'text/plain',
                    'size' => filesize(self::$temp_file),
                    'tmp_name' => self::$temp_file,
                    'error' => UPLOAD_ERR_OK,
                ]], 'single_file'
            ],
            [
                ['multiple_files' =>[
                    'name' => ['TestFile1.txt', 'TestFile2.txt'],
                    'type' => ['text/plain', 'text/plain'],
                    'size' => [filesize(self::$temp_file), filesize(self::$temp_file)],
                    'tmp_name' => [self::$temp_file, self::$temp_file],
                    'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
                ]], 'multiple_files'
            ],
        ];
    }

    /**
     * @dataProvider providerHttpRequestHeaders
     * @covers ::getHeaders
     * @param array $server
     * @param string $name
     * @param string $value
     */
    public function testRequestHeaders(array $server, string $name, string $value)
    {
        $request = new HttpRequest($server);
        $arr = $request->getHeaders();
        $this->assertArrayHasKey($name, $arr);
        $this->assertEquals($value, $arr[$name]);
    }

    /** data provider */
    public static function providerHttpRequestHeaders(): array
    {
        return [
            [['HTTP_HOST' => 'localhost'], 'host', 'localhost'],
            [['HTTP_CACHE_CONTROL' => 'max-age=0'], 'cache-control', 'max-age=0'],
        ];
    }

    /**
     * @dataProvider providerHttpRequestValidation
     * @covers ::__constructor
     * @covers ::getMethod
     * @covers ::getVarsPost
     * @covers ::offsetGet
     * @param ?array $server
     * @param ?array $get
     * @param ?array $post
     * @param ?array $cookie
     * @param ?string $php_input
     * @param ?array $filters
     * @param ?string $name
     * @param mixed $value
     */
    public function testHttpRequestFilter(
        ?array $server,
        ?array $get,
        ?array $post,
        ?array $cookie,
        ?string $php_input,
        ?array $filters,
        ?string $name,
        $value
    ) {
        $request = new HttpRequest($server, $get, $post, $cookie, null, $php_input, $filters);
        $this->assertInstanceOf(HttpRequest::class, $request);
        if (isset($name)) {
            $this->assertEquals($value, $request[$name]);
        }
        if (!in_array($request->getMethod(), ['GET', 'POST']) and empty($post) and $php_input) {
            $this->assertGreaterThan(0, count($request->getVarsPost()));
        }
    }

    /**
     * @dataProvider providerHttpRequestValidation
     * @covers ::setFilters
     * @covers ::filter
     * @covers ::offsetGet
     * @param ?array $server
     * @param ?array $get
     * @param ?array $post
     * @param ?array $cookie
     * @param ?string $php_input
     * @param ?array $filters
     * @param ?string $name
     * @param mixed $value
     */
    public function testHttpRequestSetFilters(
        ?array $server,
        ?array $get,
        ?array $post,
        ?array $cookie,
        ?string $php_input,
        ?array $filters,
        ?string $name,
        $value
    ) {
        $request = new HttpRequest(
            $server,
            $get,
            $post,
            ['id2' => 15, 'name2' => 'Vostok'] + ($cookie ?: []),
            null,
            $php_input,
            ['id2' => FILTER_VALIDATE_INT, 'name2' => FILTER_DEFAULT]
        );

        // check initial values
        $this->assertEquals(15, $request['id2']);
        $this->assertEquals('Vostok', $request['name2']);

        // setFilters()
        if ($filters) {
            $request->setFilters($filters);
            // check expected value
            if (isset($name)) {
                $this->assertEquals($value, $request[$name]);
            }
        }

        // check initial values unset
        $this->assertNotContains('id2', $request);
        $this->assertNotContains('name2', $request);
    }

    /**
     * @dataProvider providerHttpRequestValidation
     * @covers ::addFilters
     * @covers ::filter
     * @covers ::offsetGet
     * @param ?array $server
     * @param ?array $get
     * @param ?array $post
     * @param ?array $cookie
     * @param ?string $php_input
     * @param ?array $filters
     * @param ?string $name
     * @param mixed $value
     */
    public function testHttpRequestAddFilters(
        ?array $server,
        ?array $get,
        ?array $post,
        ?array $cookie,
        ?string $php_input,
        ?array $filters,
        ?string $name,
        $value
    ) {
        $request = new HttpRequest(
            $server,
            ['id2' => 15, 'name2' => 'Vostok'] + ($get ?: []),
            $post,
            $cookie,
            null,
            $php_input,
            ['id2' => FILTER_VALIDATE_INT, 'name2' => FILTER_DEFAULT]
        );

        // check initial values
        $this->assertEquals(15, $request['id2']);
        $this->assertEquals('Vostok', $request['name2']);

        if ($filters) {
            $request->addFilters($filters);
            if (isset($name)) {
                $this->assertEquals($value, $request[$name]);
            }
        }

        // check initial values still present
        $this->assertEquals(15, $request['id2']);
        $this->assertEquals('Vostok', $request['name2']);
    }

    /** data provider */
    public static function providerHttpRequestValidation(): array
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

        $server_post = [
            'REQUEST_METHOD' => 'POST',
            'HTTP_CONTENT_TYPE' => 'application/json'
        ] + $server_get;
        $php_input_post = '{"id":123,"name":["Amundsen-Scott","Dome Fuji"]}';

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
            'id' => FILTER_VALIDATE_INT,
            'name' => ['filter' => FILTER_DEFAULT, 'flags' => FILTER_REQUIRE_ARRAY]
        ];

        return [
            'from GET: limit' =>
                [$server_get, $get, null, $cookie, null, ['limit' => FILTER_VALIDATE_INT], 'limit', 10],
            'from GET: ln' =>
                [$server_get, $get, null, $cookie, null, ['ln' => FILTER_DEFAULT], 'ln', 'en'],

            'from POST php_input: id' =>
                [$server_post, null, null, null, $php_input_post, $filter2, 'id', 123],

            'from PUT php_input: id' =>
                [$server_put, null, null, null, $php_input, $filter2, 'id', 123],
            'from PUT php_input: name' =>
                [$server_put, null, null, null, $php_input, $filter2, 'name', ['Amundsen-Scott', 'Dome Fuji']],
            'from PUT php_input: name err' =>
                [$server_put, null, null, null, 'id=123&name=Dome%20Fuji', $filter2, 'name', false],

            'from PATCH php_input_patch: id' =>
                [$server_patch, null, null, null, $php_input_patch, $filter2, 'id', 123],
            'from PATCH php_input_patch: name' =>
                [$server_patch, null, null, null, $php_input_patch, $filter2, 'name', ['Amundsen-Scott', 'Dome Fuji']],
            'from PATCH php_input_patch: name err' =>
                [$server_patch, null, null, null, '{"id":"123","name":"Dome Fuji"}', $filter2, 'name', false],

            'empty' =>
                [[], null, null, null, null, null, null, null],
            'basic' =>
                [['REQUEST_URI' => '/index.php'] + $server_get, null, null, null, null, null, null, null],
        ];
    }
}
