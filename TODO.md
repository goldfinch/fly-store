## Usage

```php

$flyStore = FlyStore::create(); // cache
// $flyStore = FlyStore::create('session');

$chain = ['PageController', 'init', 11, 'ContentController', 1]; // random chain as a key

// $flyStore->delete($chain);

if ($flyStore->exists($chain)) {
    $data = $flyStore->get($chain);
} else {
    $data = $this;
    $flyStore->set($chain, $data);
}

// $flyStore->clear();

```
