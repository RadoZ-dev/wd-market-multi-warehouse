<?php

declare(strict_types=1);

namespace WdMultiWarehouse\Services;

use WdMultiWarehouse\Contracts\GeocoderInterface;

class GeocodingService implements GeocoderInterface
{
    /**
     * Geocode an address using OpenStreetMap Nominatim (free, no API key required).
     *
     * @return array{0: float, 1: float}|null
     */
    public function geocode( string $address ): ?array
    {
        $cacheKey = 'wdmw_geo_' . md5( $address );
        $cached   = get_transient( $cacheKey );

        if ( $cached !== false ) {
            return $cached;
        }

        $coordinates = $this->fetchCoordinates( $address );

        if ( $coordinates !== null ) {
            set_transient( $cacheKey, $coordinates, DAY_IN_SECONDS * 30 );
        }

        return $coordinates;
    }

    /**
     * @return array{0: float, 1: float}|null
     */
    private function fetchCoordinates( string $address ): ?array
    {
        $url = add_query_arg(
            [
                'q'      => $address,
                'format' => 'json',
                'limit'  => 1,
            ],
            'https://nominatim.openstreetmap.org/search'
        );

        $response = wp_remote_get(
            $url,
            [
                'timeout'    => 10,
                'user-agent' => 'WdMultiWarehouse/1.0 WordPress Plugin',
            ]
        );

        if ( is_wp_error( $response ) ) {
            return null;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $body ) || ! isset( $body[0]['lat'], $body[0]['lon'] ) ) {
            return null;
        }

        return [ (float) $body[0]['lat'], (float) $body[0]['lon'] ];
    }
}
