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
                    'DaftRouterRouteAndMiddlewareSources' => [
                        DaftMiddleware::class,
                        DaftRoute::class,
                        DaftSource::class,
                    ],
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
        array $expectedResult,
        string ...$implementations
    ) : void {
        $collector = new StaticMethodCollector($staticMethods);

        $this->assertSame($expectedResult, iterator_to_array($collector->Collect(...$implementations)));
    }
}
