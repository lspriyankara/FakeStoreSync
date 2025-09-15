<?php
namespace FakeStoreSync;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Sync {

    protected $settings;

    public function __construct() {
        $this->settings = get_option( 'fss_settings', array( 'api_url' => 'https://fakestoreapi.com' ) );
    }

    public function init() {

    }

    /**
     * LPS -  Manual sync entry point called by admin.
     */
    public function run_sync_manual() {
        $settings = get_option( 'fss_settings', array() );
        $conflict = isset( $settings['conflict_mode'] ) ? $settings['conflict_mode'] : 'local';

        $api_base = rtrim( esc_url_raw( $this->settings['api_url'] ), '/' );
        $endpoint = $api_base . '/products';

        $response = wp_remote_get( $endpoint, array( 'timeout' => 20 ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( ! is_array( $data ) ) {
            return new \WP_Error( 'invalid_response', 'Invalid JSON from FakeStore API' );
        }

        $importer = new Importer();

        $batch_size = 5;
        $chunks = array_chunk( $data, $batch_size );

        $imported = 0;
        $updated = 0;

        foreach ( $chunks as $chunk ) {
            foreach ( $chunk as $item ) {

                $res = $importer->import_product( $item, $conflict );

                if ( is_wp_error( $res ) ) {
                    continue;
                }

                if ( $res === 'imported' ) {
                    $imported++;
                } elseif ( $res === 'updated' ) {
                    $updated++;
                }
            }

            sleep( 1 );
        }

        update_option( 'fss_last_sync', current_time( 'mysql' ) );
        update_option( 'fss_last_counts', array( 'imported' => $imported, 'updated' => $updated ) );

        return array( 'imported' => $imported, 'updated' => $updated );
    }
}
