<?php
namespace FakeStoreSync;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Admin {

    public function init() {
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_post_fss_sync_now', array( $this, 'handle_sync_now' ) );
        add_action( 'admin_notices', array( $this, 'admin_notices' ) );
    }

    public function add_menu() {
        add_menu_page(
            'FakeStore Sync',
            'FakeStore Sync',
            'manage_options',
            'fss-settings',
            array( $this, 'settings_page' ),
            'dashicons-update'
        );
    }

    public function register_settings() {
        register_setting(
            'fss_settings_group',
            'fss_settings',
            array( $this, 'sanitize_settings' )
        );

        add_settings_section(
            'fss_main_section',
            'API Settings',
            null,
            'fss-settings'
        );

        add_settings_field(
            'api_url',
            'API Base URL',
            array( $this, 'field_api_url' ),
            'fss-settings',
            'fss_main_section'
        );

         // LPS - Push updates toggle
        add_settings_field(
            'push_enabled',
            'Push updates back to API',
            array( $this, 'field_push_enabled' ),
            'fss-settings',
            'fss_main_section'
        );

        // LPS - Conflict resolution dropdown
        add_settings_field(
            'conflict_mode',
            'Conflict Resolution',
            array( $this, 'field_conflict_mode' ),
            'fss-settings',
            'fss_main_section'
        );
    }

    public function sanitize_settings( $input ) {
        $output = array();
        if ( isset( $input['api_url'] ) ) {
            $output['api_url'] = esc_url_raw( trim( $input['api_url'] ) );
        }

        $output['push_enabled']   = ! empty( $input['push_enabled'] ) ? 1 : 0;

        if ( isset( $input['conflict_mode'] ) && in_array( $input['conflict_mode'], array( 'local', 'upstream' ), true ) ) {
            $output['conflict_mode'] = $input['conflict_mode'];
        } else {
            $output['conflict_mode'] = 'local'; // default
        }

        return $output;
    }

    public function field_api_url() {
        $opts = get_option( 'fss_settings', array( 'api_url' => 'https://fakestoreapi.com' ) );
        $val = isset( $opts['api_url'] ) ? $opts['api_url'] : 'https://fakestoreapi.com';
        echo '<input type="text" name="fss_settings[api_url]" value="' . esc_attr( $val ) . '" class="regular-text" />';
        echo '<p class="description">The base URL for FakeStore API.</p>';
    }

    public function field_conflict_mode() {
        $opts = get_option( 'fss_settings', array() );
        $val  = isset( $opts['conflict_mode'] ) ? $opts['conflict_mode'] : 'local';
        ?>
        <select name="fss_settings[conflict_mode]">
            <option value="local" <?php selected( $val, 'local' ); ?>>Local wins (WooCommerce overwrites FakeStore)</option>
            <option value="upstream" <?php selected( $val, 'upstream' ); ?>>Upstream wins (FakeStore overwrites WooCommerce)</option>
        </select>
        <p class="description">Choose which side wins when both WooCommerce and FakeStore have edits.</p>
        <?php
    }

    public function field_push_enabled() {
        $opts = get_option( 'fss_settings', array() );
        $val  = isset( $opts['push_enabled'] ) ? (int) $opts['push_enabled'] : 0;
        ?>
        <label>
            <input type="checkbox" name="fss_settings[push_enabled]" value="1" <?php checked( 1, $val ); ?> />
            Enable pushing product updates from WooCommerce back to FakeStore API
        </label>
        <?php
    }

    public function settings_page() {
        ?>
        <div class="wrap">
            <h1>FakeStore Sync</h1>

            <form method="post" action="options.php">
                <?php
                settings_fields( 'fss_settings_group' );
                do_settings_sections( 'fss-settings' );
                submit_button();
                ?>
            </form>

            <h2>Manual Sync</h2>
            <p><strong>Last sync:</strong> <?php echo esc_html( get_option( 'fss_last_sync', 'Never' ) ); ?></p>
            <?php $counts = get_option( 'fss_last_counts', array( 'imported' => 0, 'updated' => 0 ) ); ?>
            <p><strong>Imported:</strong> <?php echo intval( $counts['imported'] ); ?> &nbsp; <strong>Updated:</strong> <?php echo intval( $counts['updated'] ); ?></p>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'fss_sync_now_nonce', 'fss_sync_now_nonce_field' ); ?>
                <input type="hidden" name="action" value="fss_sync_now" />
                <?php submit_button( 'Sync Now' ); ?>
            </form>
        </div>
        <?php
    }

    /**
     * LPS - Handle the sync-now
     */
    public function handle_sync_now() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Insufficient permissions' );
        }

        check_admin_referer( 'fss_sync_now_nonce', 'fss_sync_now_nonce_field' );

        $sync = new Sync();
        $result = $sync->run_sync_manual();

        $redirect = wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=fss-settings' );
        $redirect = add_query_arg( 'fss_sync_result', '1', $redirect );
        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * LPS - Show a notice after sync
     */
    public function admin_notices() {
        if ( isset( $_GET['fss_sync_result'] ) ) {
            $last = get_option( 'fss_last_sync', 'Never' );
            $counts = get_option( 'fss_last_counts', array( 'imported' => 0, 'updated' => 0 ) );
            ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    Sync completed. Last sync: <?php echo esc_html( $last ); ?>.
                    Imported: <?php echo intval( $counts['imported'] ); ?>,
                    Updated: <?php echo intval( $counts['updated'] ); ?>
                </p>
            </div>
            <?php
        }
    }
}
