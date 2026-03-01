<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use Mockery;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use WdMultiWarehouse\Services\GeocodingService;

class GeocodingServiceTest extends TestCase
{
    private GeocodingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        $this->service = new GeocodingService();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    // ─── Cache hit ─────────────────────────────────────────────

    public function testGeocodeReturnsCachedResult(): void
    {
        $cached = [ 42.6977, 23.3219 ];

        Functions\expect( 'get_transient' )
            ->once()
            ->andReturn( $cached );

        $result = $this->service->geocode( 'Sofia, Bulgaria' );

        $this->assertSame( $cached, $result );
    }

    // ─── Cache miss → successful API call ──────────────────────

    public function testGeocodeCallsApiOnCacheMiss(): void
    {
        Functions\expect( 'get_transient' )
            ->once()
            ->andReturn( false );

        Functions\expect( 'add_query_arg' )
            ->once()
            ->andReturn( 'https://nominatim.openstreetmap.org/search?q=Sofia&format=json&limit=1' );

        $apiResponse = [
            'response' => [ 'code' => 200 ],
            'body'     => json_encode( [
                [ 'lat' => '42.6977', 'lon' => '23.3219' ],
            ] ),
        ];

        Functions\expect( 'wp_remote_get' )
            ->once()
            ->andReturn( $apiResponse );

        Functions\expect( 'is_wp_error' )
            ->once()
            ->with( $apiResponse )
            ->andReturn( false );

        Functions\expect( 'wp_remote_retrieve_body' )
            ->once()
            ->with( $apiResponse )
            ->andReturn( $apiResponse['body'] );

        Functions\expect( 'set_transient' )
            ->once()
            ->with(
                Mockery::type( 'string' ),
                [ 42.6977, 23.3219 ],
                Mockery::type( 'int' )
            )
            ->andReturn( true );

        $result = $this->service->geocode( 'Sofia, Bulgaria' );

        $this->assertIsArray( $result );
        $this->assertEqualsWithDelta( 42.6977, $result[0], 0.0001 );
        $this->assertEqualsWithDelta( 23.3219, $result[1], 0.0001 );
    }

    // ─── API error (WP_Error) ──────────────────────────────────

    public function testGeocodeReturnsNullOnWpError(): void
    {
        Functions\expect( 'get_transient' )
            ->once()
            ->andReturn( false );

        Functions\expect( 'add_query_arg' )
            ->once()
            ->andReturn( 'https://nominatim.openstreetmap.org/search?q=Nowhere' );

        $wpError = Mockery::mock( 'WP_Error' );

        Functions\expect( 'wp_remote_get' )
            ->once()
            ->andReturn( $wpError );

        Functions\expect( 'is_wp_error' )
            ->once()
            ->with( $wpError )
            ->andReturn( true );

        $result = $this->service->geocode( 'Nowhere' );

        $this->assertNull( $result );
    }

    // ─── Empty API response ────────────────────────────────────

    public function testGeocodeReturnsNullOnEmptyApiResponse(): void
    {
        Functions\expect( 'get_transient' )
            ->once()
            ->andReturn( false );

        Functions\expect( 'add_query_arg' )
            ->once()
            ->andReturn( 'https://nominatim.openstreetmap.org/search?q=Invalid' );

        $apiResponse = [
            'response' => [ 'code' => 200 ],
            'body'     => '[]',
        ];

        Functions\expect( 'wp_remote_get' )
            ->once()
            ->andReturn( $apiResponse );

        Functions\expect( 'is_wp_error' )
            ->once()
            ->andReturn( false );

        Functions\expect( 'wp_remote_retrieve_body' )
            ->once()
            ->andReturn( '[]' );

        $result = $this->service->geocode( 'Invalid Address XYZ' );

        $this->assertNull( $result );
    }

    // ─── Failed geocode is NOT cached ──────────────────────────

    public function testGeocodeDoesNotCacheNullResult(): void
    {
        Functions\expect( 'get_transient' )
            ->once()
            ->andReturn( false );

        Functions\expect( 'add_query_arg' )
            ->once()
            ->andReturn( 'https://nominatim.openstreetmap.org/search?q=Bad' );

        $wpError = Mockery::mock( 'WP_Error' );

        Functions\expect( 'wp_remote_get' )
            ->once()
            ->andReturn( $wpError );

        Functions\expect( 'is_wp_error' )
            ->once()
            ->andReturn( true );

        // set_transient should NOT be called
        Functions\expect( 'set_transient' )->never();

        $this->assertNull( $this->service->geocode( 'Bad Address' ) );
    }

    // ─── Cache key uses md5 for uniqueness ─────────────────────

    public function testDifferentAddressesProduceDifferentCacheKeys(): void
    {
        $cacheKeys = [];

        Functions\expect( 'get_transient' )
            ->twice()
            ->andReturnUsing( function ( string $key ) use ( &$cacheKeys ) {
                $cacheKeys[] = $key;
                return [ 1.0, 2.0 ]; // Return cached to avoid API calls.
            } );

        $this->service->geocode( 'Sofia, Bulgaria' );
        $this->service->geocode( 'Plovdiv, Bulgaria' );

        $this->assertCount( 2, $cacheKeys );
        $this->assertNotSame( $cacheKeys[0], $cacheKeys[1] );
    }
}
