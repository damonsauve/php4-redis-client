<?php

/**
 * A partially implemented Redis client for PHP4 partly based on Rediska:
 * https://github.com/Shumkov/Rediska
 *
 * To do:
 * - use non-blocking socket requests.
 * - implement remaining Redis response types.
 *
 * PHP version 4
 *
 * @package    Php4RedisClient
 * @author     Damon Sauve
 */
class Php4RedisClient
{
    // Default Redis IP.
    //
    var $redisAddress = '127.0.0.1';

    // Default Redis port.
    //
    var $redisPort = 6379;

    // Socket stuff.
    //
    var $_socket;

    var $_socketLength = 1024;

    // Incremental socket data.
    //
    var $_buffer;

    // Redis response type.
    //
    var $_responseType;

    // Number of Redis args in buffer.
    //
    var $_itemCount;

    // Number of completed items in buffer.
    //
    var $_lastIndexNumber;

    // Flag to keep reading from socket.
    //
    var $_needsMoreData;

    // Data returned from Redis request (minus the response line).
    //
    var $response = array();

    function Php4RedisClient($redis_address, $redis_port)
    {
        if ($redis_address) {
            $this->redisAddress = $redis_address;
        }

        if ($redis_port) {
            $this->redisPort = $redis_port;
        }

        return $this->_connect();
    }

    function callRedis($method, $args)
    {
        $this->_clear_buffer();
        $this->_clear_responseType();
        $this->_clear_response();

        $this->_lastIndexNumber = 0;
        $this->_itemCount = 0;
        $this->_needsMoreData = true;

        // Push Redis method to front of args.
        //
        array_unshift($args, $method);

        $cmd = $this->_build_redis_command($args);

        if (! $this->is_connected()) {
            $errorData = array(
                severity => ERR_WARNING,
                msg => "Can't read from socket",
                extras => ''
            );

            print($errorData);

            return;
        }

        socket_write($this->_socket, $cmd, strlen($cmd));

        // Read from socket until parser determines that response is complete.
        // Remember that the socket is a hose that doesn't turn off by itself.
        // You'll need to parse the response and figure out when to stop reading
        // from the socket.
        //
        while ($this->_needsMoreData) {
            $this->_read_socket();
            $this->_parse_redis();
        }

        return;
    }

    function disconnect()
    {
        if ($this->is_connected()) {
            socket_close($this->_socket);
            $this->_socket = null;
            return true;
        } else {
            return false;
        }
    }

    function is_connected()
    {
        return is_resource($this->_socket);
    }

    function _connect()
    {
        if (! $this->is_connected()) {
            $socket = socket_create(AF_INET, SOCK_STREAM, getprotobyname('tcp'));

            socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, array(
                'sec' => 1,
                'usec' => 1000
            ));
            socket_set_option($socket, SOL_SOCKET, TCP_NODELAY, 1);

            if ($socket === false) {
                echo "socket_create() failed for reason: " . socket_strerror(socket_last_error()) . "\n";
            }

            $result = socket_connect($socket, $this->redisAddress, $this->redisPort);

            $this->_socket = $socket;

            if ($result === false) {
                $errorcode = socket_last_error();
                $errormsg = socket_strerror($errno);

                $msg = "Can't connect to Redis server on " . $this->redisAddress . ':' . $this->redisPort;

                if ($errorcode || $errormsg) {
                    $msg .= "," . ($errorcode ? " error $errorcode" : "") . ($errormsg ? " $errormsg" : "");
                }

                print $msg . "\n";

                $this->_socket = null;

                return;
            }
        }

