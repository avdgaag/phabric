Phabric is tiny framework for creating simple PHP web apps.

## Description

Phabric uses PHP 5.3's closures to directly map routes to request handlers, inspired -- as so many others -- by [Sinatra][]. Phabric provides *wrapper objects with helper methods for the response and the request*. It also provides very simple *exception handling* and a *before filter*, that gets called before every request and can be used to set up the application. Finally, it provides a few simple *validation* functions.

See the inline documentation for more information.

I created this project as a kind of excercise in creating my own little web framework using some of PHP's new features.

## Quick example application

```php
// Load the library
require 'phabric.php';

// Initialize a new application
$app = new Application();

// Before filters are run before every action and can be
// used to set up database connections, load settings, etc.
$app->before(function($params) {
    echo 'Starting request...'; 

// Exceptions can be handled by their error codes, so you
// can customize behaviour.
})->error(404, function() {
    echo 'not found';

// Simply map a request method (get, post, put or delete)
// and a request path to a handler function. The function
// has access to the request parameters and render output
// by returning a string.
})->get('/', function($params) {
    return 'Hello, world! The params are ' . print_r($params, true);

// Use a regular expression to match request paths. Named
// subcaptures get merged into the request params.
})->route('GET', '|^/(?<controller>[^/]+)/(?<action>[^/]+)/(?<id>[^/?]+)$|', function($params) {
    return print_r($params, true);

// Finally, run the application
})->run();
```

[Sinatra]: http://sinatrarb.com
