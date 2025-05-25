<?php

declare(strict_types=1);

namespace Vertilia\Request;

use Vertilia\MimeType\MimeType;
use Vertilia\ValidArray\MutableValidArray;

/**
 * Represents current HTTP request with its method, path, args, cookies, headers
 * etc. Allows array access to all validated arguments. Since extends
 * MutableValidArray, allows setting filters after the instantiation. When
 * filters are set or added, corresponding values are revalidated if present.
 */
class HttpRequest extends MutableValidArray implements HttpRequestInterface
{
    protected string $method = '';
    protected string $scheme = '';
    protected string $host = '';
    protected int $port = 0;
    protected string $path = '';
    protected string $query = '';
    protected array $vars_server = [];
    protected array $vars_get = [];
    protected array $vars_post = [];
    protected array $cookies = [];
    protected array $headers = [];
    protected array $files = [];

    /**
     * Initializes Request with input parameters, parses routes.
     * $_SERVER params that provide current state:
     * - REQUEST_METHOD -- request method
     * - REQUEST_SCHEME -- request scheme
     * - HTTPS -- request scheme
     * - HTTP_HOST -- http header Host, request port
     * - SERVER_PORT -- request port
     * - REQUEST_URI -- request path, request query
     * - QUERY_STRING -- request query
     * - HTTP_* -- http headers
     *
     * @param array $server normally from $_SERVER
     * @param array $get normally from $_GET
     * @param array $post normally from $_POST
     * @param array $cookies normally from $_COOKIE
     * @param array $files normally from $_FILES
     * @param string $php_input normally from php://input
     * @param array $filters request parameters filters list
     */
    public function __construct(
        array $server,
        array $get = [],
        array $post = [],
        array $cookies = [],
        array $files = [],
        string $php_input = '',
        array $filters = []
    ) {
        $this->vars_server = $server;
        // method from REQUEST_METHOD
        $this->method = $this->vars_server['REQUEST_METHOD'] ?? '';
        // scheme from REQUEST_SCHEME or HTTPS
        if (isset($this->vars_server['REQUEST_SCHEME'])) {
            $this->scheme = $this->vars_server['REQUEST_SCHEME'];
        } elseif (isset($this->vars_server['HTTPS']) and $this->vars_server['HTTPS'] != 'off') {
            $this->scheme = 'https';
        }
        // host and port from HTTP_HOST
        if (isset($this->vars_server['HTTP_HOST'])) {
            list($this->host, $port) = explode(':', "{$this->vars_server['HTTP_HOST']}:", 2);
            $this->port = (int)$port;
        }
        // port from SERVER_PORT
        if (empty($this->port)) {
            $this->port = isset($this->vars_server['SERVER_PORT'])
                ? (int)$this->vars_server['SERVER_PORT']
                : ($this->scheme == 'https'
                    ? 443
                    : ($this->host ? 80 : 0)
                );
        }
        $this->port = (int)$this->port;
        // path and query from REQUEST_URI
        if (isset($this->vars_server['REQUEST_URI'])) {
            list($this->path, $this->query) = explode('?', "{$this->vars_server['REQUEST_URI']}?");
        }
        // query from QUERY_STRING or REQUEST_URI
        $this->query = $this->vars_server['QUERY_STRING'] ?? ($this->query ?: '');
        if ($get) {
            $this->vars_get = $get;
        } elseif ($this->query) {
            parse_str($this->query, $this->vars_get);
        }
        $this->vars_post = $post;
        $this->cookies = $cookies;
        $this->files = $files;

        // set headers
        foreach ($this->vars_server as $k => $v) {
            if (strncmp($k, 'HTTP_', 5) === 0) {
                $this->headers[strtolower(strtr(substr($k, 5), '_', '-'))] = $v;
            }
        }

        // if Content-Type header is defined, fetch args from $php_input
        // and register them as POST arguments
        if (empty($this->vars_post)
            and isset($this->headers['content-type'])
            and isset($php_input)
        ) {
            list($type) = explode(';', $this->headers['content-type'], 2);
            $this->vars_post = (array)MimeType::get($type)->decode($php_input);
        }

        // set filtered args if provided
        if ($filters) {
            parent::__construct($filters, $this->cookies + $this->vars_post + $this->vars_get);
        } else {
            parent::__construct([]);
        }
    }

    protected function registerRequestVars(array $vars = []): void
    {
        foreach ($this->cookies + $this->vars_post + $this->vars_get + $vars as $k => $v) {
            $this[$k] = $v;
        }
    }

    /**
     * Revalidate GET, POST, COOKIES and custom params after resetting filters
     *
     * @param array $filters filters descriptions to add to existing structure
     * @return MutableValidArray $this
     */
    public function setFilters(array $filters): MutableValidArray
    {
        $old_vars = (array)$this;
        parent::setFilters($filters);
        $this->registerRequestVars($old_vars);

        return $this;
    }

    /**
     * Revalidate GET, POST, COOKIES and custom params after adding filters
     *
     * @param array $filters filters descriptions to add to existing structure
     * @return MutableValidArray $this
     */
    public function addFilters(array $filters): MutableValidArray
    {
        $old_vars = (array)$this;
        parent::addFilters($filters);
        $this->registerRequestVars($old_vars);

        return $this;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getScheme(): string
    {
        return $this->scheme;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    public function getVarsServer(): array
    {
        return $this->vars_server;
    }

    public function getVarsGet(): array
    {
        return $this->vars_get;
    }

    public function getVarsPost(): array
    {
        return $this->vars_post;
    }

    public function getCookies(): array
    {
        return $this->cookies;
    }

    public function getFiles(): array
    {
        return $this->files;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }
}
