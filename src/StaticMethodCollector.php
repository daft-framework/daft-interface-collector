<?php

declare(strict_types=1);
/**
* @author SignpostMarv
*/

namespace SignpostMarv\DaftInterfaceCollector;

use Generator;
use InvalidArgumentException;
use ReflectionMethod;
use Traversable;

class StaticMethodCollector
{
    /**
    * @param array<string, array<int, string>> $staticMethods
    */
    private $staticMethods;

    /**
    * @var array<int, string>
    */
    private $processedSources = [];

    public function __construct(array $staticMethods)
    {
        $filtered = array_map(
            /**
            * @return string[]
            */
            function (array $shouldContainInterfaces) : array {
                /**
                * @var string[] $out
                */
                $out = array_filter($shouldContainInterfaces, [$this, 'shouldContainInterfaces']);

                return array_values($out);
            },
            array_filter($staticMethods, 'is_array')
        );

        $this->staticMethods = $filtered;
    }

    public function Collect(string ...$implementations) : Generator
    {
        $this->processedSources = [];

        yield from $this->CollectInterfaces(...$implementations);
    }

    protected function CollectInterfaces(string ...$implementations) : Generator
    {
        foreach (array_filter($implementations, 'class_exists') as $implementation) {
            $this->processedSources[] = $implementation;

            /**
            * @var string $method
            * @var array<int, string> $interfaces
            */
            foreach ($this->staticMethods as $method => $interfaces) {
                if ( ! method_exists($implementation, $method)) {
                    continue;
                }

                $ref = new ReflectionMethod($implementation, $method);

                if (
                    ! $ref->isStatic() ||
                    ! $ref->isPublic() ||
                    0 < $ref->getNumberOfRequiredParameters() ||
                    ! $ref->hasReturnType()
                ) {
                    continue;
                }

                /**
                * @var \ReflectionType $refReturn
                */
                $refReturn = $ref->getReturnType();

                if (
                    ! (
                        'array' === $refReturn->__toString() ||
                        is_a((string) $refReturn->__toString(), Traversable::class, true)
                    )
                ) {
                    continue;
                }

                /**
                * @var array|Traversable $methodResult
                */
                $methodResult = $implementation::$method();

                if (is_iterable($methodResult)) {
                    /**
                    * @var string $perhapsYield
                    */
                    foreach (
                        array_filter(
                            (
                                is_array($methodResult)
                                    ? $methodResult
                                    : iterator_to_array($methodResult)
                            ),
                            'is_string'
                        ) as $perhapsYield
                    ) {
                        foreach ($interfaces as $interface) {
                            if (is_a($perhapsYield, $interface, true)) {
                                yield $perhapsYield;
                                break;
                            }
                        }

                        if (
                            ! in_array($perhapsYield, $this->processedSources, true)
                        ) {
                            yield from $this->CollectInterfaces($perhapsYield);
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
