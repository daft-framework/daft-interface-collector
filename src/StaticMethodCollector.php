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
use ReflectionNamedType;
use ReflectionType;
use Traversable;

class StaticMethodCollector
{
    const DEFAULT_INT_ARRAY_FILTER_FLAG = 0;

    const INT_FILTER_NON_EMPTY_ARRAY = 0;

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
    private $autoReset;

    public function __construct(array $staticMethods, array $interfaces, bool $autoReset = true)
    {
        $filteredMethods = [];

        foreach ($this->FilterArrayOfInterfaceOffsets($staticMethods) as $interface => $methods) {
            $filteredMethods[$interface] = $this->FilterMethods($interface, $methods);
        }

        $this->staticMethods = $this->FilterNonZeroArray($filteredMethods);

        /**
        * @var string[]
        */
        $filteredInterfaces = $this->FilterArrayOfInterfacesOrClasses($interfaces);

        $this->interfaces = $filteredInterfaces;

        $this->autoReset = $autoReset;
    }

    public function Collect(string ...$implementations) : Generator
    {
        if ($this->autoReset) {
            $this->processedSources = [];
            $this->alreadyYielded = [];
        }

        yield from $this->CollectInterfaces(...$implementations);
    }

    protected function CollectInterfaces(string ...$implementations) : Generator
    {
        foreach (
            array_filter(
                $implementations,
                function (string $implementation) : bool {
                    return
                        class_exists($implementation) &&
                        ! static::IsStringInArray($implementation, $this->processedSources);
                }
            ) as $implementation
        ) {
            $this->processedSources[] = $implementation;
            yield from $this->CollectInterfacesFromImplementationCheckInterfaces($implementation);
            yield from $this->CollectInterfacesFromImplementation($implementation);
        }
    }

    final protected function CollectInterfacesFromImplementationCheckInterfaces(
        string $implementation
    ) : Generator {
        foreach ($this->interfaces as $interface) {
            if (
                ! static::IsStringInArray($implementation, $this->alreadyYielded) &&
                static::IsStringA($implementation, $interface)
            ) {
                yield $implementation;
                $this->alreadyYielded[] = $implementation;
                break;
            }
        }
    }

    final protected function CollectInterfacesFromImplementation(string $implementation) : Generator
    {
        $interfaces = array_keys($this->staticMethods);

        foreach ($this->FilterIsA($implementation, $interfaces) as $interface) {
            foreach ($this->staticMethods[$interface] as $method => $types) {
                yield from $this->CollectInterfacesFromImplementationTypes(
                    $implementation,
                    $method,
                    $types
                );
            }
        }
    }

    /**
    * @param array<int, string> $types
    *
    * @psalm-suppress InvalidStringClass
    */
    final protected function CollectInterfacesFromImplementationTypes(
        string $implementation,
        string $method,
        array $types
    ) : Generator {
        /**
        * @var iterable<string>
        */
        $methodResult = $implementation::$method();

        foreach ($methodResult as $result) {
            if (static::IsStringInArray($result, $this->alreadyYielded)) {
                continue;
            }

            foreach ($this->FilterIsA($result, $types) as $type) {
                yield $result;
                $this->alreadyYielded[] = $result;
            }

            yield from $this->CollectInterfaces($result);
        }
    }

    /**
    * @param array<int, string> $interfaces
    *
    * @return array<int, string>
    */
    final protected function FilterIsA(string $implementation, array $interfaces) : array
    {
        /**
        * @var array<int, string>
        */
        $out = array_filter($interfaces, function (string $interface) use ($implementation) : bool {
            return static::IsStringA($implementation, $interface);
        });

        return $out;
    }

    /**
    * @return string[]|array<string, mixed>
    */
    final protected function FilterArrayOfInterfaces(
        array $interfaces,
        int $flag = self::DEFAULT_INT_ARRAY_FILTER_FLAG
    ) : array
    {
        $strings = array_filter($interfaces, 'is_string', $flag);

        return array_filter($strings, 'interface_exists', $flag);
    }

    /**
    * @return string[]
    */
    final protected function FilterArrayOfInterfacesOrClasses(array $interfaces) : array
    {
        /**
        * @var string[]
        */
        $strings = array_filter($interfaces, 'is_string');

        return array_filter($strings, function (string $maybe) : bool {
            return interface_exists($maybe) || class_exists($maybe);
        });
    }

    /**
    * @return array<string, array>
    */
    final protected function FilterArrayOfInterfaceOffsets(array $interfaces) : array
    {
        /**
        * @var array<string, array>
        */
        $strings = $this->FilterArrayOfInterfaces($interfaces, ARRAY_FILTER_USE_KEY);

        return array_filter($strings, 'is_array');
    }

    final protected function MakeMethodFilter(string $interface) : Closure
    {
        return function (string $maybe) use ($interface) : bool {
            $ref = new ReflectionClass($interface);

            return
                $ref->hasMethod($maybe) &&
                $this->FilterReflectionMethod($ref->getMethod($maybe));
        };
    }

    final protected function FilterReflectionMethod(ReflectionMethod $refMethod) : bool
    {
        return
            $refMethod->isStatic() &&
            $refMethod->isPublic() &&
            0 === $refMethod->getNumberOfRequiredParameters() &&
            $this->FilterReflectionReturnType($refMethod->getReturnType());
    }

    final protected function FilterReflectionReturnType(? ReflectionType $refReturn) : bool
    {
        $refReturnName = ($refReturn instanceof ReflectionNamedType) ? $refReturn->getName() : '';

        return 'array' === $refReturnName || static::IsStringA($refReturnName, Traversable::class);
    }

    /**
    * @return array<string, string[]>
    */
    final protected function FilterMethods(string $interface, array $methods) : array
    {
        /**
        * @var array<string, string[]>
        */
        $filteredMethods = $this->FilterNonZeroArray(array_map(
            [$this, 'FilterArrayOfInterfacesOrClasses'],
            array_filter(
                array_filter($methods, 'is_string', ARRAY_FILTER_USE_KEY),
                $this->MakeMethodFilter($interface),
                ARRAY_FILTER_USE_KEY
            )
        ));

        return $filteredMethods;
    }

    /**
    * @return array<string, array<string, array<int, string>>>
    */
    final protected function FilterNonZeroArray(array $in) : array
    {
        /**
        * @var array<string, array<string, array<int, string>>>
        */
        $out = array_filter(
            $in,
            function (array $val) : bool {
                return count($val) > self::INT_FILTER_NON_EMPTY_ARRAY;
            }
        );

        return $out;
    }

    protected static function IsStringInArray(string $maybe, array $array) : bool
    {
        return in_array($maybe, $array, true);
    }

    protected static function IsStringA(string $maybe, string $thing) : bool
    {
        return is_a($maybe, $thing, true);
    }
}
