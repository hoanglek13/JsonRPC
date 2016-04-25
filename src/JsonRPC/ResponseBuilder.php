<?php

namespace JsonRPC;

use Exception;
use JsonRPC\Validator\JsonEncodingValidator;

/**
 * Class ResponseBuilder
 *
 * @package JsonRPC
 * @author  Frederic Guillot
 */
class ResponseBuilder
{
    /**
     * Payload ID
     *
     * @access private
     * @var mixed
     */
    private $id;

    /**
     * Payload ID
     *
     * @access private
     * @var mixed
     */
    private $result;

    /**
     * Payload error code
     *
     * @access private
     * @var integer
     */
    private $errorCode;

    /**
     * Payload error message
     *
     * @access private
     * @var string
     */
    private $errorMessage;

    /**
     * Payload error data
     *
     * @access private
     * @var mixed
     */
    private $errorData;

    /**
     * HTTP Headers
     *
     * @access private
     * @var array
     */
    private $headers = array(
        'Content-Type' => 'application/json',
    );

    /**
     * HTTP status
     *
     * @access private
     * @var string
     */
    private $status;

    /**
     * Exception
     *
     * @access private
     * @var Exception
     */
    private $exception;

    /**
     * Get new object instance
     *
     * @static
     * @access public
     * @return ResponseBuilder
     */
    public static function create()
    {
        return new static();
    }

    /**
     * Set id
     *
     * @access public
     * @param  mixed  $id
     * @return $this
     */
    public function withId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * Set result
     *
     * @access public
     * @param  mixed $result
     * @return $this
     */
    public function withResult($result)
    {
        $this->result = $result;
        return $this;
    }

    /**
     * Set error
     *
     * @access public
     * @param  integer $code
     * @param  string  $message
     * @param  string  $data
     * @return $this
     */
    public function withError($code, $message, $data = '')
    {
        $this->errorCode = $code;
        $this->errorMessage = $message;
        $this->errorData = $data;
        return $this;
    }

    /**
     * Set exception
     *
     * @access public
     * @param  Exception $exception
     * @return $this
     */
    public function withException(Exception $exception)
    {
        $this->exception = $exception;
        return $this;
    }

    /**
     * Add HTTP header
     *
     * @access public
     * @param  string $name
     * @param  string $value
     * @return $this
     */
    public function withHeader($name, $value)
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Add HTTP Status
     *
     * @access public
     * @param  string $status
     * @return $this
     */
    public function withStatus($status)
    {
        $this->status = $status;
        return $this;
    }

    /**
     * Build response
     *
     * @access public
     * @return string
     */
    public function build()
    {
        $encodedResponse = json_encode($this->buildResponse());
        JsonEncodingValidator::validate();

        return $encodedResponse;
    }

    /**
     * Send HTTP headers
     *
     * @access public
     * @return $this
     */
    public function sendHeaders()
    {
        if (php_sapi_name() !== 'cli') {
            if (! empty($this->status)) {
                header($this->status);
            }

            foreach ($this->headers as $name => $value) {
                header($name.': '.$value);
            }
        }

        return $this;
    }

    /**
     * Build response payload
     *
     * @access private
     * @return array
     */
    private function buildResponse()
    {
        $response = array('jsonrpc' => '2.0');

        $this->handleExceptions();

        if (! empty($this->errorCode)) {
            $response['error'] = $this->buildErrorResponse();
        } else {
            $response['result'] = $this->result;
        }

        $response['id'] = $this->id;

        return $response;
    }

    /**
     * Build response error payload
     *
     * @access private
     * @return array
     */
    private function buildErrorResponse()
    {
        $response = array(
            'code' => $this->errorCode,
            'message' => $this->errorMessage,
        );

        if (! empty($this->errorData)) {
            $response['data'] = $this->errorData;
        }

        return $response;
    }

    /**
     * Transform exceptions to JSON-RPC errors
     *
     * @access private
     */
    private function handleExceptions()
    {
        if ($this->exception !== null) {
            switch (get_class($this->exception)) {
                case 'JsonRPC\Exception\InvalidJsonFormatException':
                    $this->errorCode = -32700;
                    $this->errorMessage = 'Parse error';
                    $this->id = null;
                    break;
                case 'JsonRPC\Exception\InvalidJsonRpcFormatException':
                    $this->errorCode = -32600;
                    $this->errorMessage = 'Invalid Request';
                    $this->id = null;
                    break;
                case 'BadFunctionCallException':
                    $this->errorCode = -32601;
                    $this->errorMessage = 'Method not found';
                    break;
                case 'InvalidArgumentException':
                    $this->errorCode = -32602;
                    $this->errorMessage = 'Invalid params';
                    break;
                case 'JsonRPC\Exception\ResponseEncodingFailureException':
                    $this->errorCode = -32603;
                    $this->errorMessage = 'Internal error';
                    $this->errorData = $this->exception->getMessage();
                    break;
                case 'JsonRPC\Exception\AuthenticationFailureException':
                    $this->errorCode = 401;
                    $this->errorMessage = 'Unauthorized';
                    $this->withHeader('WWW-Authenticate', 'Basic realm="JsonRPC"');
                    $this->withStatus('HTTP/1.0 401 Unauthorized');
                    break;
                case 'JsonRPC\Exception\AccessDeniedException':
                    $this->errorCode = 403;
                    $this->errorMessage = 'Forbidden';
                    $this->withStatus('HTTP/1.0 403 Forbidden');
                    break;
                case 'JsonRPC\Exception\ResponseException':
                    $this->errorCode = $this->exception->getCode();
                    $this->errorMessage = $this->exception->getMessage();
                    $this->errorData = $this->getData();
                    break;
                default:
                    $this->errorCode = $this->exception->getCode();
                    $this->errorMessage = $this->exception->getMessage();
            }
        }
    }
}
