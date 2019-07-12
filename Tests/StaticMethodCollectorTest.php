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
	/**
	* @var bool
	*/
	protected $backupGlobals = false;

	/**
	* @var bool
	*/
	protected $backupStaticAttributes = false;

	/**
	* @var bool
	*/
	protected $runTestInSeparateProcess = false;

	/**
	* @return Generator<int, array{0:array<class-string, array<string, array<int, class-string>>>, 1:array<int, class-string>, 2:array<int, class-string>, 3:class-string}, mixed, void>
	*/
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
	* @return Generator<int, array{0:bool, 1:array<class-string, array<string, array<int, class-string>>>, 2:array<int, class-string>, 3:array<int, class-string>, 4:class-string}, mixed, void>
	*/
	public function DataProviderCollectionToggle() : Generator
	{
		/**
		* @var iterable<array<int, scalar|array>>
		*/
		$sources = $this->DataProviderCollection();

		foreach ($sources as $args) {
			/**
			* @var array{0:true, 1:array<class-string, array<string, array<int, class-string>>>, 2:array<int, class-string>, 3:array<int, class-string>, 4:class-string}
			*/
			$out = array_merge([true], $args);

			yield $out;

			/**
			* @var array{0:false, 1:array<class-string, array<string, array<int, class-string>>>, 2:array<int, class-string>, 3:array<int, class-string>, 4:class-string}
			*/
			$out = array_merge([false], $args);

			yield $out;
		}
	}

	/**
	* @param array<class-string, array<string, array<int, class-string>>> $staticMethods
	* @param array<int, class-string> $interfaces
	* @param class-string ...$implementations
	*
	* @dataProvider DataProviderCollectionToggle
	*/
	public function testCollection(
		bool $semiResetting,
		array $staticMethods,
		array $interfaces,
		array $expectedResult,
		string ...$implementations
	) : void {
		$collector =
			$semiResetting
				? new StaticMethodCollector($staticMethods, $interfaces)
				: new Fixtures\SemiResettingStaticMethodCollector($staticMethods, $interfaces);

		$collection = $collector->Collect(...$implementations);

		static::assertSame($expectedResult, iterator_to_array($collection));

		$collection = $collector->Collect(...$implementations);

		if ( ! ($collector instanceof Fixtures\SemiResettingStaticMethodCollector)) {
			static::assertSame($expectedResult, iterator_to_array($collection));
		} else {
			static::assertSame([], iterator_to_array($collection));
		}
	}

	/**
	* @param array<class-string, array<string, array<int, class-string>>> $staticMethods
	* @param array<int, class-string> $interfaces
	* @param array<int, class-string> $expectedResult
	* @param string ...$implementations
	*
	* @psalm-param class-string ...$implementations
	*
	* @dataProvider DataProviderCollectionToggle
	*/
	public function testCollectionWithoutResettingProcessedSources(
		bool $semiResetting,
		array $staticMethods,
		array $interfaces,
		array $expectedResult,
		string ...$implementations
	) : void {
		$collector =
			$semiResetting
				? new StaticMethodCollector($staticMethods, $interfaces, false)
				: new Fixtures\SemiResettingStaticMethodCollector(
					$staticMethods,
					$interfaces,
					false
				);

		$collection = $collector->Collect(...$implementations);

		static::assertSame($expectedResult, iterator_to_array($collection));

		$collection = $collector->Collect(...$implementations);

		static::assertSame([], iterator_to_array($collection));
	}

	public function testCollectInterfacesFromImplementationTypesFails() : void
	{
		$collector = new Fixtures\StaticMethodCollector\PublicCollectInterfacesFromImplementationTypes(
			[],
			[]
		);

		static::expectException(InvalidArgumentException::class);

		$collector->PublicCollectInterfacesFromImplementationTypes(
			Generator::class,
			'foo',
			[]
		)->valid();
	}
}
