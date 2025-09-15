<?php
namespace FakeStoreSync;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Importer {

    /**
     * LPS - Import or update a single product
     */
    public function import_product( $item ) {

        if ( empty( $item['id'] ) ) {
            return new \WP_Error( 'missing_id', 'Missing upstream id' );
        }
        $up_id = intval( $item['id'] );

        $existing = $this->find_post_by_fakestore_id( $up_id );

        $post_data = array(
            'post_title'   => isset( $item['title'] ) ? sanitize_text_field( $item['title'] ) : '',
            'post_content' => isset( $item['description'] ) ? wp_kses_post( $item['description'] ) : '',
            'post_status'  => 'publish',
            'post_type'    => 'product',
        );

        if ( $existing ) {
            $post_data['ID'] = $existing;
            $post_id = wp_update_post( $post_data, true );
            $is_new = false;
        } else {
            $post_id = wp_insert_post( $post_data, true );
            $is_new = true;
        }

        if ( is_wp_error( $post_id ) || ! $post_id ) {
            return new \WP_Error( 'post_error', 'Could not create or update product' );
        }

        $price = isset( $item['price'] ) ? floatval( $item['price'] ) : 0;
        update_post_meta( $post_id, '_regular_price', $price );
        update_post_meta( $post_id, '_price', $price );
        update_post_meta( $post_id, '_fakestore_id', $up_id );

        if ( ! empty( $item['category'] ) ) {
            $cat_name = sanitize_text_field( $item['category'] );
            $term = term_exists( $cat_name, 'product_cat' );
            if ( ! $term ) {
                $term = wp_insert_term( $cat_name, 'product_cat' );
            }

            if ( ! is_wp_error( $term ) ) {
                if ( is_array( $term ) && isset( $term['term_id'] ) ) {
                    $term_id = intval( $term['term_id'] );
                } else {
                    $term_id = intval( $term );
                }
                if ( $term_id ) {
                    wp_set_object_terms( $post_id, $term_id, 'product_cat', true );
                }
            }
        }

        if ( ! empty( $item['image'] ) ) {
            $image_url = esc_url_raw( $item['image'] );
            $attach_id = $this->sideload_image_to_post( $image_url, $post_id );
            if ( $attach_id ) {
                set_post_thumbnail( $post_id, $attach_id );
            }
        }

        wp_set_object_terms( $post_id, 'simple', 'product_type' );

        return $is_new ? 'imported' : 'updated';
    }

    /**
     * LPS - Find product by _fakestore_id meta.
     */
    protected function find_post_by_fakestore_id( $id ) {
        $args = array(
            'post_type'      => 'product',
            'meta_query'     => array(
                array(
                    'key'   => '_fakestore_id',
                    'value' => $id,
                ),
            ),
            'posts_per_page' => 1,
            'fields'         => 'ids',
        );
        $posts = get_posts( $args );
        if ( ! empty( $posts ) ) {
            return $posts[0];
        }
        return false;
    }

    /**
     * LPS - Sideload image into WP media for $post_id.
     */
    protected function sideload_image_to_post( $image_url, $post_id ) {
        if ( empty( $image_url ) ) {
            return false;
        }

        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $html = media_sideload_image( $image_url, $post_id );
        if ( is_wp_error( $html ) ) {
            return false;
        }

        if ( preg_match( '/<img.*?src=[\'"](.*?)[\'"]/', $html, $matches ) ) {
            $img_url = $matches[1];
            $attach_id = attachment_url_to_postid( $img_url );
            if ( $attach_id ) {
                return $attach_id;
            }
        }

        $filename = basename( parse_url( $image_url, PHP_URL_PATH ) );
        $qargs = array(
            'post_type'      => 'attachment',
            'posts_per_page' => 1,
            's'              => $filename,
            'fields'         => 'ids',
        );
        $posts = get_posts( $qargs );
        if ( ! empty( $posts ) ) {
            return $posts[0];
        }

        return false;
    }
}
