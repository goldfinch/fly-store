<?php

namespace Goldfinch\FlyStore;

use Exception;
use Psr\SimpleCache\CacheInterface;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Injector\Injector;

class FlyStore
{
    protected $store;

    protected $storeTypes = ['cache', 'session'];

    protected $request;

    protected $prefix = 'flyStore_';

    protected $lifetime = 600;

    public function __construct($store = 'cache')
    {
        if (!in_array($store, $this->storeTypes)) {
            throw new Exception($store . ' store is not recognized');
        }

        $this->store = $store;
    }

    public function getStoreType()
    {
        return $this->store;
    }

    public function getStore()
    {
        if ($this->isCurrentStore('cache')) {

            return Injector::inst()->get(CacheInterface::class . '.flyStore');
        } else if ($this->isCurrentStore('session')) {

            if (!$this->request) {
                $this->request = Injector::inst()->get(HTTPRequest::class);
            }
            return $this->request->getSession();
        }
    }

    public function exists($chain)
    {
        $key = $this->getCacheKey($chain);

        $store = $this->getStore();

        if ($this->isCurrentStore('cache')) {
            return $store->has($key);
        } else if ($this->isCurrentStore('session')) {
            return $store->get($key) ? true : false;
        }
    }

    public function set($chain, $data, int $lifetime = null)
    {

        $key = $this->getCacheKey($chain);

        $store = $this->getStore();

        if (!is_string($data)) {
            $data = serialize($data);
        }

        if ($this->isCurrentStore('cache')) {
            if ($lifetime === null) {
                $lifetime = $this->lifetime;
            }
            return $store->set($key, $data, $lifetime);
        } else if ($this->isCurrentStore('session')) {
            return $store->set($key, $data);
        }
    }

    public function get($chain)
    {
        $key = $this->getCacheKey($chain);

        $store = $this->getStore();

        $data = $store->get($key);

        if (is_string($data)) {
            $data = unserialize($data);
        }

        return $data;
    }

    public function delete($chain)
    {
        if ($this->exists($chain)) {
            $key = $this->getCacheKey($chain);
            $store = $this->getStore();

            if ($this->isCurrentStore('cache')) {
                return $store->delete($key);
            } else if ($this->isCurrentStore('session')) {
                return $store->clear($key);
            }
        }
    }

    public function clear()
    {
        $store = $this->getStore();

        if ($this->isCurrentStore('cache')) {
            return $store->clear();
        } else if ($this->isCurrentStore('session')) {

            $flyStoreData = array_filter(
                $store->getAll(),
                function ($key) {
                    return substr($key, 0, 9) === $this->prefix;
                },
                ARRAY_FILTER_USE_KEY
            );

            foreach ($flyStoreData as $fc => $data) {
                $store->clear($fc);
            }
        }
    }

    public static function create(...$args)
    {
        return new static(...$args);
    }

    protected function isCurrentStore($store)
    {
        return $this->getStoreType() == $store;
    }

    protected function getCacheKey($chain)
    {
        if (is_string($chain)) {
            $chain = [$chain];
        } else if (!is_array($chain)) {
            throw new Exception('The key can only be defined by string or array');
        }

        return $this->prefix . md5(implode('|', array_map('md5', $chain)));
    }
}
