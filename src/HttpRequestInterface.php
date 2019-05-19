<?php
declare(strict_types=1);

namespace Vertilia\Request;

use Vertilia\ValidArray\MutableFiltersInterface;

/**
 * Children must handle HTTP request characteristics, like method name, scheme, port etc.
 */
interface HttpRequestInterface extends MutableFiltersInterface
{
    /**
     * @return string HTTP request method
     */
    public function getMethod(): string;

    /**
     * @return string HTTP request scheme
     */
    public function getScheme() : string;

    /**
     * @return string HTTP request host
     */
    public function getHost() : string;

    /**
     * @return int HTTP request port
     */
    public function getPort() : int;

    /**
     * @return string HTTP request path
     */
    public function getPath() : string;

    /**
     * @return string HTTP request query string
     */
    public function getQuery() : string;

    /**
     * @return array HTTP request GET variables
     */
    public function getVarsGet(): array;

    /**
     * @return array HTTP request POST variables
     */
    public function getVarsPost(): array;

    /**
     * @return array HTTP request cookies
     */
    public function getCookies(): array;

    /**
     * @return array HTTP request headers
     */
    public function getHeaders(): array;
}
