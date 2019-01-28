<?php
/**
* @author SignpostMarv
*/
declare(strict_types=1);

namespace SignpostMarv\DaftInterfaceCollector\Tests\Fixtures;

use Generator;
use SignpostMarv\DaftInterfaceCollector\StaticMethodCollector as Base;

class SemiResettingStaticMethodCollector extends Base
{
    /**
    * @param class-string ...$implementations
    */
    public function Collect(string ...$implementations) : Generator
    {
        if ($this->autoReset) {
            $this->processedSources = [];
        }

        yield from $this->CollectInterfaces(...$implementations);
    }
}
