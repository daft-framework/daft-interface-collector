<?php
/**
* @author SignpostMarv
*/
declare(strict_types=1);

namespace SignpostMarv\DaftInterfaceCollector\Tests\Fixtures;

class Config implements DaftSource
{
    public static function DaftRouterRouteAndMiddlewareSources() : array
    {
        return [
            Home::class,
            Login::class,
            NotLoggedIn::class,
        ];
    }
}
