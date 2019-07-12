<?php

declare(strict_types=1);
/**
* @author SignpostMarv
*/

namespace SignpostMarv\DaftInterfaceCollector\Tests\Fixtures\StaticMethodCollector;

use Generator;
use SignpostMarv\DaftInterfaceCollector\StaticMethodCollector as Base;

class PublicCollectInterfacesFromImplementationTypes extends Base
{
	/**
	* @param class-string $implementation
	* @param array<int, class-string> $types
	*
	* @return Generator<int, class-string, mixed, void>
	*/
	public function PublicCollectInterfacesFromImplementationTypes(
		string $implementation,
		string $method,
		array $types
	) : Generator {
		return $this->CollectInterfacesFromImplementationTypes(
			$implementation,
			$method,
			$types
		);
	}
}
