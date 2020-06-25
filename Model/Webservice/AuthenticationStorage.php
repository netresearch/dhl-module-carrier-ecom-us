<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace Dhl\EcomUs\Model\Webservice;

use Dhl\Sdk\EcomUs\Api\AuthenticationStorageInterface;
use Magento\Framework\App\CacheInterface;

/**
 * Authentication storage implementation with cache persistence.
 */
class AuthenticationStorage implements AuthenticationStorageInterface
{
    private const CACHE_KEY = 'dhlecomus_access_token';

    /**
     * @var CacheInterface
     */
    private $cache;

    /**
     * @var int
     */
    private $storeId;

    /**
     * @var string
     */
    private $username;

    /**
     * @var string
     */
    private $password;

    /**
     * AuthenticationStorage constructor.
     *
     * @param CacheInterface $cache
     * @param int $storeId
     * @param string $username
     * @param string $password
     */
    public function __construct(CacheInterface $cache, int $storeId, string $username, string $password)
    {
        $this->cache = $cache;
        $this->storeId = $storeId;
        $this->username = $username;
        $this->password = $password;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function readToken(): string
    {
        $token = $this->cache->load(self::CACHE_KEY . $this->storeId);
        if (!$token) {
            return '';
        }

        return $token;
    }

    public function saveToken(string $token, int $lifetime): void
    {
        $this->cache->save($token, self::CACHE_KEY . $this->storeId, [], $lifetime);
    }
}
