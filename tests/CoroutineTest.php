<?php

namespace React\Tests\Async;

use React\Promise\Promise;
use function React\Async\coroutine;
use function React\Promise\reject;
use function React\Promise\resolve;

class CoroutineTest extends TestCase
{
    public function testCoroutineReturnsFulfilledPromiseIfFunctionReturnsWithoutGenerator()
    {
        $promise = coroutine(function () {
            return 42;
        });

        $promise->then($this->expectCallableOnceWith(42));
    }

    public function testCoroutineReturnsFulfilledPromiseIfFunctionReturnsImmediately()
    {
        $promise = coroutine(function () {
            if (false) {
                yield;
            }
            return 42;
        });

        $promise->then($this->expectCallableOnceWith(42));
    }

    public function testCoroutineReturnsFulfilledPromiseIfFunctionReturnsAfterYieldingPromise()
    {
        $promise = coroutine(function () {
            $value = yield resolve(42);
            return $value;
        });

        $promise->then($this->expectCallableOnceWith(42));
    }

    public function testCoroutineReturnsRejectedPromiseIfFunctionThrowsWithoutGenerator()
    {
        $promise = coroutine(function () {
            throw new \RuntimeException('Foo');
        });

        $promise->then(null, $this->expectCallableOnceWith(new \RuntimeException('Foo')));
    }

    public function testCoroutineReturnsRejectedPromiseIfFunctionThrowsImmediately()
    {
        $promise = coroutine(function () {
            if (false) {
                yield;
            }
            throw new \RuntimeException('Foo');
        });

        $promise->then(null, $this->expectCallableOnceWith(new \RuntimeException('Foo')));
    }

    public function testCoroutineReturnsRejectedPromiseIfFunctionThrowsAfterYieldingPromise()
    {
        $promise = coroutine(function () {
            $reason = yield resolve('Foo');
            throw new \RuntimeException($reason);
        });

        $promise->then(null, $this->expectCallableOnceWith(new \RuntimeException('Foo')));
    }

    public function testCoroutineReturnsRejectedPromiseIfFunctionThrowsAfterYieldingRejectedPromise()
    {
        $promise = coroutine(function () {
            try {
                yield reject(new \OverflowException('Foo'));
            } catch (\OverflowException $e) {
                throw new \RuntimeException($e->getMessage());
            }
        });

        $promise->then(null, $this->expectCallableOnceWith(new \RuntimeException('Foo')));
    }

    public function testCoroutineReturnsFulfilledPromiseIfFunctionReturnsAfterYieldingRejectedPromise()
    {
        $promise = coroutine(function () {
            try {
                yield reject(new \OverflowException('Foo', 42));
            } catch (\OverflowException $e) {
                return $e->getCode();
            }
        });

        $promise->then($this->expectCallableOnceWith(42));
    }

    public function testCoroutineReturnsRejectedPromiseIfFunctionYieldsInvalidValue()
    {
        $promise = coroutine(function () {
            yield 42;
        });

        $promise->then(null, $this->expectCallableOnceWith(new \UnexpectedValueException('Expected coroutine to yield React\Promise\PromiseInterface, but got integer')));
    }

    public function testCancelCoroutineWillReturnRejectedPromiseWhenCancellingPendingPromiseRejects()
    {
        $promise = coroutine(function () {
            yield new Promise(function () { }, function () {
                throw new \RuntimeException('Operation cancelled');
            });
        });

        assert(method_exists($promise, 'cancel'));
        $promise->cancel();

        $promise->then(null, $this->expectCallableOnceWith(new \RuntimeException('Operation cancelled')));
    }

    public function testCancelCoroutineWillReturnFulfilledPromiseWhenCancellingPendingPromiseRejectsInsideCatchThatReturnsValue()
    {
        $promise = coroutine(function () {
            try {
                yield new Promise(function () { }, function () {
                    throw new \RuntimeException('Operation cancelled');
                });
            } catch (\RuntimeException $e) {
                return 42;
            }
        });

        assert(method_exists($promise, 'cancel'));
        $promise->cancel();

        $promise->then($this->expectCallableOnceWith(42));
    }

    public function testCancelCoroutineWillReturnPendigPromiseWhenCancellingFirstPromiseRejectsInsideCatchThatYieldsSecondPromise()
    {
        $promise = coroutine(function () {
            try {
                yield new Promise(function () { }, function () {
                    throw new \RuntimeException('First operation cancelled');
                });
            } catch (\RuntimeException $e) {
                yield new Promise(function () { }, function () {
                    throw new \RuntimeException('Second operation never cancelled');
                });
            }
        });

        assert(method_exists($promise, 'cancel'));
        $promise->cancel();

        $promise->then($this->expectCallableNever(), $this->expectCallableNever());
    }

    public function testCoroutineShouldNotCreateAnyGarbageReferencesWhenGeneratorReturns()
    {
        if (class_exists('React\Promise\When')) {
            $this->markTestSkipped('Not supported on legacy Promise v1 API');
        }

        gc_collect_cycles();
        gc_collect_cycles();

        $promise = coroutine(function () {
            if (false) {
                yield;
            }
            return 42;
        });

        unset($promise);

        $this->assertEquals(0, gc_collect_cycles());
    }

    public function testCoroutineShouldNotCreateAnyGarbageReferencesForPromiseRejectedWithExceptionImmediately()
    {
        if (class_exists('React\Promise\When')) {
            $this->markTestSkipped('Not supported on legacy Promise v1 API');
        }

        gc_collect_cycles();

        $promise = coroutine(function () {
            yield new Promise(function () {
                throw new \RuntimeException('Failed', 42);
            });
        });

        unset($promise);

        $this->assertEquals(0, gc_collect_cycles());
    }

    public function testCoroutineShouldNotCreateAnyGarbageReferencesForPromiseRejectedWithExceptionOnCancellation()
    {
        if (class_exists('React\Promise\When')) {
            $this->markTestSkipped('Not supported on legacy Promise v1 API');
        }

        gc_collect_cycles();

        $promise = coroutine(function () {
            yield new Promise(function () { }, function () {
                throw new \RuntimeException('Operation cancelled', 42);
            });
        });

        assert(method_exists($promise, 'cancel'));
        $promise->cancel();
        unset($promise);

        $this->assertEquals(0, gc_collect_cycles());
    }

    public function testCoroutineShouldNotCreateAnyGarbageReferencesWhenGeneratorThrowsBeforeFirstYield()
    {
        if (class_exists('React\Promise\When')) {
            $this->markTestSkipped('Not supported on legacy Promise v1 API');
        }

        gc_collect_cycles();

        $promise = coroutine(function () {
            throw new \RuntimeException('Failed', 42);
            yield;
        });

        unset($promise);

        $this->assertEquals(0, gc_collect_cycles());
    }

    public function testCoroutineShouldNotCreateAnyGarbageReferencesWhenGeneratorYieldsInvalidValue()
    {
        if (class_exists('React\Promise\When')) {
            $this->markTestSkipped('Not supported on legacy Promise v1 API');
        }

        gc_collect_cycles();

        $promise = coroutine(function () {
            yield 42;
        });

        unset($promise);

        $this->assertEquals(0, gc_collect_cycles());
    }
}
