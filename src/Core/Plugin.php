<?php

declare(strict_types=1);

namespace WdMultiWarehouse\Core;

use WdMultiWarehouse\Traits\SingletonTrait;

final class Plugin
{
    use SingletonTrait;

    public function init(): void
    {
        // Dependencies will be wired here in later commits.
    }
}
