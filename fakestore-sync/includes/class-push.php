<?php
namespace FakeStoreSync;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Push {

    public function init() {
        add_action( 'save_post_product', array( $this, 'maybe_push_update' ), 20, 3 );
    }

    /**
     * LPS - Push WooCommerce product changes
     */
    public function maybe_push_update( $post_id, $post, $update ) {
        $settings = get_option( 'fss_settings', array() );
        if ( empty( $settings['push_enabled'] ) ) {
            return;
        }

        if ( isset( $settings['conflict_mode'] ) && $settings['conflict_mode'] === 'upstream' ) {
            return;
        }

        if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
            return;
        }

        $fakestore_id = get_post_meta( $post_id, '_fakestore_id', true );
        if ( ! $fakestore_id ) {
            return;
        }

        // Load product object
        $product = wc_get_product( $post_id );
        if ( ! $product ) {
            return;
        }

        $data = array(
            'title'       => $product->get_name(),
            'price'       => (float) $product->get_price(),
            'description' => $product->get_description(),
            'category'    => '', // WooCommerce categories here
            'image'       => wp_get_attachment_url( $product->get_image_id() ),
        );

        $settings = get_option( 'fss_settings', array( 'api_url' => 'https://fakestoreapi.com' ) );
        $api_base = rtrim( esc_url_raw( $settings['api_url'] ), '/' );
        $endpoint = $api_base . '/products/' . intval( $fakestore_id );

        $response = wp_remote_request( $endpoint, array(
            'method'  => 'PUT',
            'headers' => array( 'Content-Type' => 'application/json' ),
            'body'    => wp_json_encode( $data ),
            'timeout' => 20,
        ));

        var_dump( $response );

        if ( is_wp_error( $response ) ) {
            error_log( 'FakeStore push failed for product ' . $post_id . ': ' . $response->get_error_message() );
        } else {
            error_log( 'FakeStore product ' . $post_id . ' updated successfully on FakeStore API.' );
        }
    }
}
