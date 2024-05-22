<?php

namespace React\Tests\Async;

use React;
use React\EventLoop\Loop;
use React\Promise\Deferred;
use React\Promise\Promise;
use React\Promise\PromiseInterface;
use function React\Async\async;

class AwaitTest extends TestCase
{
    /**
     * @dataProvider provideAwaiters
     */
    public function testAwaitThrowsExceptionWhenPromiseIsRejectedWithException(callable $await): void
    {
        $promise = new Promise(function () {
            throw new \Exception('test');
        });

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('test');
        $await($promise);
    }

    /**
     * @dataProvider provideAwaiters
     */
    public function testAwaitThrowsExceptionWithoutRunningLoop(callable $await): void
    {
        $now = true;
        Loop::futureTick(function () use (&$now) {
            $now = false;
        });

        $promise = new Promise(function () {
            throw new \Exception('test');
        });

        try {
            $await($promise);
        } catch (\Exception $e) {
            $this->assertTrue($now);
        }
    }

    /**
     * @dataProvider provideAwaiters
     */
    public function testAwaitThrowsExceptionImmediatelyWhenPromiseIsRejected(callable $await): void
    {
        $deferred = new Deferred();

        $ticks = 0;
        Loop::futureTick(function () use (&$ticks) {
            ++$ticks;
            Loop::futureTick(function () use (&$ticks) {
                ++$ticks;
            });
        });

        Loop::futureTick(fn() => $deferred->reject(new \RuntimeException()));

        try {
            $await($deferred->promise());
        } catch (\RuntimeException $e) {
            $this->assertEquals(1, $ticks);
        }
    }

    /**
     * @dataProvider provideAwaiters
     */
    public function testAwaitAsyncThrowsExceptionImmediatelyWhenPromiseIsRejected(callable $await): void
    {
        $deferred = new Deferred();

        $ticks = 0;
        Loop::futureTick(function () use (&$ticks) {
            ++$ticks;
            Loop::futureTick(function () use (&$ticks) {
                ++$ticks;
            });
        });

        Loop::futureTick(fn() => $deferred->reject(new \RuntimeException()));

        $promise = async(function () use ($deferred, $await) {
            return $await($deferred->promise());
        })();

        try {
            $await($promise);
        } catch (\RuntimeException $e) {
            $this->assertEquals(1, $ticks);
        }
    }

    /**
     * @dataProvider provideAwaiters
     */
    public function testAwaitThrowsExceptionImmediatelyInCustomFiberWhenPromiseIsRejected(callable $await): void
    {
        $fiber = new \Fiber(function () use ($await) {
            $promise = new Promise(function ($resolve) {
                throw new \RuntimeException('Test');
            });

            return $await($promise);
        });

        try {
            $fiber->start();
        } catch (\RuntimeException $e) {
            $this->assertTrue($fiber->isTerminated());
            $this->assertEquals('Test', $e->getMessage());
        }
    }

    /**
     * @dataProvider provideAwaiters
     */
    public function testAwaitThrowsUnexpectedValueExceptionWhenPromiseIsRejectedWithFalse(callable $await): void
    {
        if (!interface_exists('React\Promise\CancellablePromiseInterface')) {
            $this->markTestSkipped('Promises must be rejected with a \Throwable instance since Promise v3');
        }

        $promise = new Promise(function ($_, $reject) {
            $reject(false); // @phpstan-ignore-line
        });

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Promise rejected with unexpected value of type bool');
        $await($promise);
    }

    /**
     * @dataProvider provideAwaiters
     */
    public function testAwaitThrowsUnexpectedValueExceptionWhenPromiseIsRejectedWithNull(callable $await): void
    {
        if (!interface_exists('React\Promise\CancellablePromiseInterface')) {
            $this->markTestSkipped('Promises must be rejected with a \Throwable instance since Promise v3');
        }

        $promise = new Promise(function ($_, $reject) {
            $reject(null); // @phpstan-ignore-line
        });

        try {
            $await($promise);
        } catch (\UnexpectedValueException $exception) {
            $this->assertInstanceOf(\UnexpectedValueException::class, $exception);
            $this->assertEquals('Promise rejected with unexpected value of type NULL', $exception->getMessage());
            $this->assertEquals(0, $exception->getCode());
            $this->assertNull($exception->getPrevious());
            $this->assertNotEquals('', $exception->getTraceAsString());
        }
    }

    /**
     * @dataProvider provideAwaiters
     */
    public function testAwaitThrowsErrorWhenPromiseIsRejectedWithError(callable $await): void
    {
        $promise = new Promise(function ($_, $reject) {
            throw new \Error('Test', 42);
        });

        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Test');
        $this->expectExceptionCode(42);
        $await($promise);
    }

    /**
     * @dataProvider provideAwaiters
     */
    public function testAwaitReturnsValueWhenPromiseIsFullfilled(callable $await): void
    {
        $promise = new Promise(function ($resolve) {
            $resolve(42);
        });

        $this->assertEquals(42, $await($promise));
    }

    /**
     * @dataProvider provideAwaiters
     */
    public function testAwaitReturnsValueImmediatelyWithoutRunningLoop(callable $await): void
    {
        $now = true;
        Loop::futureTick(function () use (&$now) {
            $now = false;
        });

        $promise = new Promise(function ($resolve) {
            $resolve(42);
        });

        $this->assertEquals(42, $await($promise));
        $this->assertTrue($now);
    }

