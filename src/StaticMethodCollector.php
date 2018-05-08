<?php

declare(strict_types=1);
/**
* @author SignpostMarv
*/

namespace SignpostMarv\DaftInterfaceCollector;

use Generator;
use ReflectionClass;
use ReflectionMethod;
use Traversable;

class StaticMethodCollector
{
    /**
    * @var array<string, array<int, string>>
    */
    private $staticMethods;

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
        $staticMethods = array_filter(
            $staticMethods,
            /**
            * @param mixed $maybe
            */
            function ($maybe) : bool {
                return is_string($maybe) && interface_exists($maybe);
            },
            ARRAY_FILTER_USE_KEY
        );

        /**
        * @var array<string, array<int, string>> $filtered
        */
        $filtered = [];

        /**
        * @var string $interface
        * @var array<string, array<int, string>> $methods
        */
        foreach ($staticMethods as $interface => $methods) {
            $ref = new ReflectionClass($interface);

            $filtered[$interface] = array_filter(
                $methods,
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

                            return
                                'array' === $refReturn->__toString() ||
                                is_a((string) $refReturn->__toString(), Traversable::class, true);
                        }
                    }

                    return false;
                },
                ARRAY_FILTER_USE_KEY
            );

            $filtered[$interface] = array_map(
                function (array $methodInterfaces) : array {
                    return array_filter(
                        $methodInterfaces,
                        /**
                        * @param mixed $maybe
                        */
                        function ($maybe) : bool {
                            return is_string($maybe) && interface_exists($maybe);
                        }
                    );
                },
                $filtered[$interface]
            );
        }

        /**
        * @var array<string, array<int, string>> $filtered
        */
        $filtered = array_filter($filtered, function (array $methods) : bool {
            return count($methods) > 0;
        });

        /**
        * @var string[] $filteredInterfaces
        */
        $filteredInterfaces = array_filter(
            $interfaces,
            /**
            * @param mixed $maybe
            */
            function ($maybe) : bool {
                return is_string($maybe) && interface_exists($maybe);
            }
        );

        $this->interfaces = $filteredInterfaces;

        $this->staticMethods = $filtered;
        $this->autoResetProcessedSources = $autoResetProcessedSources;
    }

    public function Collect(string ...$implementations) : Generator
    {
        if ($this->autoResetProcessedSources) {
            $this->processedSources = [];
            $this->alreadyYielded = [];
        }

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
                    * @var string[] $types
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
}
