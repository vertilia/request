<?php
declare(strict_types=1);

namespace Vertilia\Request;

use Vertilia\ValidArray\MutableValidArray;

/**
 * Represents current HTTP request with its method, path, args, cookies, headers
 * etc. Allows array access to all validated arguments. Since extends
 * MutableValidArray, allows setting filters after the instantiation. When
 * filters are set or added, corresponding values are revalidated if present.
 */
class HttpRequest extends MutableValidArray implements HttpRequestInterface
{
    /** @var string */
    protected $method = '';
    /** @var string */
    protected $scheme = '';
    /** @var string */
    protected $host = '';
    /** @var int */
    protected $port = 0;
    /** @var string */
    protected $path = '';
    /** @var string */
    protected $query = '';
    /** @var array */
    protected $vars_get = [];
    /** @var array */
    protected $vars_post = [];
    /** @var array */
    protected $cookies = [];
    /** @var array */
    protected $headers = [];

    /**
     * Initializes Request with input parameters, parses routes
     *
     * @param array $server normally from $_SERVER
     * @param array $get normally from $_GET
     * @param array $post normally from $_POST
     * @param array $cookies normally from $_COOKIE
     * @param string $php_input normally from php://input
     */
    public function __construct(
        array $server,
        array $get = null,
        array $post = null,
        array $cookies = null,
        string $php_input = null,
        array $filters = null
    ) {
        // method from REQUEST_METHOD
        $this->method = $server['REQUEST_METHOD'] ?? '';
        // scheme from REQUEST_SCHEME or HTTPS
        if (isset($server['REQUEST_SCHEME'])) {
            $this->scheme = $server['REQUEST_SCHEME'];
        } elseif (isset($server['HTTPS']) and $server['HTTPS'] != 'off') {
            $this->scheme = 'https';
        }
        // host and port from HTTP_HOST
        if (isset($server['HTTP_HOST'])) {
            list($this->host, $this->port) = explode(':', $server['HTTP_HOST']);
        }
        // port from SERVER_PORT
        if (empty($this->port)) {
            $this->port = $server['SERVER_PORT'] ?? ($this->scheme == 'https'
                ? 443
                : ($this->host ? 80 : 0)
            );
        }
        $this->port = (int) $this->port;
        // path and query from REQUEST_URI
        if (isset($server['REQUEST_URI'])) {
            list($this->path, $this->query) = explode('?', $server['REQUEST_URI'], 2);
        }
        // query from QUERY_STRING or REQUEST_URI
        $this->query = $server['QUERY_STRING'] ?? $this->query ?: '';
        $this->vars_get = $get ?: [];
        $this->vars_post = $post ?: [];
        $this->cookies = $cookies ?: [];

        // set headers
        foreach ($server as $k => $v) {
            if (\strncmp($k, 'HTTP_', 5) === 0) {
                $this->headers[\strtolower(\strtr(\substr($k, 5), '_', '-'))] = $v;
            }
        }

        // for methods other than GET and POST fetch args from $php_input
        // and register them as post arguments
        if (! \in_array($this->method, ['GET', 'POST'])
            and isset($this->headers['content-type'])
            and isset($php_input)
        ) {
            list($type) = \explode(';', $this->headers['content-type'], 2);
            switch (trim($type)) {
                case 'application/json':
                    $this->vars_post = \json_decode($php_input, true) ?: null;
                    break;
                case 'application/x-www-form-urlencoded':
                    \parse_str($php_input, $this->vars_post);
                    break;
            }
        }

        // set filtered args if provided
        if ($filters) {
            parent::__construct(
                $filters,
                $this->headers + $this->cookies + $this->vars_post + $this->vars_get
            );
        }
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

    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Overrides MutableValidArray::setFilters() by validating headers, cookies, post and get values
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
            (array) $this + $this->headers + $this->cookies + $this->vars_post + $this->vars_get,
            $add_empty
        );

        return $this;
    }

    /**
     * Overrides MutableValidArray::addFilters() by validating headers, cookies, post and get values
     * together with values already registered with $this
     *
     * @param array $filters filters descriptions to add to existing structure
     * @return MutableValidArray $this
     */
    public function addFilters(array $filters): MutableValidArray
    {
        $this->filters = \array_replace($this->filters, $filters);
        $values = (array) $this + $this->headers + $this->cookies + $this->vars_post + $this->vars_get;

        foreach ($filters as $k => $v) {
            if (\array_key_exists($k, $values)) {
                // revalidate existing value;
                $this[$k] = $values[$k];
            }
        }

        return $this;
    }
}
