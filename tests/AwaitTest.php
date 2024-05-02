<?php

namespace React\Tests\Async;

use React;
use React\EventLoop\Loop;
use React\Promise\Promise;

class AwaitTest extends TestCase
{
    public function testAwaitThrowsExceptionWhenPromiseIsRejectedWithException(): void
    {
        $promise = new Promise(function () {
            throw new \Exception('test');
        });

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('test');
        React\Async\await($promise);
    }

    public function testAwaitThrowsUnexpectedValueExceptionWhenPromiseIsRejectedWithFalse(): void
    {
        if (!interface_exists('React\Promise\CancellablePromiseInterface')) {
            $this->markTestSkipped('Promises must be rejected with a \Throwable instance since Promise v3');
        }

        $promise = new Promise(function ($_, $reject) {
            $reject(false); // @phpstan-ignore-line
        });

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Promise rejected with unexpected value of type bool');
        React\Async\await($promise);
    }

    public function testAwaitThrowsUnexpectedValueExceptionWhenPromiseIsRejectedWithNull(): void
    {
        if (!interface_exists('React\Promise\CancellablePromiseInterface')) {
            $this->markTestSkipped('Promises must be rejected with a \Throwable instance since Promise v3');
        }

        $promise = new Promise(function ($_, $reject) {
            $reject(null); // @phpstan-ignore-line
        });

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Promise rejected with unexpected value of type NULL');
        React\Async\await($promise);
    }

    public function testAwaitThrowsErrorWhenPromiseIsRejectedWithError(): void
    {
        $promise = new Promise(function ($_, $reject) {
            throw new \Error('Test', 42);
        });

        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Test');
        $this->expectExceptionCode(42);
        React\Async\await($promise);
    }

    public function testAwaitReturnsValueWhenPromiseIsFullfilled(): void
    {
        $promise = new Promise(function ($resolve) {
            $resolve(42);
        });

        $this->assertEquals(42, React\Async\await($promise));
    }

    public function testAwaitReturnsValueWhenPromiseIsFulfilledEvenWhenOtherTimerStopsLoop(): void
    {
        $promise = new Promise(function ($resolve) {
            Loop::addTimer(0.02, function () use ($resolve) {
                $resolve(2);
            });
        });
        Loop::addTimer(0.01, function () {
            Loop::stop();
        });

        $this->assertEquals(2, React\Async\await($promise));
    }

    public function testAwaitWithAlreadyFulfilledPromiseWillReturnWithoutRunningLoop(): void
    {
        $now = true;

        Loop::futureTick(function () use (&$now) {
            $now = false;
        });

        $promise = new Promise(function ($resolve) {
            $resolve(42);
        });

        React\Async\await($promise);
        $this->assertTrue($now);
    }

    public function testAwaitWithAlreadyFulfilledPromiseWillReturnWithoutStoppingLoop(): void
    {
        $ticks = 0;

        $promise = new Promise(function ($resolve) {
            $resolve(42);
        });

        // Loop will execute this tick first
        Loop::futureTick(function () use (&$ticks) {
            ++$ticks;
            // Loop will execute this tick third
            Loop::futureTick(function () use (&$ticks) {
                ++$ticks;
            });
        });

        // Loop will execute this tick second
        Loop::futureTick(function () use (&$promise){
            // await won't stop the loop if promise already resolved -> third tick will trigger
            React\Async\await($promise);
        });

        Loop::run();

        $this->assertEquals(2, $ticks);
    }

    public function testAwaitWithPendingPromiseThatWillResolveWillStopLoopBeforeLastTimerFinishes(): void
    {
        $promise = new Promise(function ($resolve) {
            Loop::addTimer(0.02, function () use ($resolve) {
                $resolve(2);
            });
        });

        $ticks = 0;

        // Loop will execute this tick first
        Loop::futureTick(function () use (&$ticks) {
            ++$ticks;
            // This timer will never finish because Loop gets stopped by await
            // Loop needs to be manually started again to finish this timer
            Loop::addTimer(0.04, function () use (&$ticks) {
                ++$ticks;
            });
        });

        // await stops the loop when promise resolves after 0.02s
        Loop::futureTick(function () use (&$promise){
            React\Async\await($promise);
        });

        Loop::run();

        // This bahvior exists in v2 & v3 of async, we recommend to use fibers in v4 (PHP>=8.1)
        $this->assertEquals(1, $ticks);
    }

    public function testAwaitWithAlreadyRejectedPromiseWillReturnWithoutStoppingLoop(): void
    {
        $ticks = 0;

        $promise = new Promise(function ($_, $reject) {
            throw new \Exception();
        });

        // Loop will execute this tick first
        Loop::futureTick(function () use (&$ticks) {
            ++$ticks;
            // Loop will execute this tick third
            Loop::futureTick(function () use (&$ticks) {
                ++$ticks;
            });
        });

        // Loop will execute this tick second
        Loop::futureTick(function () use (&$promise){
            try {
                // await won't stop the loop if promise already rejected -> third tick will trigger
                React\Async\await($promise);
            } catch (\Exception $e) {
                // no-op
            }
        });

        Loop::run();

        $this->assertEquals(2, $ticks);
    }

    public function testAwaitWithPendingPromiseThatWillRejectWillStopLoopBeforeLastTimerFinishes(): void
    {
        $promise = new Promise(function ($_, $reject) {
            Loop::addTimer(0.02, function () use (&$reject) {
                $reject(new \Exception());
            });
        });

        $ticks = 0;

        // Loop will execute this tick first
        Loop::futureTick(function () use (&$ticks) {
            ++$ticks;
            // This timer will never finish because Loop gets stopped by await
            // Loop needs to be manually started again to finish this timer
            Loop::addTimer(0.04, function () use (&$ticks) {
                ++$ticks;
            });
        });

        // Loop will execute this tick second
        // await stops the loop when promise rejects after 0.02s
        Loop::futureTick(function () use (&$promise){
            try {
                React\Async\await($promise);
            } catch (\Exception $e) {
                // no-op
            }
        });

        Loop::run();

        // This bahvior exists in v2 & v3 of async, we recommend to use fibers in v4 (PHP>=8.1)
        $this->assertEquals(1, $ticks);
    }

    public function testAwaitShouldNotCreateAnyGarbageReferencesForResolvedPromise(): void
    {
        if (class_exists('React\Promise\When')) {
            $this->markTestSkipped('Not supported on legacy Promise v1 API');
        }

        gc_collect_cycles();

        $promise = new Promise(function ($resolve) {
            $resolve(42);
        });
        React\Async\await($promise);
        unset($promise);

        $this->assertEquals(0, gc_collect_cycles());
    }

    public function testAwaitShouldNotCreateAnyGarbageReferencesForRejectedPromise(): void
    {
        if (class_exists('React\Promise\When')) {
            $this->markTestSkipped('Not supported on legacy Promise v1 API');
        }

        gc_collect_cycles();

        $promise = new Promise(function () {
            throw new \RuntimeException();
        });
        try {
            React\Async\await($promise);
        } catch (\Exception $e) {
            // no-op
        }
        unset($promise, $e);

        $this->assertEquals(0, gc_collect_cycles());
    }

    public function testAwaitShouldNotCreateAnyGarbageReferencesForPromiseRejectedWithNullValue(): void
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
            React\Async\await($promise);
        } catch (\Exception $e) {
            // no-op
        }
        unset($promise, $e);

        $this->assertEquals(0, gc_collect_cycles());
    }
}
