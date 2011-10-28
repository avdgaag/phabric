<?php

/**
 * Generic exception that acts as namespace for framework-specific exceptions.
 *
 * It adds a `__toHtml` method to render the exception as a simple web page.
 */
abstract class AppException extends Exception {
    /**
     * Provide a human-friendly summary of the exception.
     *
     * @return String
     */
    public abstract function getSummary();

    /**
     * Render the exception as a simple web page containing the stack trace
     * and the summary.
     *
     * @see getSummary()
     * @return String
     */
    public function __toHtml() {
        $template = <<<EOS
<!doctype html><html><head><title>%1\$s</title></head><body>
<h1>%1\$s</h1>
<p>%2\$s</p>
<pre>%3\$s</pre>
</body></html>
EOS;
        return sprintf($template, $this->getMessage(), $this->getSummary(), $this->__toString());
    }
}

/**
 * Exception thrown when trying to provide an invalid HTTP verb using the
 * _method parameter hack.
 *
 * It is created with a single argument: the invalid verb that triggered the
 * error.
 */
class InvalidVerbException extends AppException {
    public function __construct($verb) {
        parent::__construct("Invalid HTTP verb '$verb'", 400);
    }

    public function getSummary() {
        return 'An error occured while trying to handle your request. You are trying to use an unsupported HTTP method.';
    }
}

/**
 * Exception thrown when a user is not authorized for a request.
 */
class UnauthorizedException extends AppException {
    public function __construct() {
        parent::__construct('You are not authorized for this request.', 403);
    }

    public function getSummary() {
        return 'For this action, you need to be properlay authorized.';
    }
}

/**
 * Exception thrown when a request results in a 404 error.
 *
 * It is created with a single argument: the request that resulted in the error.
 */
class NotFoundException extends AppException {
    public function __construct($request) {
        parent::__construct('No matching route found for ' . $request->__toString(), 404);
    }

    public function getSummary() {
        return 'The page you are looking for could not be found. Please check the URL and try again.';
    }
}

/**
 * Simple utility class for validating user input.
 *
 * The validator holds a set of user-provided data and can run tests on fields
 * in that set. It collects any errors it finds, which you can present to
 * the user.
 *
 * Usage example:
 *
 *     $validate = new Validator($params);
 *     $validate->length('name');
 *     if($validate->hasErrors()) {
 *         print_r($validate->errors);
 *     }
 *
 * The validator methods all return the validator object itself, so you can
 * chain multiple validations together:
 *
 *     $validate->length('name')->numericality('age');
 *
 */
class Validator {

    /**
     * List of collected errors
     * @return Array
     */
    public $errors;

    /**
     * Collection of attributes that we are validating
     * @return Array
     */
    private $data;

    public function __construct(array $data) {
        $this->errors = array();
        $this->data   = $data;
    }

    /**
     * See if there are any errors collected in this validator so far.
     * @return Boolean
     */
    public function hasErrors() {
        return !empty($this->errors);
    }

    /**
     * Validate the length of a field.
     *
     * Specify the tests using the $options argument:
     *
     * - max for maximum length
     * - min for minimum length
     *
     * @param String $attribute
     * @param Array $options (min, max)
     */
    public function length($attribute, array $options) {
        if($options['max'] && strlen($this->data[$attribute]) > $options['max']) {
            $this->errors[] = sprintf('%s must be at most %d', ucfirst($attribute), $options['max']);
        }
        if($options['min'] && strlen($this->data[$attribute]) < $options['min']) {
            $this->errors[] = sprintf('%s must be at least %d', ucfirst($attribute), $options['min']);
        }
        return $this;
    }

    /**
     * Validate presence of a field -- make sure the field exists and is not
     * empty.
     *
     * @param String $attribute
     * @param String $message error message to use.
     */
    public function presence($attribute, $message = '%s must be present') {
        if(!array_key_exists($attribute, $this->data) || empty($this->data[$attribute])) {
            $this->errors[] = sprintf($message, ucfirst($attribute));
        }
        return $this;
    }

    /**
     * Validate if a field has a numeric value.
     *
     * @param String $attribute
     * @param String $message error message to use.
     */
    public function numericality($attribute, $message = '%s must be a number') {
        if(!is_numeric($this->data[$attribute])) {
            $this->errors[] = sprintf($message, ucfirst($attribute));
        }
        return $this;
    }

    /**
     * Validate the value of a field is in a given array of values.
     *
     * @param String $attribute
     * @param Array $allowed_values whitelist of allowed values
     * @param String $message error message to use.
     */
    public function inclusion($attribute, $allowed_values, $message = '%s is invalid') {
        if(!in_array($this->data[$attribute], $allowed_values)) {
            $this->errors[] = sprintf($message, ucfirst($attribute));
        }
        return $this;
    }

