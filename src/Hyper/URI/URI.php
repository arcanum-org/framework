<?php

declare(strict_types=1);

namespace Arcanum\Hyper\URI;

use Arcanum\Gather\Registry;
use Psr\Http\Message\UriInterface;

class URI implements UriInterface
{
    /**
     * @var Scheme The scheme of the URI.
     */
    protected Scheme $scheme;

    /**
     * @var Authority The authority of the URI.
     */
    protected Authority $authority;

    /**
     * @var Path The path of the URI.
     */
    protected Path $path;

    /**
     * @var Query The query of the URI.
     */
    protected Query $query;

    /**
     * @var Fragment The fragment of the URI.
     */
    protected Fragment $fragment;

    /**
     * @var string|null The composed URI after normalization.
     */
    protected string|null $composed = null;

    /**
     * Create a new URI.
     */
    public function __construct(protected string $uri = '')
    {
        $parsed = Spec::parse($uri);

        $this->scheme = $parsed['scheme'];
        $this->authority = new Authority(
            $parsed['user'],
            $parsed['pass'],
            $parsed['host'],
            $parsed['port'],
        );
        $this->path = $parsed['path'];
        $this->query = $parsed['query'];
        $this->fragment = $parsed['fragment'];

        $this->finalize();
    }

    /**
     * Get the URI as a string.
     */
    public function __toString(): string
    {
        if ($this->composed !== null) {
            return $this->composed;
        }

        $this->composed = '';

        if ((string)$this->scheme !== '') {
            $this->composed .= $this->scheme . ':';
        }

        $authority = $this->getAuthority();

        if ($authority !== '' || (string)$this->scheme === 'file') {
            $this->composed .= '//' . $authority;
        }

        $this->composed .= $this->path;

        if ((string)$this->query !== '') {
            $this->composed .= '?' . $this->query;
        }

        if ((string)$this->fragment !== '') {
            $this->composed .= '#' . $this->fragment;
        }

        return $this->composed;
    }

    /**
     * Get the Scheme as a string.
     */
    public function getScheme(): string
    {
        return (string)$this->scheme;
    }

    /**
     * Get the Authority as a string.
     */
    public function getAuthority(): string
    {
        return (string)$this->authority;
    }

    /**
     * Get the User Info as a string.
     */
    public function getUserInfo(): string
    {
        return $this->authority->getUserInfo();
    }

    /**
     * Get the Host as a string.
     */
    public function getHost(): string
    {
        return (string)$this->authority->getHost();
    }

    /**
     * Get the Port as an integer, or null if not set.
     */
    public function getPort(): int|null
    {
        $port = $this->authority->getPort();
        return $port === null ? null : (int)(string)$port;
    }

    /**
     * Get the Path as a string.
     */
    public function getPath(): string
    {
        return (string)$this->path;
    }

    /**
     * Get the Query as a string.
     */
    public function getQuery(): string
    {
        return (string)$this->query;
    }

    /**
     * Get the Fragment as a string.
     */
    public function getFragment(): string
    {
        return (string)$this->fragment;
    }

    /**
     * Create a copy of the URI with the given scheme.
     */
    public function withScheme(string $scheme): UriInterface
    {
        $clone = clone $this;
        $clone->scheme = new Scheme($scheme);
        $clone->composed = null;
        $clone->finalize();
        return $clone;
    }

    /**
     * Create a copy of the URI with the given user info.
     */
    public function withUserInfo(string $user, string|null $password = null): UriInterface
    {
        $clone = clone $this;
        $clone->authority = $this->authority->withUserInfo(
            new UserInfo($user),
            new UserInfo($password ?? '')
        );
        $clone->composed = null;
        $clone->finalize();
        return $clone;
    }

    /**
     * Create a copy of the URI with the given host.
     */
    public function withHost(string $host): UriInterface
    {
        $clone = clone $this;
        $clone->authority = $this->authority->withHost(new Host($host));
        $clone->composed = null;
        $clone->finalize();
        return $clone;
    }

    /**
     * Create a copy of the URI with the given port, or null to remove the port.
     */
    public function withPort(int|null $port): UriInterface
    {
        $clone = clone $this;
        $clone->authority = $this->authority->withPort($port === null ? null : new Port($port));
        $clone->composed = null;
        $clone->finalize();
        return $clone;
    }

    /**
     * Create a copy of the URI with the given path.
     */
    public function withPath(string $path): UriInterface
    {
        $clone = clone $this;
        $clone->path = new Path($path);
        $clone->composed = null;
        $clone->finalize();
        return $clone;
    }

    /**
     * Create a copy of the URI with the given query.
     */
    public function withQuery(string $query): UriInterface
    {
        $clone = clone $this;
        $clone->query = new Query($query);
        $clone->composed = null;
        $clone->finalize();
        return $clone;
    }

    /**
     * Create a copy of the URI with the given fragment.
     */
    public function withFragment(string $fragment): UriInterface
    {
        $clone = clone $this;
        $clone->fragment = new Fragment($fragment);
        $clone->composed = null;
        $clone->finalize();
        return $clone;
    }

    /**
     * Wrap up the URI after construction.
     *
     * This method is called after the URI is constructed, and is responsible
     * for ensuring that the URI is valid. It also performs some normalization
     * by removing the default port for the scheme, and adding a localhost host
     * for web URIs without a defined host.
     *
     * @throws MalformedURI if the URI is invalid.
     */
    protected function finalize(): void
    {
        if (Spec::hasDefaultPort($this)) {
            $this->authority = $this->authority->withPort(null);
        }

        if ($this->authority->getHost()->isEmpty() && $this->scheme->isWebScheme()) {
            $this->authority = $this->authority->withHost(Host::localhost());
        }

        if (empty($this->getAuthority())) {
            if ($this->scheme->isEmpty() && strpos(explode('/', (string)$this->path)[0], ':') !== false) {
                throw new MalformedURI("A relative URI must not have a colon ':' in the first path segment");
            }
        }
    }
}
