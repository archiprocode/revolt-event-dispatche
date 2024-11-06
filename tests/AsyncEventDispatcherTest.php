<?php

declare(strict_types=1);

namespace ArchiPro\EventDispatcher\Tests;

use ArchiPro\EventDispatcher\AsyncEventDispatcher;
use ArchiPro\EventDispatcher\ListenerProvider;
use ArchiPro\EventDispatcher\Tests\Fixture\TestEvent;
use PHPUnit\Framework\TestCase;
use Revolt\EventLoop;

/**
 * Test cases for AsyncEventDispatcher.
 *
 * Verifies the asynchronous event dispatching functionality, including:
 * - Multiple listener execution
 * - Event propagation stopping
 * - Handling events with no listeners
 *
 * @covers \ArchiPro\EventDispatcher\AsyncEventDispatcher
 */
class AsyncEventDispatcherTest extends TestCase
{
    private ListenerProvider $listenerProvider;
    private AsyncEventDispatcher $dispatcher;

    /**
     * Sets up the test environment before each test.
     */
    protected function setUp(): void
    {
        $this->listenerProvider = new ListenerProvider();
        $this->dispatcher = new AsyncEventDispatcher($this->listenerProvider);

        EventLoop::setErrorHandler(function (\Throwable $err) {
            throw $err;
        });

    }

    /**
     * Tests that multiple listeners for an event are executed.
     */
    public function testDispatchEventToMultipleListeners(): void
    {
        $results = [];
        $completed = false;

        $this->listenerProvider->addListener(TestEvent::class, function (TestEvent $event) use (&$results) {
            EventLoop::delay(0.1, fn () => null);
            $results[] = 'listener1: ' . $event->data;
        });

        $this->listenerProvider->addListener(TestEvent::class, function (TestEvent $event) use (&$results, &$completed) {
            EventLoop::delay(0.05, fn () => null);
            $results[] = 'listener2: ' . $event->data;
            $completed = true;
        });

        $event = new TestEvent('test data');
        $this->dispatcher->dispatch($event);

        // Verify immediate return
        $this->assertEmpty($results);

        // Run the event loop until listeners complete
        EventLoop::run();

        $this->assertTrue($completed);
        $this->assertCount(2, $results);
        $this->assertContains('listener1: test data', $results);
        $this->assertContains('listener2: test data', $results);
    }

    /**
     * Tests that event propagation can be stopped synchronously.
     */
    public function testSynchronousStoppableEvent(): void
    {
        $results = [];

        $this->listenerProvider->addListener(TestEvent::class, function (TestEvent $event) use (&$results) {
            $results[] = 'listener1';
            $event->stopPropagation();
        });

        $this->listenerProvider->addListener(TestEvent::class, function (TestEvent $event) use (&$results) {
            $results[] = 'listener2';
        });

        $event = new TestEvent('test data');
        $this->dispatcher->dispatch($event);
        EventLoop::run();

        $this->assertCount(1, $results);
        $this->assertEquals(['listener1'], $results);
    }

    /**
     * Tests handling of events with no registered listeners.
     */
    public function testNoListenersForEvent(): void
    {
        $event = new TestEvent('test data');
        $dispatchedEvent = $this->dispatcher->dispatch($event);

        $this->assertSame($event, $dispatchedEvent);
    }

    /**
     * @test
     */
    public function testDispatchesNonStoppableEvents(): void
    {
        $event = new class () {
            public bool $called = false;
        };

        $listener = function ($event) {
            $event->called = true;
        };

        $this->listenerProvider->addListener(get_class($event), $listener);
        $this->dispatcher->dispatch($event);

        $this->assertFalse($event->called, 'Listener should not have been called right away');

        EventLoop::run();

        $this->assertTrue($event->called, 'Listener should have been called for non-stoppable event');
    }

}
