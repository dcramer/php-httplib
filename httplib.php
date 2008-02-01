<?php
class SocketError extends Exception
{
    // The base exception for HTTPConnection
}
class UnknownServerError extends SocketError
{
    // Thrown when there is an unknown server error trying to connect
}
class HTTPConnection
{
    // This works *almost* like the Python class
    function __construct($host, $port=80, $timeout=60)
    {
        $this->host = $host;
        $this->port = $port;
        $this->timeout = $timeout;
        $this->response = null;
    }
    function request($method, $path, $params, $headers=array())
    {
        $this->socket = @fsockopen($this->host, $this->port, $errorNumber, $errorString, (float)$this->timeout);
        if (!$this->socket)
        {
            throw new UnknownServerError('Failed opening http socket connection: '.$errorString.' ('.$errorNumber.')');
        }
        $this->params = $params;
        $this->method = $method;
        $this->headers = $header;
        $this->path = $path;
    }
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
class HTTPSConnection extends HTTPConnection
{
    
}
class HTTPResponse
{
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
    }
}
?>