    /**
     * Validate whether a field matches a given regular expression.
     *
     * @param String $attribute
     * @param String $pattern regular expression to match
     * @param String $message error message to use.
     */
    public function pattern($attribute, $pattern, $message = '%s is invalid') {
        if(!preg_match($pattern, $this->data[$attribute])) {
            $this->errors[] = sprintf($message, ucfirst($attribute));
        }
        return $this;
    }

}

/**
 * Wrapper object for the current server request, holding environment
 * information and input from the user.
 *
 * User input can come from form input, query strings or by pattern matching
 * a route. Routes using regular expressions will have all of its named
 * captures merged into the request params.
 *
 * A special property is the request method used: as most browsers only
 * support the GET and POST methods, you can provide a '_method' parameter
 * with a POST request to specify that the request should be interpreted as
 * either PUT or DELETE.
 *
 */
class Request {
    public
        /**
         * Parameters from query string, request data or route matching
         * @return Array
         */
        $params,

        /**
         * The current request method, e.g. GET, POST, PUT or DELETE
         * @return String
         */
        $method,

        /**
         * The current request path, e.g. '/posts/12'
         * @return String
         */
        $path;

    /**
     * Create a new request with environment and parameter values.
     * The environment information is expected to be or look like PHPs
     * $_SERVER superglobal.
     *
     * @param Array $env
     * @param Array $params
     */
    public function __construct(array $env, array $params) {
        $this->params = $params;
        $this->path   = $env['REQUEST_URI'];
        $this->method = $env['REQUEST_METHOD'];
        $this->translateMethod();
    }

    /**
     * Test if a given action object matches for the current request, and
     * should therefore be used to generate a response.
     *
     * This will test for matching methods and paths. Action paths are
     * interpreted as regular expressions and any named captures will be
     * merged into the params.
     *
     * @param Object $action object with properties for method and path
     * @return Boolean
     */
    public function matches($action) {
        if($this->method != $action->method) return false;
        if(!preg_match($action->path, $this->path, $matches)) return false;
        $this->mergeInRouteParams($matches);
        return true;
    }

    /**
     * Merge in the values of non-numeric keys into the params array.
     *
     * @param Array $match_data matches from a regular expression match.
     */
    private function mergeInRouteParams($match_data) {
        foreach($match_data as $key => $value) {
            if(!is_numeric($key)) {
                $this->params[$key] = $value;
            }
        }
    }

    /**
     * Render the request as a simple string signature like 'GET /posts/12'
     *
     * @return String
     */
    public function __toString() {
        return $this->method . ' ' . $this->path;
    }

    /**
     * When using a POST request, allow a special _method parameter to set
     * the current request method.
     *
     * @throws InvalidVerbException when the value of _method is not either
     *   PUT or DELETE.
     */
    private function translateMethod() {
        if($this->method == 'POST' && array_key_exists('_method', $this->params)) {
            if($this->params['_method'] == 'PUT' || $this->params['_method'] == 'DELETE') {
                $this->method = $this->params['_method'];
            } else {
                throw new InvalidVerbException($this->params['_method']);
            }
        }
    }
}

/**
 * The Response object is used to accumulate header information and content,
 * and provide a simple API for common operations, such as redirects.
 *
 * Response objects are created by the framework and you can use it
 * in your actions to write output to the user or set headers.
 */
class Response {
    private
        /**
         * Content to be printed when the output is rendered using run().
         * @return String
         */
        $content,

        /**
         * List of headers to send beore sending the output.
         * @return Array
         */
        $headers,

        /**
         * Flag indicating whether to send only headers or also content.
         * Defaults to false (both headers and content)
         * @return Boolean
         */
        $only_headers;

    public function __construct() {
        $this->only_headers = false;
        $this->headers = array();
    }

    /**
     * Queue raw output to be sent when this response is rendered.
     *
     * @param String $str
     */
    public function write($str) {
        $this->content .= $str;
    }

    /**
     * Shortcut method to set a header to redirect to a given URL.
     *
     * @param String $url
     */
    public function redirect($url) {
        $this->only_headers = true;
        $this->header('Location: ' . $url);
    }

    /**
     * Queue a header to be output when this response is rendered
     *
     * @param String $str
     */
    public function header($str) {
        $this->headers[]= $str;
    }

    /**
     * Render this response by setting headers and printing the output
     * for this request.
     */
    public function run() {
        array_walk($this->headers, 'header');
        if(!empty($this->content) && !$this->only_headers) {
            print $this->content;
        }
    }
}

class Application {
    private
        /**
         * All defined callback actions that generate a response.
         * @return Array
         */
        $actions,

        /**
         * Raw environment information in the format of $_SERVER
         * @return Array
         */
        $env,

        /**
         * Callbacks defined to handle error codes.
         * @return Array
         */
        $error_handlers,

        /**
         * Parameters passed to this request, like $_GET or $_POST
         * @return Array
         */
        $params,

        /**
         * All callbacks defined to run before actions.
         * @return Array
         */
        $before_filters;

    public
        /**
         * The current Request object
         * @return Request
         */
        $request,

