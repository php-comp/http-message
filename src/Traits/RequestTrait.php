<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017/10/21
 * Time: 下午12:44
 */

namespace PhpComp\Http\Message\Traits;

use PhpComp\Http\Message\Uri;
use Psr\Http\Message\UriInterface;

/**
 * Trait RequestTrait
 * @package PhpComp\Http\Message\Traits
 */
trait RequestTrait
{
    use MessageTrait;

    /**
     * @var string
     */
    private $method;

    /**
     * The original request method (ignoring override)
     * @var string
     */
    private $originalMethod;


    /** @var  string */
    private $requestTarget;

    /**
     * The request URI object
     * @var Uri
     */
    private $uri;

    /**
     * Valid request methods
     * @var string[]
     */
    private $validMethods = [
        'CONNECT' => 1,
        'DELETE' => 1,
        'GET' => 1,
        'HEAD' => 1,
        'OPTIONS' => 1,
        'PATCH' => 1,
        'POST' => 1,
        'PUT' => 1,
        'TRACE' => 1,
    ];

    /**
     * @param string|UriInterface|null $uri
     * @param string|null $method
     * @throws \InvalidArgumentException
     */
    protected function initializeRequest($uri = null, $method = null)
    {
        try {
            $this->originalMethod = $this->filterMethod($method);
        } catch (\InvalidArgumentException $e) {
            $this->originalMethod = $method;
            throw $e;
        }

        $this->uri = $this->createUri($uri);
    }

    /**
     * @return string
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    protected function buildFirstLine(): string
    {
        // `GET /path HTTP/1.1`
        return sprintf(
            '%s %s %s/%s',
            $this->getMethod(),
            $this->uri->getPathAndQuery(),
            $this->getProtocol(),
            $this->getProtocolVersion()
        );
    }

    /**
     * @param string|UriInterface|null $uri
     * @return UriInterface
     * @throws \InvalidArgumentException
     */
    private function createUri($uri): UriInterface
    {
        if ($uri instanceof UriInterface) {
            return $uri;
        }
        if (\is_string($uri)) {
            return Uri::createFromString($uri);
        }

        if ($uri === null) {
            return new Uri();
        }

        throw new \InvalidArgumentException(
            'Invalid URI provided; must be null, a string, or a Psr\Http\Message\UriInterface instance'
        );
    }

    /*******************************************************************************
     * Method
     ******************************************************************************/

    /**
     * @return string
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function getMethod(): string
    {
        if ($this->method === null) {
            $this->method = $this->originalMethod;
            $customMethod = $this->getHeaderLine('X-Http-Method-Override');

            if ($customMethod) {
                $this->method = $this->filterMethod($customMethod);
            } elseif ($this->originalMethod === 'POST') {
                $overrideMethod = $this->filterMethod($this->getParsedBodyParam('_METHOD'));
                if ($overrideMethod !== null) {
                    $this->method = $overrideMethod;
                }

                if ($this->getBody()->eof()) {
                    $this->getBody()->rewind();
                }
            }
        }

        return $this->method;
    }

    /**
     * @param string $method
     */
    public function setMethod(string $method)
    {
        $this->method = $method;
    }

    /**
     * @return string
     */
    public function getOriginalMethod(): string
    {
        return $this->originalMethod;
    }

    /**
     * Does this request use a given method?
     * Note: This method is not part of the PSR-7 standard.
     * @param  string $method HTTP method
     * @return bool
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    public function isMethod($method): bool
    {
        return $this->getMethod() === strtoupper($method);
    }

    /**
     * request method is OPTIONS?
     */
    public function isOptions(): bool
    {
        return $this->isMethod('OPTIONS');
    }

    /**
     * request method is GET?
     */
    public function isGet(): bool
    {
        return $this->isMethod('GET');
    }

    /**
     * request method is POST?
     */
    public function isPost(): bool
    {
        return $this->isMethod('POST');
    }

    /**
     * request method is PUT?
     */
    public function isPut(): bool
    {
        return $this->isMethod('PUT');
    }

    /**
     * request method is PATCH?
     */
    public function isPatch(): bool
    {
        return $this->isMethod('PATCH');
    }

    /**
     * request method is DELETE?
     */
    public function isDelete(): bool
    {
        return $this->isMethod('DELETE');
    }

    /**
     * @param $method
     * @return RequestTrait
     * @throws \InvalidArgumentException
     */
    public function withMethod($method): self
    {
        $method = (string)$this->filterMethod($method);

        $clone = clone $this;
        $clone->originalMethod = $method;
        $clone->method = $method;

        return $clone;
    }

    /**
     * Validate the HTTP method
     * @param  null|string|mixed $method
     * @return null|string
     * @throws \InvalidArgumentException on invalid HTTP method.
     */
    protected function filterMethod($method)
    {
        if ($method === null) {
            return $method;
        }

        if (!\is_string($method)) {
            throw new \InvalidArgumentException(sprintf(
                'Unsupported HTTP method; must be a string, received %s',
                (\is_object($method) ? \get_class($method) : \gettype($method))
            ));
        }

        $method = \strtoupper($method);
        if (!isset($this->validMethods[$method])) {
            throw new \InvalidArgumentException($this, $method);
        }

        return $method;
    }

    /*******************************************************************************
     * Headers
     ******************************************************************************/


    /*******************************************************************************
     * URI
     ******************************************************************************/

    /**
     * @return string
     */
    public function getRequestTarget(): string
    {
        if ($this->requestTarget) {
            return $this->requestTarget;
        }

        if ($this->uri === null) {
            return '/';
        }

        $path = $this->uri->getPath();
        $query = $this->uri->getQuery();

        if ($query) {
            $path .= '?' . $query;
        }

        $this->requestTarget = $path;

        return $this->requestTarget;
    }

    /**
     * @param string $requestTarget
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function withRequestTarget($requestTarget): self
    {
        if (preg_match('#\s#', $requestTarget)) {
            throw new \InvalidArgumentException(
                'Invalid request target provided; must be a string and cannot contain whitespace'
            );
        }

        $clone = clone $this;
        $clone->requestTarget = $requestTarget;

        return $clone;
    }

    /**
     * @return Uri
     */
    public function getUri(): Uri
    {
        return $this->uri;
    }

    /**
     * @param Uri $uri
     */
    public function setUri(Uri $uri)
    {
        $this->uri = $uri;
    }

    /**
     * @param UriInterface $uri
     * @param bool $preserveHost
     * @return self
     */
    public function withUri(UriInterface $uri, $preserveHost = false): self
    {
        $clone = clone $this;
        $clone->uri = $uri;

        if (!$preserveHost) {
            if ($uri->getHost() !== '') {
                $clone->headers->set('Host', $uri->getHost());
            }
        } else {
            if ($uri->getHost() !== '' && (!$this->hasHeader('Host') || $this->getHeaderLine('Host') === '')) {
                $clone->headers->set('Host', $uri->getHost());
            }
        }

        return $clone;
    }

    /**
     * @return string
     */
    public function getPath(): string
    {
        return $this->getUri()->getPath();
    }

}