        return;
    }

    function _read_socket()
    {
        $socket_out = socket_read($this->_socket, $this->_socketLength);

        // $this->_dump($socket_out, socket_out);

        if ($socket_out === false) {
            $errorcode = socket_last_error();
            $errormsg = socket_strerror($errorcode);

            $msg = "Can't read from socket for Redis server on " . $this->redisAddress . ':' . $this->redisPort;

            if ($errorcode || $errormsg) {
                $msg .= "," . ($errorcode ? " error $errorcode" : "") . ($errormsg ? " $errormsg" : "");
            }

            print $msg . "\n";

            return;
        }

        // Add socket data to current buffer.
        //
        $this->_buffer .= $socket_out;

        return;
    }

    function _clear_buffer()
    {
        $this->_buffer = '';

        return;
    }

    function _clear_responseType()
    {
        $this->_responseType = '';

        return;
    }

    function _clear_response()
    {
        $this->response = '';

        return;
    }

    function _build_redis_command($args)
    {
        $cmd = '*' . count($args) . "\r\n";

        foreach ($args as $item) {
            $cmd .= '$' . strlen($item) . "\r\n" . $item . "\r\n";
        }

        return $cmd;
    }

    function _parse_redis()
    {
        // Response type should be the first line of Redis response.
        //
        if (! $this->_responseType) {
            $this->_set_response_type();
        }

        switch ($this->_responseType) {
            case '*':
                $this->_handle_redis_array();
                break;
            case '+':
                $this->_handle_redis_simple_strings();
                break;
            case '-':
                $this->_handle_redis_errors();
                break;
            case ':':
                $this->_handle_redis_integers();
                break;
            case '$':
                $this->_handle_redis_bulk_strings();
                break;
            default:
                // Not enough data to determine type. Keep reading from socket.
       }

        return;
    }

    function _set_response_type()
    {
        // Redis Protocol specification: http://redis.io/topics/protocol

        // In RESP the type of some data depends on the first byte:
        //
        // For Simple Strings the first byte of the reply is "+"
        // For Errors the first byte of the reply is "-"
        // For Integers the first byte of the reply is ":"
        // For Bulk Strings the first byte of the reply is "$"
        // For Arrays the first byte of the reply is "*"
        //
        $data = split("\r\n", $this->_buffer);

        $this->_responseType = substr($data[0], 0, 1);
        $this->_itemCount = substr($data[0], 1, strlen($data[0]) - 1);

        return;
    }

    function _handle_redis_array()
    {
        // Split buffer on /r/n$ to get each Redis item, then compare Redis item
        // length with item length to determine if all data hasd been returned
        // from buffer. If not, read more from socket.
        //
        $matches = preg_split('/\r\n\$/', $this->_buffer);

        // Remove response type.
        //
        array_shift($matches);

        if (is_array($matches)) {
            // Only parse new items in the buffer.
            //
            for ($i = $this->_lastIndexNumber; $i < count($matches); $i ++) {
                $items = explode("\r\n", $matches[$i]);

                //$this->_dump($items, 'items');

                $item_length = trim($items[0], '$');
                $item_value = $items[1];

                $found_item_length = mb_strlen($item_value);

                // Null elements in Arrays
                //
                if ($item_length == '-1') {
                    // No data counts as a found item.
                    //
                    $this->_lastIndexNumber ++;
                } elseif ($item_length == $found_item_length) {
                    // If the string length is correct, we have a completed
                    // item.
                    //
                    $this->response[] = $item_value;

                    // Increment the found item count. This becomes the
                    // starting index on next parse.
                    //
                    $this->_lastIndexNumber ++;
                } else {
                    // Not enough string; keep reading.
                    //
                    //$this->_dump(not_enough_data);
                }

                //$this->_dump($this->_lastIndexNumber, _lastIndexNumber);
            }

            // If _lastIndexNumber equals _itemCount, we're done.
            //
            if ($this->_lastIndexNumber == $this->_itemCount) {
                $this->_needsMoreData = false;
            }
        } else {
            print "Error: Invalid input type for " . $this->_responseType;
            return;
        }

        return;
    }

    function _handle_redis_simple_strings()
    {
        if ($this->_itemCount == 'OK') {
            $this->_needsMoreData = false;
        } else {
            $this->_needsMoreData = false;
            print "Error: Response type not handled: " . $this->_responseType;
        }

        return;
    }

    function _handle_redis_errors()
    {
        print "Redis Error: " . $this->_itemCount;
        $this->_needsMoreData = false;
        return;
    }

    function _handle_redis_integers()
    {
        print "Error: Response type not handled: " . $this->_responseType;
        $this->_needsMoreData = false;
        return;
    }

    function _handle_redis_bulk_strings()
    {
        print "Error: Response type not handled: " . $this->_responseType;
        $this->_needsMoreData = false;
        return;
    }

    function _dump($x, $n = 'no label')
    {
        print "*** $n ***
";
        print_r($x);
        print "***

";
        return;
    }
}
