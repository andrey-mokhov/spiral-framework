<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Core\Traits;

use Interop\Container\ContainerInterface as InteropContainer;
use Spiral\Core\ContainerInterface;
use Spiral\Core\Exceptions\Container\AutowireException;
use Spiral\Core\Exceptions\Container\ContainerException;
use Spiral\Core\Exceptions\SugarException;

/**
 * Trait provides access to set of shared components (using short bindings). You can create virtual
 * copies of this trait to let IDE know about your bindings (works in PHPStorm).
 */
trait SharedTrait
{
    use SaturateTrait;

    /**
     * @invisible
     * @var ContainerInterface
     */
    protected $container = null;

    /**
     * @param ContainerInterface $container Sugared.
     */
    public function __construct(ContainerInterface $container = null)
    {
        $this->container = $this->saturate($container, ContainerInterface::class);
    }

    /**
     * Shortcut to Container get method.
     *
     * @see ContainerInterface::get()
     * @param string $alias
     * @return mixed|null|object
     * @throws AutowireException
     * @throws SugarException
     */
    public function __get($alias)
    {
        /**
         * Shared trait do not use
         */
        if ($this->container()->has($alias)) {
            return $this->container()->get($alias);
        }

        throw new SugarException("Unable to get property binding '{$alias}'.");

        //no parent call, too dangerous
    }
}