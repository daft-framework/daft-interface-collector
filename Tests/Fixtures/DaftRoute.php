<?php
/**
* @author SignpostMarv
*/
declare(strict_types=1);

namespace SignpostMarv\DaftInterfaceCollector\Tests\Fixtures;

use InvalidArgumentException;

interface DaftRoute
{
	public static function DaftRouterHandleRequest(Request $request, array $args) : Response;

	/**
	* @return array<string, array<int, string>> an array of URIs & methods
	*/
	public static function DaftRouterRoutes() : array;

	/**
	* @param array<string, scalar> $args
	*
	* @throws InvalidArgumentException if no uri could be found
	*/
	public static function DaftRouterHttpRoute(array $args, string $method = 'GET') : string;
}
