<?php

declare(strict_types=1);
/**
* @author SignpostMarv
*/

namespace SignpostMarv\DaftInterfaceCollector;

use Closure;
use Generator;
use ReflectionClass;
use ReflectionMethod;
use Traversable;

class StaticMethodCollector
{
    /**
    * @var array<string, array<string, array<int, string>>>
    */
    private $staticMethods = [];

    /**
    * @var string[]
    */
    private $interfaces = [];

    /**
    * @var array<int, string>
    */
    private $processedSources = [];

    /**
    * @var string[]
    */
    private $alreadyYielded = [];

    /**
    * @var bool
    */
    private $autoResetProcessedSources;

    public function __construct(
        array $staticMethods,
        array $interfaces,
        bool $autoResetProcessedSources = true
    ) {
        /**
        * @var string $interface
        * @var array<string, array<int, string>> $methods
        */
        foreach (
            array_filter(
                $staticMethods,
                [$this, 'shouldContainInterfaces'],
                ARRAY_FILTER_USE_KEY
            ) as $interface => $methods
        ) {
            $ref = new ReflectionClass($interface);

            $filteredMethods = $this->FilterMethods($ref, $methods);
            if (count($filteredMethods) > 0) {
                $this->staticMethods[$interface] = $filteredMethods;
            }
        }

        /**
        * @var string[] $filteredInterfaces
        */
        $filteredInterfaces = array_filter($interfaces, [$this, 'shouldContainInterfaces']);

        $this->interfaces = $filteredInterfaces;

        $this->autoResetProcessedSources = $autoResetProcessedSources;
    }

    public function Collect(string ...$implementations) : Generator
    {
        if ($this->autoResetProcessedSources) {
            $this->processedSources = [];
            $this->alreadyYielded = [];
        }

        yield from $this->CollectInterfaces(...$implementations);
    }

    protected function CollectInterfaces(string ...$implementations) : Generator
    {
        /**
        * @var string[] $interfaces
        */
        $interfaces = array_keys($this->staticMethods);
        foreach (array_filter($implementations, 'class_exists') as $implementation) {
            if (
                in_array($implementation, $this->processedSources, true) ||
                in_array($implementation, $this->alreadyYielded, true)
            ) {
                continue;
            }
            $this->processedSources[] = $implementation;

            /**
            * @var string $interface
            */
            foreach ($this->interfaces as $interface) {
                if (is_a($implementation, $interface, true)) {
                    yield $implementation;
                    $this->alreadyYielded[] = $implementation;
                    break;
                }
            }

            foreach ($interfaces as $interface) {
                if (is_a($implementation, $interface, true)) {
                    /**
                    * @var array<int, string> $types
                    */
                    foreach ($this->staticMethods[$interface] as $method => $types) {
                        /**
                        * @var iterable<string> $methodResult
                        */
                        $methodResult = $implementation::$method();

                        /**
                        * @var string $result
                        */
                        foreach ($methodResult as $result) {
                            if (in_array($result, $this->alreadyYielded, true)) {
                                continue;
                            }
                            foreach ($types as $type) {
                                if (is_a($result, $type, true)) {
                                    yield $result;
                                    $this->alreadyYielded[] = $result;
                                    continue;
                                }
                            }
                            foreach ($interfaces as $checkResultWithInterface) {
                                if (is_a($result, $checkResultWithInterface, true)) {
                                    yield from $this->CollectInterfaces($result);
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    /**
    * @param mixed $maybe
    */
    protected function shouldContainInterfaces($maybe) : bool
    {
        return is_string($maybe) && interface_exists($maybe);
    }

    private function MakeMethodFilter(ReflectionClass $ref) : Closure
    {
        return
                /**
                * @param mixed $maybe
                */
                function ($maybe) use ($ref) : bool {
                    if (is_string($maybe) && $ref->hasMethod($maybe)) {
                        /**
                        * @var ReflectionMethod $refMethod
                        */
                        $refMethod = $ref->getMethod($maybe);

                        if (
                            $refMethod->isStatic() &&
                            $refMethod->isPublic() &&
                            0 === $refMethod->getNumberOfRequiredParameters() &&
                            $refMethod->hasReturnType()
                        ) {
                            /**
                            * @var \ReflectionType $refReturn
                            */
                            $refReturn = $refMethod->getReturnType();
                            $refReturnName = $refReturn->__toString();

                            return
                                'array' === $refReturnName ||
                                is_a($refReturnName, Traversable::class, true);
                        }
                    }

                    return false;
        };
    }

    /**
    * @param array<string, array<int, string>> $methods
    *
    * @return array<string, array<int, string>>
    */
    private function FilterMethods(ReflectionClass $ref, array $methods) : array
    {
        $filteredMethods = [];

        foreach (
            array_filter(
                $methods,
                $this->MakeMethodFilter($ref),
                ARRAY_FILTER_USE_KEY
            ) as $method => $interfaces
        ) {
            $methodInterfaces = array_filter($interfaces, [$this, 'shouldContainInterfaces']);

            if (count($methodInterfaces) > 0) {
                $filteredMethods[$method] = $interfaces;
            }
        }

        return $filteredMethods;
    }
}
