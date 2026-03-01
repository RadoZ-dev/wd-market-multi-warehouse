<?php

declare(strict_types=1);

namespace WdMultiWarehouse\Core;

use WdMultiWarehouse\Traits\Singleton;

final class Plugin
{
    use Singleton;

    public function init(): void
    {
        // Dependencies will be wired here in later commits.
    }
}
