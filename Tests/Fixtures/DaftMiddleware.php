<?php
/**
* @author SignpostMarv
*/
declare(strict_types=1);

namespace SignpostMarv\DaftInterfaceCollector\Tests\Fixtures;

interface DaftMiddleware
{
	public static function DaftRouterMiddlewareHandler(
		Request $request,
		? Response $response
	) : ? Response;

	/**
	* @return array<int, string> URI prefixes
	*/
	public static function DaftRouterRoutePrefixExceptions() : array;
}
