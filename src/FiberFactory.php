<?php

namespace React\Async;

/**
 * This factory its only purpose is interoperability. Where with
 * event loops one could simply wrap another event loop. But with fibers
 * that has become impossible and as such we provide this factory and the
 * FiberInterface.
 *
 * Usage is not documented and as such not supported and might chang without
 * notice. Use at your own risk.
 *
 * @internal
 */
final class FiberFactory
{
    private static ?\Closure $factory = null;

    public static function create(): FiberInterface
    {
        return (self::factory())();
    }

    public static function factory(?\Closure $factory = null): \Closure
    {
        if ($factory !== null) {
            self::$factory = $factory;
        }

        return self::$factory ?? static fn (): FiberInterface => new SimpleFiber();
    }
}
