<?php

declare(strict_types=1);
/**
* @author SignpostMarv
*/

namespace SignpostMarv\DaftInterfaceCollector;

use Closure;
use Generator;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;
use Traversable;

class StaticMethodCollector
{
    const DEFAULT_INT_ARRAY_FILTER_FLAG = 0;

    const DEFAULT_BOOL_AUTORESET = true;

    const INT_FILTER_NON_EMPTY_ARRAY = 0;

    const EXPECTED_NUMBER_OF_REQUIRED_PARAMETERS = 0;

    /**
    * @var array<int, string>
    *
    * @psalm-var array<int, class-string>
    */
    protected $processedSources = [];

    /**
    * @var string[]
    *
    * @psalm-var class-string[]
    */
    protected $alreadyYielded = [];

    /**
    * @var bool
    */
    protected $autoReset;

    /**
    * @var array<class-string, array<string, array<int, class-string>>>
    */
    private $staticMethods = [];

    /**
    * @var string[]
    *
    * @psalm-var class-string[]
    */
    private $interfaces = [];

    public function __construct(
        array $staticMethods,
        array $interfaces,
        bool $autoReset = self::DEFAULT_BOOL_AUTORESET
    ) {
        $filteredMethods = [];

        foreach ($this->FilterArrayOfInterfaceOffsets($staticMethods) as $interface => $methods) {
            $filteredMethods[$interface] = $this->FilterMethods($interface, $methods);
        }

        $this->staticMethods = $this->FilterNonZeroArray($filteredMethods);

        $this->interfaces = $this->FilterArrayOfInterfacesOrClasses($interfaces);

        $this->autoReset = $autoReset;
    }

    /**
    * @param class-string ...$implementations
    */
    public function Collect(string ...$implementations) : Generator
    {
        if ($this->autoReset) {
            $this->processedSources = [];
            $this->alreadyYielded = [];
        }

        yield from $this->CollectInterfaces(...$implementations);
    }

    /**
    * @param class-string ...$implementations
    */
    protected function CollectInterfaces(string ...$implementations) : Generator
    {
        foreach (
            array_filter(
                $implementations,
                /**
                * @param class-string $implementation
                */
                function (string $implementation) : bool {
                    return
                        ! static::IsStringInArray($implementation, $this->processedSources);
                }
            ) as $implementation
        ) {
            $this->processedSources[] = $implementation;
            yield from $this->CollectInterfacesFromImplementationCheckInterfaces($implementation);
            yield from $this->CollectInterfacesFromImplementation($implementation);
        }
    }

    /**
    * @param class-string $implementation
    */
    final protected function CollectInterfacesFromImplementationCheckInterfaces(
        string $implementation
    ) : Generator {
        $checking = array_filter(
            $this->interfaces,
            /**
            * @param class-string $interface
            */
            function (string $interface) use ($implementation) : bool {
                return static::IsStringA($implementation, $interface);
            }
        );

        if (
            count($checking) > self::INT_FILTER_NON_EMPTY_ARRAY &&
            ! static::IsStringInArray($implementation, $this->alreadyYielded)
        ) {
            yield $implementation;
            $this->alreadyYielded[] = $implementation;
        }
    }

    /**
    * @param class-string $implementation
    */
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
    * @param class-string $implementation
    * @param array<int, class-string> $types
    */
    final protected function CollectInterfacesFromImplementationTypes(
        string $implementation,
        string $method,
        array $types
    ) : Generator {
        if ( ! method_exists($implementation, $method)) {
            throw new InvalidArgumentException(
                'Argument 2 passed to ' .
                __METHOD__ .
                ' is not a method on Argument 1!'
            );
        }

        /**
        * @var iterable<class-string>
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
    * @template T
    *
    * @param T::class $implementation
    * @param array<int, class-string> $interfaces
    *
    * @return array<int, T::class>
    */
    final protected function FilterIsA(string $implementation, array $interfaces) : array
    {
        return array_filter(
            $interfaces,
            /**
            * @param class-string $interface
            */
            function (string $interface) use ($implementation) : bool {
                return static::IsStringA($implementation, $interface);
            }
        );
    }

    /**
    * @return string[]|array<string, mixed>
    */
    final protected function FilterArrayOfInterfaces(
        array $interfaces,
        int $flag = self::DEFAULT_INT_ARRAY_FILTER_FLAG
    ) : array {
        $strings = array_filter($interfaces, 'is_string', $flag);

        return array_filter($strings, 'interface_exists', $flag);
    }

    /**
    * @return class-string[]
    */
    final protected function FilterArrayOfInterfacesOrClasses(array $interfaces) : array
    {
        /**
        * @var class-string[]
        */
        $strings = array_filter(
            array_filter($interfaces, 'is_string'),
            function (string $maybe) : bool {
                return interface_exists($maybe) || class_exists($maybe);
            }
        );

        return $strings;
    }

    /**
    * @return array<class-string, array>
    */
    final protected function FilterArrayOfInterfaceOffsets(array $interfaces) : array
    {
        /**
        * @var array<class-string, array>
        */
        $strings = array_filter(
            $this->FilterArrayOfInterfaces($interfaces, ARRAY_FILTER_USE_KEY),
            'is_array'
        );

        return $strings;
    }

    /**
    * @param class-string $interface
    */
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
            self::EXPECTED_NUMBER_OF_REQUIRED_PARAMETERS === $refMethod->getNumberOfRequiredParameters() &&
            $this->FilterReflectionReturnType($refMethod->getReturnType());
    }

    final protected function FilterReflectionReturnType(? ReflectionType $refReturn) : bool
    {
        /**
        * @var string|class-string
        */
        $refReturnName = ($refReturn instanceof ReflectionNamedType) ? $refReturn->getName() : '';

        return 'array' === $refReturnName || static::IsStringA($refReturnName, Traversable::class);
    }

    /**
    * @param class-string $interface
    *
    * @return array<class-string, string[]>
    */
    final protected function FilterMethods(string $interface, array $methods) : array
    {
        /**
        * @var array<class-string, string[]>
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
    * @return array<class-string, array<string, array<int, class-string>>>
    */
    final protected function FilterNonZeroArray(array $in) : array
    {
        /**
        * @var array<class-string, array<string, array<int, class-string>>>
        */
        $out = array_filter(
            $in,
            function (array $val) : bool {
                return count($val) > self::INT_FILTER_NON_EMPTY_ARRAY;
            }
        );

        return $out;
    }

    /**
    * @param class-string $maybe
    * @param class-string[] $array
    */
    protected static function IsStringInArray(string $maybe, array $array) : bool
    {
        return in_array($maybe, $array, true);
    }

    /**
    * @param class-string $thing
    */
    protected static function IsStringA(string $maybe, string $thing) : bool
    {
        return is_a($maybe, $thing, true);
    }
}
