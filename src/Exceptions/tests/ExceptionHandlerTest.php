<?php

declare(strict_types=1);

namespace Spiral\Tests\Exceptions;

use Mockery as m;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Spiral\Exceptions\ExceptionHandler;
use Spiral\Exceptions\ExceptionRendererInterface;
use Spiral\Exceptions\ExceptionReporterInterface;

class ExceptionHandlerTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
    }

    public function testKernelException(): void
    {
        $handler = $this->makeErrorHandler();
        $output = \fopen('php://memory', 'rwb');
        $handler->setOutput($output);
        $handler->handleGlobalException(new \Exception('Test message'));

        $handler->setOutput(STDERR);

        \fseek($output, 0);
        $this->assertStringContainsString('Test message', \fread($output, 10000));
    }

    public function testDefaultErrorRenderer(): void
    {
        $r1 = m::mock(ExceptionRendererInterface::class);
        $r2 = m::mock(ExceptionRendererInterface::class);
        $r3 = m::mock(ExceptionRendererInterface::class);
        $handler = $this->makeEmptyErrorHandler();
        $handler->addRenderer($r1);
        $handler->addRenderer($r2);
        $handler->addRenderer($r3);

        $this->assertSame($r1, $handler->getRenderer());
    }

    public function testDefaultErrorRendererFromEmptyExceptionHandler(): void
    {
        $handler = $this->makeEmptyErrorHandler();

        $this->assertNull($handler->getRenderer());
    }

    public function testErrorHandlerByFormat(): void
    {
        $r0 = m::mock(ExceptionRendererInterface::class);
        $r1 = m::mock(ExceptionRendererInterface::class);
        $r1->shouldReceive('canRender')->withArgs(['test'])->andReturnTrue();
        $r2 = m::mock(ExceptionRendererInterface::class);
        $r2->shouldReceive('canRender')->withArgs(['test'])->andReturnFalse();
        $r3 = m::mock(ExceptionRendererInterface::class);
        $r3->shouldReceive('canRender')->withArgs(['test'])->andReturnFalse();
        $handler = $this->makeEmptyErrorHandler();
        $handler->addRenderer($r0);
        $handler->addRenderer($r1);
        $handler->addRenderer($r2);
        $handler->addRenderer($r3);

        $this->assertSame($r1, $handler->getRenderer('test'));
    }

    public function testAllReportersShouldBeCalled(): void
    {
        $exception = new \Exception();

        $r1 = m::mock(ExceptionReporterInterface::class);
        $r1->shouldReceive('report')->withArgs([$exception])->once();
        $r2 = m::mock(ExceptionReporterInterface::class);
        $r2->shouldReceive('report')->withArgs([$exception])->once();
        $r3 = m::mock(ExceptionReporterInterface::class);
        $r3->shouldReceive('report')->withArgs([$exception])->once()->andThrows(new RuntimeException());
        $r4 = m::mock(ExceptionReporterInterface::class);
        $r4->shouldReceive('report')->withArgs([$exception])->once();
        $handler = $this->makeEmptyErrorHandler();
        $handler->addReporter($r1);
        $handler->addReporter($r2);
        $handler->addReporter($r3);
        $handler->addReporter($r4);

        $handler->report($exception);
        $this->assertTrue(true);
    }

    private function makeEmptyErrorHandler(): ExceptionHandler
    {
        return new class extends ExceptionHandler {
            protected function bootBasicHandlers(): void
            {
            }
        };
    }

    private function makeErrorHandler(): ExceptionHandler
    {
        return new ExceptionHandler();
    }
}
