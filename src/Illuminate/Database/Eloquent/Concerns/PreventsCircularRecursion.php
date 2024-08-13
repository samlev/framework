<?php

namespace Illuminate\Database\Eloquent\Concerns;

use Illuminate\Support\Arr;
use Illuminate\Support\Onceable;

trait PreventsCircularRecursion
{
    /**
     * The cache of objects processed to prevent infinite recursion.
     *
     * @var \WeakMap<static, array<string, mixed>>
     */
    protected static $recursionCache;

    /**
     * Get the current recursion cache being used by the model.
     *
     * @return \WeakMap
     */
    protected static function getRecursionCache()
    {
        return static::$recursionCache ??= new \WeakMap();
    }

    /**
     * Get the call stack cache for this object.
     *
     * @return array
     */
    protected function getCallStack(): array
    {
        return static::getRecursionCache()->offsetExists($this)
            ? static::getRecursionCache()->offsetGet($this)
            : [];
    }

    /**
     * Checks if a value exists for a key in the call stack cache for this object.
     *
     * @param  string  $key
     * @return bool
     */
    protected function hasCallStackValue(string $key): bool
    {
        return array_key_exists($key, $this->getCallStack());
    }

    /**
     * Gets the current value for a key in the call stack cache for this object.
     *
     * @param  string  $key
     * @return mixed
     */
    protected function getCallStackValue(string $key)
    {
        return $this->getCallStack()[$key] ?? null;
    }

    /**
     * Set the value of for a key in the call stack cache for this object.
     *
     * @param  string  $key
     * @param  mixed  $value
     */
    protected function setCallStackValue(string $key, mixed $value): void
    {
        static::getRecursionCache()->offsetSet(
            $this,
            tap($this->getCallStack(), fn (&$stack) => $stack[$key] = $value)
        );
    }

    /**
     * Clears a value for a key from the call stack cache for this object.
     *
     * @param  string  $key
     */
    protected function unsetCallStackValue(string $key): void
    {
        if ($stack = Arr::except($this->getCallStack(), $key)) {
            static::getRecursionCache()->offsetSet($this, $stack);
        } else {
            $this->unsetCallStack();
        }
    }

    /**
     * Clear the call stack cache for this object.
     */
    protected function unsetCallStack(): void
    {
        if (static::getRecursionCache()->offsetExists($this)) {
            static::getRecursionCache()->offsetUnset($this);
        }
    }

    /**
     * Creates a Onceable instance for the first method call in the stack from outside of this file.
     *
     * @param  callable|null  $callback
     * @return Onceable
     */
    protected function onceable($callback = null): Onceable
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 4);

        do {
            $target = $trace[0];
            $caller = $trace[1] ?? $trace[0];
            $file = $target['file'] ?? '';

            if ($file === __FILE__) {
                array_shift($trace);
            } else {
                break;
            }
        } while (count($trace));

        $hash = hash('xxh128', sprintf(
            '%s@%s@%s',
            $file,
            $caller['class'],
            $caller['function'],
        ));

        return new Onceable($hash, $this, $callback ?? fn () => true);
    }

    /**
     * Checks if a function was called recursively.
     *
     * @return bool
     */
    protected function wasCalledRecursively(): bool
    {
        $onceable = $this->onceable();

        if ($this->hasCallStackValue($onceable->hash)) {
            return true;
        }

        $this->setCallStackValue($onceable->hash, true);

        return false;
    }

    /**
     * Releases a recursive lock on a function.
     */
    protected function releaseRecursiveLock(): void
    {
        $onceable = $this->onceable(fn () => true);

        $this->unsetCallStackValue($onceable->hash);
    }

    /**
     * Prevent a method from being called multiple times on the same object within the same call stack.
     *
     * @param  callable  $callback
     * @param  mixed  $onRecursion
     * @return mixed
     */
    protected function once($callback, $onRecursion = null)
    {
        if ($this->wasCalledRecursively()) {
            return $onRecursion;
        }

        try {
            return call_user_func($callback);
        } finally {
            $this->releaseRecursiveLock();
        }
    }
}
