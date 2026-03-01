<?php

declare(strict_types=1);

namespace WdMultiWarehouse\Services;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

class TemplateRenderer
{
    private Environment $twig;

    public function __construct()
    {
        $loader = new FilesystemLoader( WDMW_PLUGIN_DIR . 'src/Views' );

        $this->twig = new Environment(
            $loader,
            [
                'cache'       => $this->getCacheDir(),
                'auto_reload' => true,
                'autoescape'  => 'html',
            ]
        );

        $this->registerWordPressBridge();
    }

    private function registerWordPressBridge(): void
    {
        // |trans filter — wraps WordPress __()
        $this->twig->addFilter(
            new TwigFilter(
                'trans',
                static function ( string $text ): string {
                    return __( $text, 'wd-market-multi-warehouse' );
                }
            )
        );

        // esc_html, esc_attr filters
        $this->twig->addFilter( new TwigFilter( 'esc_html', 'esc_html' ) );
        $this->twig->addFilter( new TwigFilter( 'esc_attr', 'esc_attr' ) );

        // wp_nonce_field function
        $this->twig->addFunction(
            new TwigFunction(
                'wp_nonce_field',
                static function (
                    string $action,
                    string $name = '_wpnonce'
                ): string {
                    return wp_nonce_field( $action, $name, true, false );
                },
                [ 'is_safe' => [ 'html' ] ]
            )
        );
    }

    public function render( string $template, array $context = [] ): string
    {
        return $this->twig->render( $template, $context );
    }

    public function display( string $template, array $context = [] ): void
    {
        echo $this->render( $template, $context );
    }

    private function getCacheDir(): string
    {
        $cacheDir = WP_CONTENT_DIR . '/cache/wdmw-twig';

        if ( ! is_dir( $cacheDir ) ) {
            wp_mkdir_p( $cacheDir );
        }

        return $cacheDir;
    }
}
