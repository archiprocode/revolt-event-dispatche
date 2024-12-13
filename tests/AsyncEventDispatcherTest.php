<?php

declare(strict_types=1);

namespace ArchiPro\EventDispatcher\Tests;

use Amp\CancelledException;

use function Amp\delay;

use Amp\TimeoutCancellation;
use ArchiPro\EventDispatcher\AsyncEventDispatcher;
use ArchiPro\EventDispatcher\Event\AbstractStoppableEvent;
use ArchiPro\EventDispatcher\ListenerProvider;
use ArchiPro\EventDispatcher\Tests\Fixture\TestEvent;
use Exception;
use PHPUnit\Framework\TestCase;
use Throwable;

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

    /** @var array<Throwable> */
    private array $errors = [];

    /**
     * Sets up the test environment before each test.
     */
    protected function setUp(): void
    {
        $this->listenerProvider = new ListenerProvider();
        $this->dispatcher = new AsyncEventDispatcher(
            $this->listenerProvider,
            function (Throwable $exception) {
                $this->errors[] = $exception;
            }
        );
    }

    /**
     * Tests that multiple listeners for an event are executed.
     */
    public function testDispatchEventToMultipleListeners(): void
    {
        $results = [];
        $completed = false;

        $this->listenerProvider->addListener(TestEvent::class, function (TestEvent $event) use (&$results) {
            delay(0.1);
            $results[] = 'listener1: ' . $event->data;
        });

        $this->listenerProvider->addListener(TestEvent::class, function (TestEvent $event) use (&$results, &$completed) {
            delay(0.05);
            $results[] = 'listener2: ' . $event->data;
            $completed = true;
        });

        $event = new TestEvent('test data');
        $futureEvent = $this->dispatcher->dispatch($event);

        // Verify immediate return
        $this->assertEmpty($results);

        // Run the event loop until listeners complete
        $futureEvent->await();

        $this->assertTrue($completed);
        $this->assertCount(2, $results);
        $this->assertContains('listener1: test data', $results);
        $this->assertContains('listener2: test data', $results);

        $this->assertCount(0, $this->errors, 'No errors are logged');
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
        $this->dispatcher->dispatch($event)->await();

        $this->assertCount(1, $results);
        $this->assertEquals(['listener1'], $results);

        $this->assertCount(0, $this->errors, 'No errors are logged');
    }

    /**
     * Tests handling of events with no registered listeners.
     */
    public function testNoListenersForEvent(): void
    {
        $event = new TestEvent('test data');
        $dispatchedEvent = $this->dispatcher->dispatch($event);

        $this->assertSame($event, $dispatchedEvent->await());
        $this->assertCount(0, $this->errors, 'No errors are logged');
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
        $futureEvent = $this->dispatcher->dispatch($event);

        $this->assertFalse($event->called, 'Listener should not have been called right away');

        $futureEvent->await();

        $this->assertTrue($event->called, 'Listener should have been called for non-stoppable event');
    }

    public function testDispatchesFailureInOneListenerDoesNotAffectOthers(): void
    {
        $event = new class () {
            public bool $calledOnce = false;
            public bool $calledTwice = false;
        };

        $this->listenerProvider->addListener(get_class($event), function ($event) {
            $event->calledOnce = true;
            throw new Exception('Test exception');
        });

        $this->listenerProvider->addListener(get_class($event), function ($event) {
            $event->calledTwice = true;
            throw new Exception('Test exception');
        });

        $futureEvent = $this->dispatcher->dispatch($event);

        $futureEvent->await();

        $this->assertTrue(
            $event->calledOnce,
            'The first listener should have been called'
        );
        $this->assertTrue(
            $event->calledTwice,
            'The second listener should have been called despite the failure of the first listener'
        );

        $this->assertCount(2, $this->errors, 'Errors are caught for both listeners');
    }

    public function testCancellationOfStoppableEvent(): void
    {
        $event = new class () extends AbstractStoppableEvent {
            public bool $called = false;
        };

        $this->listenerProvider->addListener(get_class($event), function ($event) {
            // Simulate a long-running operation
            delay(0.1);
            $event->called = true;
        });

        $cancellation = new TimeoutCancellation(0.05);

        $this->expectException(CancelledException::class);

        $this->dispatcher->dispatch($event, $cancellation)->await();

        $this->assertCount(0, $this->errors, 'No errors are caught');
    }

    public function testCancellationOfNonStoppableEvent(): void
    {
        $event = new class () {
            public bool $called = false;
        };

        $this->listenerProvider->addListener(get_class($event), function ($event) {
            // Simulate a long-running operation
            delay(0.1);
            $event->called = true;
        });

        $cancellation = new TimeoutCancellation(0.05);

        $this->expectException(CancelledException::class);

        $this->dispatcher->dispatch($event, $cancellation)->await();

        $this->assertCount(0, $this->errors, 'No errors are caught');
    }
}
