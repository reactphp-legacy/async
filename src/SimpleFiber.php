<?php

namespace React\Async;

use React\EventLoop\Loop;

/**
 * @internal
 */
final class SimpleFiber implements FiberInterface
{
    private static ?\Fiber $scheduler = null;
    private ?\Fiber $fiber = null;

    public function __construct()
    {
        $this->fiber = \Fiber::getCurrent();
    }

    public function resume(mixed $value): void
    {
        if ($this->fiber === null) {
            Loop::futureTick(static fn() => \Fiber::suspend(static fn() => $value));
            return;
        }

        $this->fiber->resume($value);
    }

    public function throw(\Throwable $throwable): void
    {
        if ($this->fiber === null) {
            Loop::futureTick(static fn() => \Fiber::suspend(static fn() => throw $throwable));
            return;
        }

        $this->fiber->throw($throwable);
    }

    public function suspend(): mixed
    {
        if ($this->fiber === null) {
            if (self::$scheduler === null || self::$scheduler->isTerminated()) {
                self::$scheduler = new \Fiber(static fn() => Loop::run());
                // Run event loop to completion on shutdown.
                \register_shutdown_function(static function (): void {
                    if (self::$scheduler->isSuspended()) {
                        self::$scheduler->resume();
                    }
                });
            }

            return (self::$scheduler->isStarted() ? self::$scheduler->resume() : self::$scheduler->start())();
        }

        return \Fiber::suspend();
    }
}
