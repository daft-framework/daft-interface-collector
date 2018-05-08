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
use SignpostMarv\DaftRouter\DaftSource;
use SignpostMarv\DaftRouter\DaftRoute;
use SignpostMarv\DaftRouter\DaftMiddleware;
use SignpostMarv\DaftRouter\Tests\Fixtures;

class StaticMethodCollectorTest extends Base
{
    public function DataProviderCollection() : Generator
    {
        yield from [
            [
                [
                    DaftSource::class => [
                        'DaftRouterRouteAndMiddlewareSources' => [
                            DaftMiddleware::class,
                            DaftRoute::class,
                            DaftSource::class,
                        ],
                    ],
                ],
                [
                    DaftMiddleware::class,
                    DaftRoute::class,
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

        $this->assertSame($expectedResult, iterator_to_array($collection));

        $collection = $collector->Collect(...$implementations);

        $this->assertSame($expectedResult, iterator_to_array($collection));
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

        $this->assertSame($expectedResult, iterator_to_array($collection));

        $collection = $collector->Collect(...$implementations);

        $this->assertSame([], iterator_to_array($collection));
    }
}
