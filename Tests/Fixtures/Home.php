<?php
/**
* @author SignpostMarv
*/
declare(strict_types=1);

namespace SignpostMarv\DaftInterfaceCollector\Tests\Fixtures;

use InvalidArgumentException;

class Home implements DaftRoute
{
	public static function DaftRouterHandleRequest(Request $request, array $args) : Response
	{
		return new Response();
	}

	public static function DaftRouterRoutes() : array
	{
		return [];
	}

	public static function DaftRouterHttpRoute(array $args, string $method = 'GET') : string
	{
		return '';
	}
}
