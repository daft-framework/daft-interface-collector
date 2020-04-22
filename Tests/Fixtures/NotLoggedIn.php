<?php
/**
* @author SignpostMarv
*/
declare(strict_types=1);

namespace SignpostMarv\DaftInterfaceCollector\Tests\Fixtures;

class NotLoggedIn implements DaftMiddleware
{
	public static function DaftRouterMiddlewareHandler(
		Request $request,
		? Response $response
	) : ? Response {
		return null;
	}

	/**
	 * @return array<int, string> URI prefixes
	 */
	public static function DaftRouterRoutePrefixExceptions() : array
	{
		return [];
	}
}
