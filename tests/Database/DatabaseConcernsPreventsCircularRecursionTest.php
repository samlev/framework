<?php

namespace Illuminate\Tests\Database;

use Illuminate\Database\Eloquent\Concerns\PreventsCircularRecursion;
use PHPUnit\Framework\TestCase;

class DatabaseConcernsPreventsCircularRecursionTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        PreventsCircularRecursionWithRecursiveMethod::$globalStack = 0;
    }

    public function testRecursiveCallsArePreventedWithoutPreventingSubsequentCalls()
    {
        $instance = new PreventsCircularRecursionWithRecursiveMethod();

        $this->assertEquals(0, PreventsCircularRecursionWithRecursiveMethod::$globalStack);
        $this->assertEquals(0, $instance->instanceStack);

        $this->assertEquals(1, $instance->callStack());
        $this->assertEquals(1, PreventsCircularRecursionWithRecursiveMethod::$globalStack);
        $this->assertEquals(1, $instance->instanceStack);

        $this->assertEquals(2, $instance->callStack());
        $this->assertEquals(2, PreventsCircularRecursionWithRecursiveMethod::$globalStack);
        $this->assertEquals(2, $instance->instanceStack);
    }

    public function testRecursiveCallsAreLimitedToIndividualInstances()
    {
        $instance = new PreventsCircularRecursionWithRecursiveMethod();
        $other = $instance->other;

        $this->assertEquals(0, PreventsCircularRecursionWithRecursiveMethod::$globalStack);
        $this->assertEquals(0, $instance->instanceStack);
        $this->assertEquals(0, $other->instanceStack);

        $instance->callStack();
        $this->assertEquals(1, PreventsCircularRecursionWithRecursiveMethod::$globalStack);
        $this->assertEquals(1, $instance->instanceStack);
        $this->assertEquals(0, $other->instanceStack);

        $instance->callStack();
        $this->assertEquals(2, PreventsCircularRecursionWithRecursiveMethod::$globalStack);
        $this->assertEquals(2, $instance->instanceStack);
        $this->assertEquals(0, $other->instanceStack);

        $other->callStack();
        $this->assertEquals(3, PreventsCircularRecursionWithRecursiveMethod::$globalStack);
        $this->assertEquals(2, $instance->instanceStack);
        $this->assertEquals(1, $other->instanceStack);

        $other->callStack();
        $this->assertEquals(4, PreventsCircularRecursionWithRecursiveMethod::$globalStack);
        $this->assertEquals(2, $instance->instanceStack);
        $this->assertEquals(2, $other->instanceStack);
    }

    public function testRecursiveCallsToCircularReferenceCallsOtherInstanceOnce()
    {
        $instance = new PreventsCircularRecursionWithRecursiveMethod();
        $other = $instance->other;

        $this->assertEquals(0, PreventsCircularRecursionWithRecursiveMethod::$globalStack);
        $this->assertEquals(0, $instance->instanceStack);
        $this->assertEquals(0, $other->instanceStack);

        $instance->callOtherStack();
        $this->assertEquals(2, PreventsCircularRecursionWithRecursiveMethod::$globalStack);
        $this->assertEquals(1, $instance->instanceStack);
        $this->assertEquals(1, $other->instanceStack);

        $instance->callOtherStack();
        $this->assertEquals(4, PreventsCircularRecursionWithRecursiveMethod::$globalStack);
        $this->assertEquals(2, $instance->instanceStack);
        $this->assertEquals(2, $other->instanceStack);

        $other->callOtherStack();
        $this->assertEquals(6, PreventsCircularRecursionWithRecursiveMethod::$globalStack);
        $this->assertEquals(3, $other->instanceStack);
        $this->assertEquals(3, $instance->instanceStack);

        $other->callOtherStack();
        $this->assertEquals(8, PreventsCircularRecursionWithRecursiveMethod::$globalStack);
        $this->assertEquals(4, $other->instanceStack);
        $this->assertEquals(4, $instance->instanceStack);
    }

    public function testRecursiveCallsToCircularLinkedListCallsEachInstanceOnce()
    {
        $instance = new PreventsCircularRecursionWithRecursiveMethod();
        $second = $instance->other;
        $third = new PreventsCircularRecursionWithRecursiveMethod($second);
        $instance->other = $third;

        $this->assertEquals(0, PreventsCircularRecursionWithRecursiveMethod::$globalStack);
        $this->assertEquals(0, $instance->instanceStack);
        $this->assertEquals(0, $second->instanceStack);
        $this->assertEquals(0, $third->instanceStack);

        $instance->callOtherStack();
        $this->assertEquals(3, PreventsCircularRecursionWithRecursiveMethod::$globalStack);
        $this->assertEquals(1, $instance->instanceStack);
        $this->assertEquals(1, $second->instanceStack);
        $this->assertEquals(1, $third->instanceStack);

        $second->callOtherStack();
        $this->assertEquals(6, PreventsCircularRecursionWithRecursiveMethod::$globalStack);
        $this->assertEquals(2, $instance->instanceStack);
        $this->assertEquals(2, $second->instanceStack);
        $this->assertEquals(2, $third->instanceStack);

        $third->callOtherStack();
        $this->assertEquals(9, PreventsCircularRecursionWithRecursiveMethod::$globalStack);
        $this->assertEquals(3, $instance->instanceStack);
        $this->assertEquals(3, $second->instanceStack);
        $this->assertEquals(3, $third->instanceStack);
    }
}

class PreventsCircularRecursionWithRecursiveMethod
{
    use PreventsCircularRecursion;

    public function __construct(
        public ?PreventsCircularRecursionWithRecursiveMethod $other = null,
    ) {
        $this->other ??= new PreventsCircularRecursionWithRecursiveMethod($this);
    }

    public static int $globalStack = 0;
    public int $instanceStack = 0;

    public function callStack(): int
    {
        return $this->once(
            function () {
                static::$globalStack++;
                $this->instanceStack++;

                return $this->callStack();
            },
            $this->instanceStack
        );
    }

    public function callOtherStack(): int
    {
        return $this->once(
            function () {
                $this->other->callStack();

                return $this->other->callOtherStack();
            },
            $this->instanceStack
        );
    }
}
