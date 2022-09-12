<?php

declare(strict_types=1);

namespace Spiral\Queue\Bootloader;

use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Spiral\Boot\AbstractKernel;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\EnvironmentInterface;
use Spiral\Config\ConfiguratorInterface;
use Spiral\Config\Patch\Append;
use Spiral\Core\Container;
use Spiral\Core\Container\Autowire;
use Spiral\Core\CoreInterceptorInterface;
use Spiral\Core\FactoryInterface;
use Spiral\Core\InterceptableCore;
use Spiral\Queue\Config\QueueConfig;
use Spiral\Queue\ContainerRegistry;
use Spiral\Queue\Core\QueueInjector;
use Spiral\Queue\Driver\NullDriver;
use Spiral\Queue\Driver\SyncDriver;
use Spiral\Queue\Failed\FailedJobHandlerInterface;
use Spiral\Queue\Failed\LogFailedJobHandler;
use Spiral\Queue\HandlerRegistryInterface;
use Spiral\Queue\Interceptor\Consume\ErrorHandlerInterceptor;
use Spiral\Queue\Interceptor\Consume\Handler;
use Spiral\Queue\Interceptor\Consume\Core as ConsumeCore;
use Spiral\Queue\Interceptor\Push\Core as PushCore;
use Spiral\Queue\Queue;
use Spiral\Queue\QueueConnectionProviderInterface;
use Spiral\Queue\QueueInterface;
use Spiral\Queue\QueueManager;
use Spiral\Queue\QueueRegistry;
use Spiral\Queue\SerializerRegistryInterface;

final class QueueBootloader extends Bootloader
{
    protected const SINGLETONS = [
        HandlerRegistryInterface::class => QueueRegistry::class,
        SerializerRegistryInterface::class => QueueRegistry::class,
        FailedJobHandlerInterface::class => LogFailedJobHandler::class,
        QueueConnectionProviderInterface::class => QueueManager::class,
        QueueManager::class => [self::class, 'initQueueManager'],
        QueueRegistry::class => [self::class, 'initRegistry'],
        Handler::class => [self::class, 'initHandler'],
        QueueInterface::class => [self::class, 'initQueue'],
    ];

    public function __construct(
        private readonly ConfiguratorInterface $config
    ) {
    }

    public function init(Container $container, EnvironmentInterface $env, AbstractKernel $kernel): void
    {
        $this->initQueueConfig($env);

        $this->registerDriverAlias(SyncDriver::class, 'sync');
        $container->bindInjector(QueueInterface::class, QueueInjector::class);

        $kernel->booted(static function () use ($container): void {
            $registry = $container->get(QueueRegistry::class);
            $config = $container->get(QueueConfig::class);

            foreach ($config->getRegistryHandlers() as $jobType => $handler) {
                $registry->setHandler($jobType, $handler);
            }

            foreach ($config->getRegistrySerializers() as $jobType => $serializer) {
                $registry->setSerializer($jobType, $serializer);
            }
        });
    }

    /**
     * @param class-string<CoreInterceptorInterface>|CoreInterceptorInterface|Autowire $interceptor
     */
    public function addConsumeInterceptor(string|CoreInterceptorInterface|Autowire $interceptor): void
    {
        $this->config->modify(
            QueueConfig::CONFIG,
            new Append('interceptors.consume', null, $interceptor)
        );
    }

    /**
     * @param class-string<CoreInterceptorInterface>|CoreInterceptorInterface|Autowire $interceptor
     */
    public function addPushInterceptor(string|CoreInterceptorInterface|Autowire $interceptor): void
    {
        $this->config->modify(
            QueueConfig::CONFIG,
            new Append('interceptors.push', null, $interceptor)
        );
    }

    public function registerDriverAlias(string $driverClass, string $alias): void
    {
        $this->config->modify(
            QueueConfig::CONFIG,
            new Append('driverAliases', $alias, $driverClass)
        );
    }

    protected function initQueueManager(FactoryInterface $factory): QueueManager
    {
        return $factory->make(QueueManager::class);
    }

    protected function initRegistry(
        ContainerInterface $container,
        FactoryInterface $factory,
        ContainerRegistry $registry
    ) {
        return new QueueRegistry($container, $factory, $registry);
    }

    protected function initHandler(
        ConsumeCore $core,
        QueueConfig $config,
        Container $container,
        ?EventDispatcherInterface $dispatcher = null
    ): Handler {
        $core = new InterceptableCore($core, $dispatcher);

        foreach ($config->getConsumeInterceptors() as $interceptor) {
            if (\is_string($interceptor) || $interceptor instanceof Container\Autowire) {
                $interceptor = $container->get($interceptor);
            }

            $core->addInterceptor($interceptor);
        }

        return new Handler($core);
    }

    protected function initQueue(
        QueueConfig $config,
        QueueConnectionProviderInterface $manager,
        Container $container,
        ?EventDispatcherInterface $dispatcher = null
    ): Queue {
        $core = new InterceptableCore(new PushCore($manager->getConnection()), $dispatcher);

        foreach ($config->getPushInterceptors() as $interceptor) {
            if (\is_string($interceptor) || $interceptor instanceof Container\Autowire) {
                $interceptor = $container->get($interceptor);
            }

            $core->addInterceptor($interceptor);
        }

        return new Queue($core);
    }

    private function initQueueConfig(EnvironmentInterface $env): void
    {
        $this->config->setDefaults(
            QueueConfig::CONFIG,
            [
                'default' => $env->get('QUEUE_CONNECTION', 'sync'),
                'connections' => [
                    'sync' => [
                        'driver' => 'sync',
                    ],
                ],
                'registry' => [
                    'handlers' => [],
                    'serializers' => [],
                ],
                'driverAliases' => [
                    'sync' => SyncDriver::class,
                    'null' => NullDriver::class,
                ],
                'interceptors' => [
                    'consume' => [
                        ErrorHandlerInterceptor::class,
                    ],
                    'push' => [],
                ],
            ]
        );
    }
}
