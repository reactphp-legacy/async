<?php

use React\EventLoop\Loop;
use React\Promise\CancellablePromiseInterface;
use function React\Async\async;
use function React\Async\await;
use function React\Promise\Timer\sleep;

require 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

ini_set('memory_limit', -1);

for ($i = 0; $i < 1_000_000; $i++) {
    async(static fn (): bool => true)();
}
