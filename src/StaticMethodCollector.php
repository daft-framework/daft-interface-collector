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
use ReflectionType;
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
            $this->FilterArrayOfInterfaces(
                $staticMethods,
                ARRAY_FILTER_USE_KEY
            ) as $interface => $methods
        ) {
            $filteredMethods = $this->FilterMethods(new ReflectionClass($interface), $methods);
            if (count($filteredMethods) > 0) {
                $this->staticMethods[$interface] = $filteredMethods;
            }
        }

        /**
        * @var string[] $filteredInterfaces
        */
        $filteredInterfaces = $this->FilterArrayOfInterfaces($interfaces);

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
    * @return string[]|array<string, mixed>
    */
    private function FilterArrayOfInterfaces(array $interfaces, int $flag = 0) : array
    {
        $strings = array_filter($interfaces, 'is_string', $flag);

        return array_filter($strings, 'interface_exists', $flag);
    }

    private function MakeMethodFilter(ReflectionClass $ref) : Closure
    {
        return function (string $maybe) use ($ref) : bool {
            return
                $ref->hasMethod($maybe) &&
                $this->FilterReflectionMethod($ref->getMethod($maybe));
        };
    }

    private function FilterReflectionMethod(ReflectionMethod $refMethod) : bool
    {
        return
            $refMethod->isStatic() &&
            $refMethod->isPublic() &&
            0 === $refMethod->getNumberOfRequiredParameters() &&
            $this->FilterReflectionReturnType($refMethod->getReturnType());
    }

    private function FilterReflectionReturnType(? ReflectionType $refReturn) : bool
    {
        $refReturnName = is_null($refReturn) ? '' : $refReturn->__toString();

        return 'array' === $refReturnName || is_a($refReturnName, Traversable::class, true);
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
                array_filter($methods, 'is_string', ARRAY_FILTER_USE_KEY),
                $this->MakeMethodFilter($ref),
                ARRAY_FILTER_USE_KEY
            ) as $method => $interfaces
        ) {
            $methodInterfaces = $this->FilterArrayOfInterfaces($interfaces);

            if (count($methodInterfaces) > 0) {
                $filteredMethods[$method] = $interfaces;
            }
        }

        return $filteredMethods;
    }
}
