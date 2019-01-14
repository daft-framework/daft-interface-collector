<?php
/**
* @author SignpostMarv
*/
declare(strict_types=1);

namespace SignpostMarv\DaftInterfaceCollector\Tests\Fixtures;

class NotLoggedIn implements DaftMiddleware
{
    /**
    * @return Response|null
    */
    public static function DaftRouterMiddlewareHandler(
        Request $request,
        Response $response = null
    ) {
    }

    /**
    * @return array<int, string> URI prefixes
    */
    public static function DaftRouterRoutePrefixExceptions() : array
    {
        return [];
    }
}
