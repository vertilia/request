<?php
declare(strict_types=1);

namespace Vertilia\Request;

/**
 * Children must handle HTTP request characteristics, like method name, scheme, port etc.
 */
interface HttpRequestInterface
{
    /**
     * @return string HTTP request method
     */
    public function getHttpMethod(): string;

    /**
     * @return string HTTP request scheme
     */
    public function getHttpScheme() : string;

    /**
     * @return string HTTP request host
     */
    public function getHttpHost() : string;

    /**
     * @return int HTTP request port
     */
    public function getHttpPort() : int;

    /**
     * @return string HTTP request path
     */
    public function getHttpPath() : string;

    /**
     * @return string HTTP request query string
     */
    public function getHttpQuery() : string;

    /**
     * @return array HTTP request cookies
     */
    public function getHttpGet(): array;

    /**
     * @return array HTTP request cookies
     */
    public function getHttpPost(): array;

    /**
     * @return array HTTP request cookies
     */
    public function getHttpCookies(): array;

    /**
     * @return array HTTP request headers
     */
    public function getHttpHeaders(): array;
}
