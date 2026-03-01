<?php

declare(strict_types=1);

namespace WdMultiWarehouse\Traits;

defined( 'ABSPATH' ) || exit;

trait Singleton
{
    private static ?self $instance = null;

    private function __construct()
    {
    }

    public static function getInstance(): self
    {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
