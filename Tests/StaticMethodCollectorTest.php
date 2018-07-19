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

    /**
    * @dataProvider DataProviderCollection
    */
    public function testCollection(
        array $staticMethods,
        array $interfaces,
        array $expectedResult,
        string ...$implementations
    ) : void {
        $collector = new StaticMethodCollector($staticMethods, $interfaces);

        $collection = $collector->Collect(...$implementations);

        static::assertSame($expectedResult, iterator_to_array($collection));

        $collection = $collector->Collect(...$implementations);

        static::assertSame($expectedResult, iterator_to_array($collection));
    }

    /**
    * @dataProvider DataProviderCollection
    */
    public function testCollectionWithoutResettingProcessedSources(
        array $staticMethods,
        array $interfaces,
        array $expectedResult,
        string ...$implementations
    ) : void {
        $collector = new StaticMethodCollector($staticMethods, $interfaces, false);

        $collection = $collector->Collect(...$implementations);

        static::assertSame($expectedResult, iterator_to_array($collection));

        $collection = $collector->Collect(...$implementations);

        static::assertSame([], iterator_to_array($collection));
    }
}
