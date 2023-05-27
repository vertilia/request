<?php

declare(strict_types=1);

namespace Vertilia\Request;

use UnexpectedValueException;
use Vertilia\MimeType\ApplicationJson;
use Vertilia\MimeType\ApplicationXWwwFormUrlencoded;
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
     * @param ?array $get normally from $_GET
     * @param ?array $post normally from $_POST
     * @param ?array $cookies normally from $_COOKIE
     * @param ?array $files normally from $_FILES
     * @param ?string $php_input normally from php://input
     */
    public function __construct(
        array $server,
        array $get = null,
        array $post = null,
        array $cookies = null,
        array $files = null,
        string $php_input = null,
        array $filters = null
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
            $this->port = $this->vars_server['SERVER_PORT'] ?? ($this->scheme == 'https'
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
        $this->vars_post = $post ?: [];
        $this->cookies = $cookies ?: [];
        $this->files = $files ?: [];

        // set headers
        foreach ($this->vars_server as $k => $v) {
            if (strncmp($k, 'HTTP_', 5) === 0) {
                $this->headers[strtolower(strtr(substr($k, 5), '_', '-'))] = $v;
            }
        }

        // for methods other than GET and POST fetch args from $php_input
        // and register them as post arguments
        if (!in_array($this->method, ['GET', 'POST'])
            and isset($this->headers['content-type'])
            and isset($php_input)
        ) {
            list($type) = explode(';', $this->headers['content-type'], 2);
            $this->vars_post = (array)$this->decodeMimeType($type, $php_input);
        }

        // set filtered args if provided
        if ($filters) {
            $this->setFilters($filters);
        }
    }

    protected function decodeMimeType($mime_type, $content)
    {
        switch ($mime_type) {
            case 'application/json':
                $mt = new ApplicationJson();
                break;
            case 'application/x-www-form-urlencoded':
                $mt = new ApplicationXWwwFormUrlencoded();
                break;
            default:
                throw new UnexpectedValueException('Unknown mime type');
        }

        return $mt->decode($content);
    }

    /**
     * Overrides MutableValidArray::setFilters() by validating cookies, post and get values
     * together with values already registered with $this
     *
     * @param array $filters filters descriptions to add to existing structure
     * @param bool $add_empty whether to add missing values as NULL
     * @return MutableValidArray $this
     */
    public function setFilters(array $filters, bool $add_empty = true): MutableValidArray
    {
        parent::__construct(
            $filters,
            $this->cookies + $this->vars_post + $this->vars_get + (array)$this,
            $add_empty
        );

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

    /**
     * Overrides MutableValidArray::addFilters() by validating cookies, post and get values
     * together with values already registered with $this
     *
     * @param array $filters filters descriptions to add to existing structure
     * @return MutableValidArray $this
     */
    public function addFilters(array $filters): MutableValidArray
    {
        $this->filters = array_replace($this->filters, $filters);
        $values = $this->cookies + $this->vars_post + $this->vars_get + (array)$this;

        foreach ($filters as $k => $v) {
            if (array_key_exists($k, $values)) {
                // revalidate existing value;
                $this[$k] = $values[$k];
            }
        }

        return $this;
    }
}
