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
    protected $http_method = '';
    /** @var string */
    protected $http_scheme = '';
    /** @var string */
    protected $http_host = '';
    /** @var int */
    protected $http_port = 0;
    /** @var string */
    protected $http_path = '';
    /** @var string */
    protected $http_query = '';
    /** @var array */
    protected $http_get = [];
    /** @var array */
    protected $http_post = [];
    /** @var array */
    protected $http_cookies = [];
    /** @var array */
    protected $http_headers = [];

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
        $this->http_method = $server['REQUEST_METHOD'] ?? '';
        // scheme from REQUEST_SCHEME or HTTPS
        if (isset($server['REQUEST_SCHEME'])) {
            $this->http_scheme = $server['REQUEST_SCHEME'];
        } elseif (isset($server['HTTPS']) and $server['HTTPS'] != 'off') {
            $this->http_scheme = 'https';
        }
        // host and port from HTTP_HOST
        if (isset($server['HTTP_HOST'])) {
            list($this->http_host, $this->http_port) = explode(':', $server['HTTP_HOST']);
        }
        // port from SERVER_PORT
        if (empty($this->http_port)) {
            $this->http_port = $server['SERVER_PORT'] ?? ($this->http_scheme == 'https'
                ? 443
                : ($this->http_host ? 80 : 0)
            );
        }
        $this->http_port = (int) $this->http_port;
        // path and query from REQUEST_URI
        if (isset($server['REQUEST_URI'])) {
            list($this->http_path, $this->http_query) = explode('?', $server['REQUEST_URI'], 2);
        }
        // query from QUERY_STRING or REQUEST_URI
        $this->http_query = $server['QUERY_STRING'] ?? $this->http_query ?: '';
        $this->http_get = $get ?: [];
        $this->http_post = $post ?: [];
        $this->http_cookies = $cookies ?: [];

        // set headers
        foreach ($server as $k => $v) {
            if (\strncmp($k, 'HTTP_', 5) === 0) {
                $this->http_headers[\strtolower(\strtr(\substr($k, 5), '_', '-'))] = $v;
            }
        }

        // for methods other than GET and POST fetch args from $php_input
        // and register them as post arguments
        if (! \in_array($this->http_method, ['GET', 'POST'])
            and isset($this->http_headers['content-type'])
            and isset($php_input)
        ) {
            list($type) = \explode(';', $this->http_headers['content-type'], 2);
            switch (trim($type)) {
                case 'application/json':
                    $this->http_post = \json_decode($php_input, true) ?: null;
                    break;
                case 'application/x-www-form-urlencoded':
                    \parse_str($php_input, $this->http_post);
                    break;
            }
        }

        // set filtered args if provided
        if ($filters) {
            parent::__construct(
                $filters,
                $this->http_headers + $this->http_cookies + $this->http_post + $this->http_get
            );
        }
    }

    public function getHttpMethod(): string
    {
        return $this->http_method;
    }

    public function getHttpScheme(): string
    {
        return $this->http_scheme;
    }

    public function getHttpHost(): string
    {
        return $this->http_host;
    }

    public function getHttpPort(): int
    {
        return $this->http_port;
    }

    public function getHttpPath(): string
    {
        return $this->http_path;
    }

    public function getHttpQuery(): string
    {
        return $this->http_query;
    }

    public function getHttpGet(): array
    {
        return $this->http_get;
    }

    public function getHttpPost(): array
    {
        return $this->http_post;
    }

    public function getHttpCookies(): array
    {
        return $this->http_cookies;
    }

    public function getHttpHeaders(): array
    {
        return $this->http_headers;
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
            (array) $this + $this->http_headers + $this->http_cookies + $this->http_post + $this->http_get,
            $add_empty
        );

        return $this;
    }

    /**
     * Overrides MutableValidArray::setFilters() by validating headers, cookies, post and get values
     * together with values already registered with $this
     *
     * @param array $filters filters descriptions to add to existing structure
     * @return MutableValidArray $this
     */
    public function addFilters(array $filters): MutableValidArray
    {
        $this->filters = \array_replace($this->filters, $filters);
        $values = (array) $this + $this->http_headers + $this->http_cookies + $this->http_post + $this->http_get;

        foreach ($filters as $k => $v) {
            if (\array_key_exists($k, $values)) {
                // revalidate existing value;
                $this[$k] = $values[$k];
            }
        }

        return $this;
    }
}