        /**
         * The current response object
         * @return Response
         */
        $response;

    /**
     * Create a new application that has callbacks that generate responses
     * to incoming requests. It needs information about the environment and
     * request parameters, which default to $_SERVER and $_REQUEST.
     *
     * @param Array $env defaults to $_SERVER
     * @param Array $params defaults to $_REQUEST
     */
    public function __construct($env = null, $params = null) {
        $this->actions        = array();
        $this->error_handlers = array();
        $this->before_filters = array();
        $this->env            = is_array($env)    ? $env    : $_SERVER;
        $this->params         = is_array($params) ? $params : $_REQUEST;
    }

    public function get($path, $fn) {
        return $this->route('GET', '|^' . preg_quote($path) . '$|', $fn);
    }

    public function post($path, $fn) {
        return $this->route('POST', '|^' . preg_quote($path) . '$|', $fn);
    }

    public function put($path, $fn) {
        return $this->route('PUT', '|^' . preg_quote($path) . '$|', $fn);
    }

    public function delete($path, $fn) {
        return $this->route('DELETE', '|^' . preg_quote($path) . '$|', $fn);
    }

    /**
     * Specify a new action that may respond to a request signature identified
     * by the combination of method and path, by running a callback function
     * that can modify the current response object with output or headers.
     *
     * There are four convenience methods available, so you do not have to
     * specify the method: get(), put(), delete() and post().
     *
     * @param String $method the request method to respond to
     * @param String $path a regular expression that should match the
     *   Request path property.
     * @param Callback $fn a callable function that is run when a request
     *   matches the method and path.
     * @throws InvalidArgumentException when $fn is not callable.
     * @throws InvalidArgumentException when the method is not get, put, post
     *   or delete.
     */
    public function route($method, $path, $fn) {
        if(!is_callable($fn)) {
            throw new InvalidArgumentException('A callback is required.');
        }
        if(!preg_match('/^get|put|post|delete$/i', $method)) {
            throw new InvalidArgumentException('The method must be GET, PUT, POST or DELETE.');
        }

        $action = new StdClass();
        $action->method = $method;
        $action->path   = $path;
        $action->fn     = $fn;
        $this->actions[]= $action;

        return $this;
    }

    /**
     * Define a callback function to run before any action us run. This can
     * be useful to establish a database connection, set up authentication
     * or perform other boilerplate tasks.
     *
     * Note that before filters have access to the request params, so it
     * can inspect or modify them.
     *
     * @param Callable $fn
     * @throws InvalidArgumentException when $fn is not callable.
     */
    public function before($fn) {
        if(!is_callable($fn)) {
            throw new InvalidArgumentException('A callback is required.');
        }
        $this->before_filters[] = $fn;
        return $this;
    }

    /**
     * Define a callback to run and finish the current request when an error
     * with a given code occurs. Use this function to intercept 404 or 500
     * errors and render a pretty page.
     *
     * @param Integer $error_code HTTP status code to intercept
     * @throws InvalidArgumentException when $fn is not callable.
     */
    public function error($error_code, $fn = null) {
        if(!is_callable($fn)) {
            throw new InvalidArgumentException('A callback is required.');
        }
        $this->error_handlers[$error_code] = $fn;
        return $this;
    }

    /**
     * Get the first defined action that matches the current request.
     *
     * @return Callable
     * @throws NotFoundException when no matching action is found.
     */
    private function matchingAction() {
        $request = $this->request;
        $matches = array_filter($this->actions, function($a) use ($request) {
            return $request->matches($a);
        });
        if(empty($matches)) throw new NotFoundException($this->request);
        return array_shift($matches);
    }

    /**
     * Set up the current Request and Response objects, find the matching
     * action to run and render the response using output buffering. Any
     * defined before filters are run.
     *
     * When this method finishes, the Response is ready to be rendered to
     * the user.
     */
    private function runAction() {
        $this->request = new Request($this->env, $this->params);
        $this->response = new Response();
        ob_start();
        foreach($this->before_filters as $before_filter) {
            call_user_func($before_filter, &$this->request->params);
        }
        $output = call_user_func(
            $this->matchingAction()->fn,
            $this->request->params
        );
        ob_end_clean();
        $this->response->write($output);
    }

    /**
     * Try to run an action and render the response. Catches any exceptions
     * and tries to use callbacks to handle them.
     *
     * Run this method last in your script, as the request is done when this
     * method finishes.
     */
    public function run() {
        try {
            $this->runAction();
            $this->response->run();
        } catch(Exception $e) {
            if(
                array_key_exists($e->getCode(), $this->error_handlers) &&
                is_callable($this->error_handlers[$e->getCode()])
            ) {
                call_user_func($this->error_handlers[$e->getCode()]);
            } elseif(method_exists($e, '__toHtml')) {
                echo $e->__toHtml();
            } else {
                echo $e->__toString();
            }
        }
    }
}
