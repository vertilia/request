# request

Simple library to abstract request information and validate user input.

Built on [`ValidArray`](https://github.com/vertilia/valid-array), it not only
provides access to main request information, but integrates input arguments
filtering. This allows for simplified access to validated request parameters
as in the example below:

```php
<?php

$request = new \Vertilia\Request\HttpRequest(
    $_SERVER,
    $_GET,
    $_POST,
    $_COOKIE,
    file_get_contents('php://input'),
    [
        'id' => ['filter' => \FILTER_VALIDATE_INT, 'options' => ['min_range' => 1]],
        'name' => \FILTER_DEFAULT,
        'email' => \FILTER_VALIDATE_EMAIL,
        'profile' => ['filter' => \FILTER_VALIDATE_URL, 'flags' => \FILTER_FLAG_HOST_REQUIRED],
    ]
);

print_r($request['id']);
print_r($request['name']);
print_r($request['email']);
print_r($request['profile']);
```

In the example above, `$request` is initialised with HTTP request data, defines
input argument filters and tries to retrieve them from HTTP headers, cookies,
POST and GET parameters. In case of other HTTP method used, it parses
`php://input` based on `Content-Type` HTTP header. All found arguments are then
validated following the given filters.

Input validation guarantees that user data is of specified type. Input arguments
not mentionned in filters list are ignored. Arguments that do not match filters
are set to `false`. Missing arguments are set to `null`.

Employed filtering mechanism is standard php extension since `php-5.2`.

See [PHP Filter extension](https://php.net/filter).
