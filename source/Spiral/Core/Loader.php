<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Core;

use Spiral\Core\Container\SingletonInterface;

/**
 * Can speed up class loading in some conditions. Used by profiler to show all loadead classes.
 */
class Loader extends Component implements SingletonInterface
{
    /**
     * List of classes loaded while this working session.
     *
     * @var array
     */
    private $classes = [];

    /**
     * Name of memory sections to be used.
     *
     * @var string
     */
    private $name = 'loadmap';

    /**
     * Association between class and it's location.
     *
     * @var array
     */
    private $loadmap = [];

    /**
     * Is SPL methods handled.
     *
     * @var bool
     */
    private $enabled = false;

    /**
     * @invisible
     * @var MemoryInterface
     */
    protected $memory = null;

    /**
     * Loader will automatically handle SPL autoload functions to start caching loadmap.
     *
     * @param MemoryInterface $memory
     * @param string          $name   Memory section name.
     * @param bool            $enable Automatically enable.
     */
    public function __construct(
        MemoryInterface $memory,
        string $name = 'loadmap',
        bool $enable = true
    ) {
        $this->memory = $memory;
        $this->name = $name;

        if ($enable) {
            $this->enable();
        }
    }

    /**
     * Handle SPL auto location, will go to top of spl chain.
     *
     * @return $this|self
     */
    public function enable(): Loader
    {
        if ($this->enabled) {
            return $this;
        }

        if (!empty($this->name)) {
            $this->loadmap = (array)$this->memory->loadData($this->name);
        }

        spl_autoload_register([$this, 'loadClass'], true, true);
        $this->enabled = true;

        return $this;
    }

    /**
     * Stop handling SPL calls.
     *
     * @return $this|self
     */
    public function disable(): Loader
    {
        if ($this->enabled) {
            spl_autoload_unregister([$this, 'loadClass']);
        }

        $this->enabled = false;

        return $this;
    }

    /**
     * Re-enable autoload to push it up into food chain.
     */
    public function reset()
    {
        return $this->disable()->enable();
    }

    /**
     * Set loadmap name (section to be used).
     *
     * @param string $name
     */
    public function setName(string $name)
    {
        if ($this->name != $name && !empty($name)) {
            if (empty($this->loadmap = (array)$this->memory->loadData($name))) {
                $this->loadmap = [];
            }
        }

        $this->name = $name;
    }

    /**
     * Find class declaration and load it.
     *
     * @param string $class Class name with namespace included.
     *
     * @return bool
     */
    public function loadClass(string $class): bool
    {
        if (isset($this->loadmap[$class])) {
            try {
                //We already know route to class declaration
                include_once($this->classes[$class] = $this->loadmap[$class]);
            } catch (\Throwable $exception) {
                //File was replaced or removed
                unset($this->loadmap[$class]);

                if (!empty($this->name)) {
                    $this->memory->saveData($this->name, $this->loadmap);
                }

                //Try to update route to class
                return $this->loadClass($class);
            }

            return true;
        }

        //Composer and other loaders.
        foreach (spl_autoload_functions() as $function) {
            if ($function instanceof \Closure || $function[0] != $this) {
                //Calling loaders
                call_user_func($function, $class);

                if (
                    class_exists($class, false)
                    || interface_exists($class, false)
                    || trait_exists($class, false)
                ) {
                    //Class has been successfully found by external loader
                    //External loader are not going to provide us any information about class
                    //location, let's get it via Reflection
                    $reflector = new \ReflectionClass($class);

                    try {
                        $filename = str_replace('\\', '/', $reflector->getFileName());
                        $filename = rtrim(str_replace('//', '/', $filename), '/');

                        //Direct access to filesystem, yep.
                        if (file_exists($filename)) {
                            $this->loadmap[$class] = $this->classes[$class] = $filename;
                            $this->memory->saveData($this->name, $this->loadmap);
                        }
                    } catch (\Throwable $exception) {
                        //getFileName can throw and exception, we can ignore it
                    }

                    //Class found
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * All loaded classes.
     *
     * @return array
     */
    public function getClasses(): array
    {
        return $this->classes;
    }

    /**
     * Check if desired class exists in loadmap.
     *
     * @param string $class
     *
     * @return bool
     */
    public function isKnown(string $class): bool
    {
        return isset($this->classes[$class]);
    }
}
