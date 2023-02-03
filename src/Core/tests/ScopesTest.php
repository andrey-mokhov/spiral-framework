<?php

declare(strict_types=1);

namespace Spiral\Tests\Core;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Spiral\Core\Container;
use Spiral\Core\ContainerScope;
use Spiral\Core\Exception\RuntimeException;
use Spiral\Tests\Core\Fixtures\Bucket;
use Spiral\Tests\Core\Fixtures\SampleClass;

class ScopesTest extends TestCase
{
    public function testScope(): void
    {
        $container = $this->createMock(ContainerInterface::class);

        $this->assertNull(ContainerScope::getContainer());

        $this->assertTrue(ContainerScope::runScope($container, function () use ($container) {
            return $container === ContainerScope::getContainer();
        }));

        $this->assertNull(ContainerScope::getContainer());
    }

    public function testScopeException(): void
    {
        $container = $this->createMock(ContainerInterface::class);

        $this->assertNull(ContainerScope::getContainer());

        try {
            $this->assertTrue(ContainerScope::runScope($container, function () use ($container): void {
                throw new RuntimeException('exception');
            }));
        } catch (\Throwable $e) {
        }

        $this->assertInstanceOf(RuntimeException::class, $e);
        $this->assertNull(ContainerScope::getContainer());
    }

    public function testContainerScope(): void
    {
        $c = new Container();
        $c->bind('bucket', new Bucket('a'));

        $this->assertSame('a', $c->get('bucket')->getName());
        $this->assertFalse($c->has('other'));

        $this->assertTrue($c->runScope([
            'bucket' => new Bucket('b'),
            'other'  => new SampleClass()
        ], function ($c) {
            $this->assertSame('b', $c->get('bucket')->getName());
            $this->assertTrue($c->has('other'));

            return $c->get('bucket')->getName() == 'b' && $c->has('other');
        }));

        $this->assertSame('a', $c->get('bucket')->getName());
        $this->assertFalse($c->has('other'));
    }

    public function testContainerScopeException(): void
    {
        $c = new Container();
        $c->bind('bucket', new Bucket('a'));

        $this->assertSame('a', $c->get('bucket')->getName());
        $this->assertFalse($c->has('other'));

        $this->assertTrue($c->runScope([
            'bucket' => new Bucket('b'),
            'other'  => new SampleClass()
        ], function ($c) {
            $this->assertSame('b', $c->get('bucket')->getName());
            $this->assertTrue($c->has('other'));

            return $c->get('bucket')->getName() == 'b' && $c->has('other');
        }));

        try {
            $this->assertTrue($c->runScope([
                'bucket' => new Bucket('b'),
                'other'  => new SampleClass()
            ], function () use ($c): void {
                throw new RuntimeException('exception');
            }));
        } catch (\Throwable $e) {
        }

        $this->assertSame('a', $c->get('bucket')->getName());
        $this->assertFalse($c->has('other'));
    }

    /* // If runScope() uses scope()
    public function testContainerInScope(): void
    {
        $root = new Container();

        ContainerScope::runScope($root, static function (Container $parent) use ($root) {
            // ::runScope() passes the same container into closure as the first argument
            self::assertSame($parent, $root);

            return $parent->runScope([], static function (Container $c) use ($root, $parent) {
                // Nested container isn't the same as the parent container
                self::assertNotSame($root, $c);
                self::assertNotSame($parent, $c);
            });
        });
    }
    /*/ // Old test
    public function testContainerInScope(): void
    {
        $container = new Container();

        $this->assertSame(
            $container,
            ContainerScope::runScope($container, static fn (ContainerInterface $container) => $container)
        );

        $result = ContainerScope::runScope($container, static function (Container $container) {
            return $container->runScope([], static fn (Container $container) => $container);
        });

        $this->assertSame($container, $result);
    }
    // */
}
