<?php
/**
 * A lightweight HTTP library which acts very similar to
 * the Python httplib.
 * <code>
 * $conn = new HTTPConnection('google.com');
 * $conn->request('GET', '/', array('q'=>'http'));
 * $response = $conn->getresponse();
 * $data = $response->read();
 * </code>
 * @author David Cramer <dcramer@gmail.com>
 * @version 1.0
 * @package httplib
 */

/**
 * The base exception for HTTPConnection
 * @package httplib
 */ 
class SocketError extends Exception { }
/**
 * Thrown when there is an unknown server error
 * @package httplib
 */
class UnknownServerError extends SocketError { }
/**
 * Thrown when there is an error trying to connection
 * @package httplib
 */
class ConnectionError extends SocketError { }

/**
 * The base HTTP class for opening connections.
 * @package httplib
 * @subpackage connection
 */
class HTTPConnection
{
    /**
     * Constructor defining the connection.
     * <code>
     * <?php
     * $conn = new HTTPConnection('google.com');
     * ?>
     * </code>
     * @param string $host hostname (e.g. domain.com)
     * @param int $port port
     */
    function __construct($host, $port=80, $timeout=60)
    {
        $this->host = $host;
        $this->port = $port;
        $this->timeout = $timeout;
        $this->response = null;
    }
    /**
     * Opens the connection and sends the request headers.
     * <code>
     * <?php
     * $conn->request('GET', '/', array('q'=>'http'));
     * ?>
     * </code>
     * @param string $method request method (GET/POST/PUT)
     * @param string $path request path
     * @param array $params associative array of parameters to send
     * @param array $headers associative array of headers to send
     */
    function request($method, $path, $params, $headers=array())
    {
        $this->socket = @fsockopen($this->host, $this->port, $errorNumber, $errorString, (float)$this->timeout);
        if (!$this->socket)
        {
            throw new ConnectionError('Failed connecting to '.$this->host.':'.$this->port.': '.socket_strerror($errorNumber).' ('.$errorNumber.'); '. $error);
        }
        stream_set_timeout($this->socket, (float)$this->timeout);
        $this->params = $params;
        $this->method = $method;
        $this->headers = $header;
        $this->path = $path;
    }
    /**
     * Reads the response from the server.
     * <code>
     * <?php
     * $response = $conn->getresponse();
     * ?>
     * </code>
     * @return HTTPResponse response object
     */
    function getresponse()
    {
        if ($this->response) return $this->response;
        $query_string = http_build_query($this->params);
        if ($this->method == "GET")
        {
            $path .= '?' . $query_string;
        }
        // set default headers
        $headers = $this->headers;
        if (empty($headers['User-Agent'])) $headers['User-Agent'] = 'php-httplib/1.0 (PHP ' . phpversion() . ')';
        if (empty($headers['Content-Type'])) $headers['Content-Type'] = 'application/x-www-form-urlencoded';
        if ($this->method == 'POST') $headers['Content-Length'] = strlen($query_string);
        $headers['Host'] = $this->host;
        // build the header string
        $request_header = strtoupper($this->method) . " " . $this->path . " HTTP/1.1\r\n";
        foreach ($headers as $key=>&$value)
        {
            $request_header .= $key . ": " . $value . "\r\n";
        }
        $request_header .= "Connection: close\r\n\r\n";
        
        if ($this->method == "POST")
        {
            $request_header .= $query_string;
        }
        fwrite($this->socket, $request_header);
        $response_header = '';
        do
        {
            $response_header .= fread($this->socket, 1);
        }
        while (!preg_match('/\\r\\n\\r\\n$/', $response_header));
        $this->response = new HTTPResponse($this->socket, $response_header);
        return $this->response;
    }
}
/**
 * A secure connection using SSL under the HTTP protocol.
 * @package httplib
 * @subpackage sslconnection
 */
class HTTPSConnection extends HTTPConnection
{
}

/**
 * A response object generated from an HTTPConnection.
 * @package httplib
 * @subpackage response
 */
class HTTPResponse
{
    /**
     * @param socket $socket connection socket
     * @param int $response response headers
     */
    function __construct(&$socket, &$response)
    {
        $headers = explode("\r\n", $response);
        $this->headers = array();
        foreach ($headers as &$line)
        {
            if (strpos($line, 'HTTP/') === 0)
            {
                $data = explode(' ', $line);
                $this->status = $data[1];
                $this->message = implode(' ', array_slice($data, 2));
            }
            elseif (strpos($line, ':'))
            {
                $data = explode(':', $line);
                $value = trim(implode(':', array_slice($data, 1)));
                $this->headers[$data[0]] = $value;
            }
        }
        $this->socket = $socket;
    }
    /**
     * Reads the response body.
     * <code>
     * <?php
     * $data = $response->read();
     * ?>
     * </code>
     * @return string response body
     */
    function read()
    {
        $response_content = '';
        if ($this->headers['Transfer-Encoding'] == 'chunked')
        {
            while ($chunk_length = hexdec(fgets($this->socket)))
            {
                $response_content_chunk = '';
                $read_length = 0;

                while ($read_length < $chunk_length)
                {
                    $response_content_chunk .= fread($this->socket, $chunk_length - $read_length);
                    $read_length = strlen($response_content_chunk);
                }

                $response_content .= $response_content_chunk;
                fgets($this->socket);
            }
        }
        else
        {
            while (!feof($this->socket))
            {
                $response_content .= fgets($this->socket, 128);
            }
        }
        return chop($response_content);
        fclose($this->socket);
    }
}
?>