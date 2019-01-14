<?php

declare(strict_types=1);
/**
* @author SignpostMarv
*/

namespace SignpostMarv\DaftInterfaceCollector\Tests;

use Generator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase as Base;
use SignpostMarv\DaftInterfaceCollector\StaticMethodCollector;

class StaticMethodCollectorTest extends Base
{
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

    public function DataProviderCollectors() : Generator
    {
        foreach ($this->DataProviderCollection() as $args) {
            $staticMethods = array_shift($args);
            $interfaces = array_shift($args);

            $static = new StaticMethodCollector($staticMethods, $interfaces);

            yield array_merge([$static], $args);

            $semi = new Fixtures\SemiResettingStaticMethodCollector($staticMethods, $interfaces);

            yield array_merge([$semi], $args);
        }
    }

    public function DataProviderCollectorsNonResetting() : Generator
    {
        foreach ($this->DataProviderCollection() as $args) {
            $staticMethods = array_shift($args);
            $interfaces = array_shift($args);

            $static = new StaticMethodCollector($staticMethods, $interfaces, false);

            yield array_merge([$static], $args);

            $semi = new Fixtures\SemiResettingStaticMethodCollector(
                $staticMethods,
                $interfaces,
                false
            );

            yield array_merge([$semi], $args);
        }
    }

    /**
    * @dataProvider DataProviderCollectors
    */
    public function testCollection(
        StaticMethodCollector $collector,
        array $expectedResult,
        string ...$implementations
    ) : void {
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
    * @dataProvider DataProviderCollectorsNonResetting
    */
    public function testCollectionWithoutResettingProcessedSources(
        StaticMethodCollector $collector,
        array $expectedResult,
        string ...$implementations
    ) : void {
        $collection = $collector->Collect(...$implementations);

        static::assertSame($expectedResult, iterator_to_array($collection));

        $collection = $collector->Collect(...$implementations);

        static::assertSame([], iterator_to_array($collection));
    }
}
