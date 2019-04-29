<?php

declare(strict_types=1);
/**
* @author SignpostMarv
*/

namespace SignpostMarv\DaftInterfaceCollector\Tests;

use Generator;
use PHPUnit\Framework\TestCase as Base;
use SignpostMarv\DaftInterfaceCollector\StaticMethodCollector;

class StaticMethodCollectorTest extends Base
{
    /**
    * @var bool
    */
    protected $backupGlobals = false;

    /**
    * @var bool
    */
    protected $backupStaticAttributes = false;

    /**
    * @var bool
    */
    protected $runTestInSeparateProcess = false;

    public function DataProviderCollection() : Generator
    {
        yield from [
            [
                [
                    Fixtures\DaftSource::class => [
                        'DaftRouterRouteAndMiddlewareSources' => [
                            Fixtures\DaftMiddleware::class,
                            Fixtures\DaftRoute::class,
                            Fixtures\DaftSource::class,
                        ],
                    ],
                ],
                [
                    Fixtures\DaftMiddleware::class,
                    Fixtures\DaftRoute::class,
                ],
                [
                    Fixtures\Home::class,
                    Fixtures\Login::class,
                    Fixtures\NotLoggedIn::class,
                ],
                Fixtures\Config::class,
            ],
        ];
    }

    /**
    * @psalm-return Generator<int, array{0:bool, 1:string[], 2:array<class-string, array<string, array<int, class-string>>>, 3:class-string[], 4:class-string[], 5:string}, mixed, void>
    */
    public function DataProviderCollectionToggle() : Generator
    {
        /**
        * @var iterable<array<int, scalar|array>>
        */
        $sources = $this->DataProviderCollection();

        foreach ($sources as $args) {
            /**
            * @psalm-var array{0:bool, 1:string[], 2:array<class-string, array<string, array<int, class-string>>>, 3:class-string[], 4:class-string[], 5:string}
            */
            $out = array_merge([true], $args);

            yield $out;

            /**
            * @psalm-var array{0:bool, 1:string[], 2:array<class-string, array<string, array<int, class-string>>>, 3:class-string[], 4:class-string[], 5:string}
            */
            $out = array_merge([false], $args);

            yield $out;
        }
    }

    /**
    * @param class-string ...$implementations
    *
    * @dataProvider DataProviderCollectionToggle
    */
    public function testCollection(
        bool $semiResetting,
        array $staticMethods,
        array $interfaces,
        array $expectedResult,
        string ...$implementations
    ) : void {
        $collector =
            $semiResetting
                ? new StaticMethodCollector($staticMethods, $interfaces)
                : new Fixtures\SemiResettingStaticMethodCollector($staticMethods, $interfaces);

        $collection = $collector->Collect(...$implementations);

        static::assertSame($expectedResult, iterator_to_array($collection));

        $collection = $collector->Collect(...$implementations);

        if ( ! ($collector instanceof Fixtures\SemiResettingStaticMethodCollector)) {
            static::assertSame($expectedResult, iterator_to_array($collection));
        } else {
            static::assertSame([], iterator_to_array($collection));
        }
    }

    /**
    * @param class-string ...$implementations
    *
    * @dataProvider DataProviderCollectionToggle
    */
    public function testCollectionWithoutResettingProcessedSources(
        bool $semiResetting,
        array $staticMethods,
        array $interfaces,
        array $expectedResult,
        string ...$implementations
    ) : void {
        $collector =
            $semiResetting
                ? new StaticMethodCollector($staticMethods, $interfaces, false)
                : new Fixtures\SemiResettingStaticMethodCollector(
                    $staticMethods,
                    $interfaces,
                    false
                );

        $collection = $collector->Collect(...$implementations);

        static::assertSame($expectedResult, iterator_to_array($collection));

        $collection = $collector->Collect(...$implementations);

        static::assertSame([], iterator_to_array($collection));
    }
}
