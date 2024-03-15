<?php

namespace Goldfinch\FlyStore;

use Exception;
use SilverStripe\ORM\ArrayList;
use SilverStripe\View\ArrayData;
use Psr\SimpleCache\CacheInterface;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Injector\Injector;

class FlyStore
{
    protected $store;

    protected $storeTypes = ['cache', 'session'];

    protected $request;

    protected $prefix = 'flyStore_';

    protected $representation;

    protected $representationTypes = ['json', 'serialize'];

    protected $lifetime = 600;

    public function __construct($store = 'cache', $representation = 'json')
    {
        if (!in_array($store, $this->storeTypes)) {
            throw new Exception($store . ' store is not recognized');
        }

        if (!in_array($representation, $this->representationTypes)) {
            throw new Exception($representation . ' representation is not recognized');
        }

        $this->representation = $representation;
        $this->store = $store;
    }

    public function getRepresentationType()
    {
        return $this->representation;
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

        if (!is_string($data)) {dd($this->isCurrentRepresentation('serialize'));
            if ($this->isCurrentRepresentation('json')) {
                $data = json_encode($data);
            } else if ($this->isCurrentRepresentation('serialize')) {
                $data = serialize($data);
            }
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

    public function get($chain, $representing = false)
    {
        $key = $this->getCacheKey($chain);

        $store = $this->getStore();

        $data = $store->get($key);

        if (is_string($data)) {
            if ($this->isCurrentRepresentation('json')) {
                $data = json_validate($data) ? json_decode($data, true) : $data;
            } else if ($this->isCurrentRepresentation('serialize')) {
                $data = unserialize($data);
            }
        }

        return $representing ? $this->representingData($data) : $data;
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

    protected function representingData($array)
    {
        if (!is_array($array)) {
            return $array;
        }

        array_walk(
            $array,
            $walker = function (&$value, $key) use (&$walker) {
                if (is_array($value)) {
                    array_walk($value, $walker);

                    if (array_is_list($value)) {
                        $value = new ArrayList($value);
                    }
                }
            },
        );

        if (array_is_list($array)) {
            $array = new ArrayList($array);
        } else {
            $array = new ArrayData($array);
        }

        return $array;
    }

    protected function isCurrentStore($store)
    {
        return $this->getStoreType() == $store;
    }

    protected function isCurrentRepresentation($representation)
    {
        return $this->getRepresentationType() == $representation;
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