    /**
     * @dataProvider provideAwaiters
     */
    public function testAwaitReturnsValueImmediatelyWhenPromiseIsFulfilled(callable $await): void
    {
        $deferred = new Deferred();

        $ticks = 0;
        Loop::futureTick(function () use (&$ticks) {
            ++$ticks;
            Loop::futureTick(function () use (&$ticks) {
                ++$ticks;
            });
        });

        Loop::futureTick(fn() => $deferred->resolve(42));

        $this->assertEquals(42, $await($deferred->promise()));
        $this->assertEquals(1, $ticks);
    }

    /**
     * @dataProvider provideAwaiters
     */
    public function testAwaitAsyncReturnsValueImmediatelyWhenPromiseIsFulfilled(callable $await): void
    {
        $deferred = new Deferred();

        $ticks = 0;
        Loop::futureTick(function () use (&$ticks) {
            ++$ticks;
            Loop::futureTick(function () use (&$ticks) {
                ++$ticks;
            });
        });

        Loop::futureTick(fn() => $deferred->resolve(42));

        $promise = async(function () use ($deferred, $await) {
            return $await($deferred->promise());
        })();

        $this->assertEquals(42, $await($promise));
        $this->assertEquals(1, $ticks);
    }

    /**
     * @dataProvider provideAwaiters
     */
    public function testAwaitReturnsValueImmediatelyInCustomFiberWhenPromiseIsFulfilled(callable $await): void
    {
        $fiber = new \Fiber(function () use ($await) {
            $promise = new Promise(function ($resolve) {
                $resolve(42);
            });

            return $await($promise);
        });

        $fiber->start();

        $this->assertTrue($fiber->isTerminated());
        $this->assertEquals(42, $fiber->getReturn());
    }

    /**
     * @dataProvider provideAwaiters
     */
    public function testAwaitShouldNotCreateAnyGarbageReferencesForResolvedPromise(callable $await): void
    {
        if (class_exists('React\Promise\When')) {
            $this->markTestSkipped('Not supported on legacy Promise v1 API');
        }

        gc_collect_cycles();

        $promise = new Promise(function ($resolve) {
            $resolve(42);
        });
        $await($promise);
        unset($promise);

        $this->assertEquals(0, gc_collect_cycles());
    }

    /**
     * @dataProvider provideAwaiters
     */
    public function testAwaitShouldNotCreateAnyGarbageReferencesForRejectedPromise(callable $await): void
    {
        if (class_exists('React\Promise\When')) {
            $this->markTestSkipped('Not supported on legacy Promise v1 API');
        }

        gc_collect_cycles();

        $promise = new Promise(function () {
            throw new \RuntimeException();
        });
        try {
            $await($promise);
        } catch (\Exception $e) {
            // no-op
        }
        unset($promise, $e);

        $this->assertEquals(0, gc_collect_cycles());
    }

    /**
     * @dataProvider provideAwaiters
     */
    public function testAwaitShouldNotCreateAnyGarbageReferencesForPromiseRejectedWithNullValue(callable $await): void
    {
        if (!interface_exists('React\Promise\CancellablePromiseInterface')) {
            $this->markTestSkipped('Promises must be rejected with a \Throwable instance since Promise v3');
        }

        if (class_exists('React\Promise\When')) {
            $this->markTestSkipped('Not supported on legacy Promise v1 API');
        }

        gc_collect_cycles();

        $promise = new Promise(function ($_, $reject) {
            $reject(null); // @phpstan-ignore-line
        });
        try {
            $await($promise);
        } catch (\Exception $e) {
            // no-op
        }
        unset($promise, $e);

        $this->assertEquals(0, gc_collect_cycles());
    }

    /**
     * @dataProvider provideAwaiters
     */
    public function testAlreadyFulfilledPromiseShouldNotSuspendFiber(callable $await): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->assertSame($i, $await(React\Promise\resolve($i)));
        }
    }

    /**
     * @dataProvider provideAwaiters
     */
    public function testNestedAwaits(callable $await): void
    {
        $this->assertTrue($await(new Promise(function ($resolve) use ($await) {
            $resolve($await(new Promise(function ($resolve) use ($await) {
                $resolve($await(new Promise(function ($resolve) use ($await) {
                    $resolve($await(new Promise(function ($resolve) use ($await) {
                        $resolve($await(new Promise(function ($resolve) {
                            Loop::addTimer(0.01, function () use ($resolve) {
                                $resolve(true);
                            });
                        })));
                    })));
                })));
            })));
        })));
    }

    /**
     * @dataProvider provideAwaiters
     */
    public function testResolvedPromisesShouldBeDetached(callable $await): void
    {
        $await(async(function () use ($await): int {
            $fiber = \Fiber::getCurrent();
            assert($fiber instanceof \Fiber);
            $await(new Promise(function ($resolve) {
                Loop::addTimer(0.01, fn() => $resolve(null));
            }));
            $this->assertNull(React\Async\FiberMap::getPromise($fiber));

            return time();
        })());
    }

    /**
     * @dataProvider provideAwaiters
     */
    public function testRejectedPromisesShouldBeDetached(callable $await): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Boom!');

        $await(async(function () use ($await): int {
            $fiber = \Fiber::getCurrent();
            assert($fiber instanceof \Fiber);
            try {
                $await(React\Promise\reject(new \Exception('Boom!')));
            } catch (\Throwable $throwable) {
                throw $throwable;
            } finally {
                $this->assertNull(React\Async\FiberMap::getPromise($fiber));
            }

            return time();
        })());
    }

    /** @return iterable<string,list<callable(PromiseInterface<mixed>): mixed>> */
    public function provideAwaiters(): iterable
    {
        yield 'await' => [static fn (React\Promise\PromiseInterface $promise): mixed => React\Async\await($promise)];
        yield 'async' => [static fn (React\Promise\PromiseInterface $promise): mixed => React\Async\await(React\Async\async(static fn(): mixed => $promise)())];
    }
}
