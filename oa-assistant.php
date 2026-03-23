<?php
/**
 * Plugin Name: OA Assistant
 * Plugin URI:  https://github.com/odear/oa-assistant
 * Description: AI Assistant toolkit for WordPress - REST API for Elementor page management
 * Version:     1.1.0
 * Author:      Olivier de Armenteras
 * License:     MIT
 * Text Domain: oa-assistant
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'OA_VERSION',     '1.1.0' );
define( 'OA_OPTION_KEY',  'oa_api_key' );
define( 'OA_NAMESPACE',   'oa/v1' );

// ──────────────────────────────────────────────────────────────────────────────
//  Authentication helper
// ──────────────────────────────────────────────────────────────────────────────

function oa_check_key( WP_REST_Request $request ) {
    $header = $request->get_header( 'X-OA-Key' );
    $stored = get_option( OA_OPTION_KEY, '' );

    if ( empty( $stored ) || $header !== $stored ) {
        return new WP_Error(
            'oa_forbidden',
            'Invalid or missing API key.',
            array( 'status' => 403 )
        );
    }

    return true;
}

// ──────────────────────────────────────────────────────────────────────────────
//  Register REST routes
// ──────────────────────────────────────────────────────────────────────────────

add_action( 'rest_api_init', 'oa_register_routes' );

function oa_register_routes() {

    // GET /oa/v1/status
    register_rest_route( OA_NAMESPACE, '/status', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'oa_endpoint_status',
        'permission_callback' => 'oa_check_key',
    ) );

    // GET /oa/v1/pages
    register_rest_route( OA_NAMESPACE, '/pages', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'oa_endpoint_pages',
        'permission_callback' => 'oa_check_key',
    ) );

    // GET|POST /oa/v1/progress
    register_rest_route( OA_NAMESPACE, '/progress', array(
        'methods'             => WP_REST_Server::READABLE . ', ' . WP_REST_Server::CREATABLE,
        'callback'            => 'oa_endpoint_progress',
        'permission_callback' => 'oa_check_key',
    ) );

    // POST /oa/v1/elementor-write
    register_rest_route( OA_NAMESPACE, '/elementor-write', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'oa_endpoint_elementor_write',
        'permission_callback' => 'oa_check_key',
        'args'                => array(
            'page_id' => array(
                'required'          => true,
                'validate_callback' => function( $v ) { return is_numeric( $v ) && $v > 0; },
                'sanitize_callback' => 'absint',
            ),
            'elementor_data' => array(
                'required' => true,
                'type'     => 'string',
            ),
        ),
    ) );

    // POST /oa/v1/elementor-flush
    register_rest_route( OA_NAMESPACE, '/elementor-flush', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'oa_endpoint_elementor_flush',
        'permission_callback' => 'oa_check_key',
        'args'                => array(
            'page_id' => array(
                'required'          => true,
                'validate_callback' => function( $v ) { return is_numeric( $v ) && $v > 0; },
                'sanitize_callback' => 'absint',
            ),
        ),
    ) );
}

// ──────────────────────────────────────────────────────────────────────────────
//  Endpoint: GET /oa/v1/status
// ──────────────────────────────────────────────────────────────────────────────

function oa_endpoint_status( WP_REST_Request $request ) {
    return rest_ensure_response( array(
        'ok'              => true,
        'plugin'          => 'OA Assistant',
        'version'         => OA_VERSION,
        'wordpress'       => get_bloginfo( 'version' ),
        'site_url'        => get_site_url(),
        'elementor_active' => defined( 'ELEMENTOR_VERSION' ),
        'elementor_version' => defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : null,
    ) );
}

// ──────────────────────────────────────────────────────────────────────────────
//  Endpoint: GET /oa/v1/pages
// ──────────────────────────────────────────────────────────────────────────────

function oa_endpoint_pages( WP_REST_Request $request ) {
    $posts = get_posts( array(
        'post_type'      => 'page',
        'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
        'posts_per_page' => -1,
        'orderby'        => 'menu_order title',
        'order'          => 'ASC',
    ) );

    $pages = array();
    foreach ( $posts as $post ) {
        $edit_mode      = get_post_meta( $post->ID, '_elementor_edit_mode', true );
        $elementor_data = get_post_meta( $post->ID, '_elementor_data', true );

        $is_elementor = ( $edit_mode === 'builder' )
            || ( ! empty( $elementor_data ) && $elementor_data !== '[]' );

        $pages[] = array(
            'id'               => $post->ID,
            'title'            => $post->post_title,
            'slug'             => $post->post_name,
            'status'           => $post->post_status,
            'link'             => get_permalink( $post->ID ),
            'modified'         => $post->post_modified,
            'elementor'        => $is_elementor,
            'elementor_mode'   => $edit_mode ?: null,
        );
    }

    return rest_ensure_response( $pages );
}

// ──────────────────────────────────────────────────────────────────────────────
//  Endpoint: GET|POST /oa/v1/progress
// ──────────────────────────────────────────────────────────────────────────────

function oa_get_progress_file() {
    $upload = wp_upload_dir();
    $dir    = $upload['basedir'] . '/oa-dashboard';
    return $dir . '/progress.json';
}

function oa_endpoint_progress( WP_REST_Request $request ) {
    $file = oa_get_progress_file();

    // POST – write progress
    if ( $request->get_method() === 'POST' ) {
        $body = $request->get_json_params();
        if ( ! is_array( $body ) ) {
            return new WP_Error( 'oa_invalid_body', 'JSON body required.', array( 'status' => 400 ) );
        }

        $dir = dirname( $file );
        if ( ! file_exists( $dir ) ) {
            wp_mkdir_p( $dir );
        }

        // Keep track of when this was last written
        $body['_updated'] = time();

        $result = file_put_contents( $file, wp_json_encode( $body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
        if ( $result === false ) {
            return new WP_Error( 'oa_write_error', 'Could not write progress file.', array( 'status' => 500 ) );
        }

        return rest_ensure_response( array( 'ok' => true ) );
    }

    // GET – read progress
    if ( ! file_exists( $file ) ) {
        return rest_ensure_response( array(
            'active'     => false,
            'percentage' => 0,
            'steps'      => array(),
        ) );
    }

    $json = file_get_contents( $file );
    $data = json_decode( $json, true );

    if ( ! is_array( $data ) ) {
        return new WP_Error( 'oa_parse_error', 'Progress file is malformed.', array( 'status' => 500 ) );
    }

    return rest_ensure_response( $data );
}

// ──────────────────────────────────────────────────────────────────────────────
//  Endpoint: POST /oa/v1/elementor-write
// ──────────────────────────────────────────────────────────────────────────────

function oa_endpoint_elementor_write( WP_REST_Request $request ) {
    $page_id        = $request->get_param( 'page_id' );
    $elementor_data = $request->get_param( 'elementor_data' );

    if ( ! get_post( $page_id ) ) {
        return new WP_Error( 'oa_not_found', "Post {$page_id} not found.", array( 'status' => 404 ) );
    }

    // Validate that elementor_data is valid JSON
    $decoded = json_decode( $elementor_data );
    if ( json_last_error() !== JSON_ERROR_NONE ) {
        return new WP_Error( 'oa_invalid_json', 'elementor_data is not valid JSON.', array( 'status' => 400 ) );
    }

    // Write Elementor meta – wp_slash prevents WordPress from double-encoding slashes
    update_post_meta( $page_id, '_elementor_data',          wp_slash( $elementor_data ) );
    update_post_meta( $page_id, '_elementor_edit_mode',     'builder' );
    update_post_meta( $page_id, '_elementor_template_type', 'wp-page' );

    // Clear Elementor CSS cache for this page
    if ( defined( 'ELEMENTOR_VERSION' ) ) {
        Elementor\Plugin::$instance->files_manager->clear_cache();
    }

    // Purge LiteSpeed cache for this post
    do_action( 'litespeed_purge_post', $page_id );

    // Also clear any W3TC / WP Super Cache / WP Rocket page cache
    if ( function_exists( 'w3tc_flush_post' ) ) {
        w3tc_flush_post( $page_id );
    }
    if ( function_exists( 'wp_cache_post_change' ) ) {
        wp_cache_post_change( $page_id );
    }
    if ( function_exists( 'rocket_clean_post' ) ) {
        rocket_clean_post( $page_id );
    }

    return rest_ensure_response( array(
        'ok'      => true,
        'page_id' => $page_id,
    ) );
}

// ──────────────────────────────────────────────────────────────────────────────
//  Endpoint: POST /oa/v1/elementor-flush
// ──────────────────────────────────────────────────────────────────────────────

function oa_endpoint_elementor_flush( WP_REST_Request $request ) {
    $page_id = $request->get_param( 'page_id' );

    if ( ! get_post( $page_id ) ) {
        return new WP_Error( 'oa_not_found', "Post {$page_id} not found.", array( 'status' => 404 ) );
    }

    // Regenerate all Elementor CSS files
    if ( defined( 'ELEMENTOR_VERSION' ) ) {
        do_action( 'elementor/core/files/clear_cache' );
        Elementor\Plugin::$instance->files_manager->clear_cache();
    }

    // Purge LiteSpeed cache for this post and globally
    do_action( 'litespeed_purge_post', $page_id );
    do_action( 'litespeed_purge_all' );

    // Other cache plugins
    if ( function_exists( 'w3tc_flush_post' ) ) {
        w3tc_flush_post( $page_id );
    }
    if ( function_exists( 'rocket_clean_post' ) ) {
        rocket_clean_post( $page_id );
    }

    return rest_ensure_response( array( 'ok' => true ) );
}

// ──────────────────────────────────────────────────────────────────────────────
//  Admin settings page – set / regenerate API key
// ──────────────────────────────────────────────────────────────────────────────

add_action( 'admin_menu', 'oa_admin_menu' );

function oa_admin_menu() {
    add_options_page(
        'OA Assistant',
        'OA Assistant',
        'manage_options',
        'oa-assistant',
        'oa_admin_page'
    );
}

add_action( 'admin_init', 'oa_admin_init' );

function oa_admin_init() {
    register_setting( 'oa_settings_group', OA_OPTION_KEY, array(
        'sanitize_callback' => 'sanitize_text_field',
    ) );
}

function oa_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Access denied.' );
    }

    // Generate new key action
    if ( isset( $_POST['oa_generate_key'] ) && check_admin_referer( 'oa_generate_nonce' ) ) {
        $new_key = wp_generate_password( 32, false );
        update_option( OA_OPTION_KEY, $new_key );
        echo '<div class="notice notice-success"><p>New API key generated.</p></div>';
    }

    $current_key = get_option( OA_OPTION_KEY, '' );
    $site_url    = get_site_url();
    ?>
    <div class="wrap">
        <h1>OA Assistant <span style="font-size:14px;color:#666;">v<?php echo esc_html( OA_VERSION ); ?></span></h1>

        <form method="post" action="options.php">
            <?php settings_fields( 'oa_settings_group' ); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="<?php echo OA_OPTION_KEY; ?>">API Key</label></th>
                    <td>
                        <input type="text" id="<?php echo OA_OPTION_KEY; ?>"
                               name="<?php echo OA_OPTION_KEY; ?>"
                               value="<?php echo esc_attr( $current_key ); ?>"
                               class="regular-text code" style="width:340px">
                        <p class="description">Use this key in the <code>X-OA-Key</code> header for all API requests.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button( 'Save API Key' ); ?>
        </form>

        <form method="post">
            <?php wp_nonce_field( 'oa_generate_nonce' ); ?>
            <input type="hidden" name="oa_generate_key" value="1">
            <?php submit_button( 'Generate New Key', 'secondary' ); ?>
        </form>

        <hr>
        <h2>Available Endpoints</h2>
        <table class="widefat striped" style="max-width:700px">
            <thead><tr><th>Method</th><th>Endpoint</th><th>Description</th></tr></thead>
            <tbody>
                <tr><td>GET</td><td><code><?php echo esc_html( $site_url ); ?>/wp-json/oa/v1/status</code></td><td>Plugin status</td></tr>
                <tr><td>GET</td><td><code><?php echo esc_html( $site_url ); ?>/wp-json/oa/v1/pages</code></td><td>All pages with Elementor status</td></tr>
                <tr><td>GET/POST</td><td><code><?php echo esc_html( $site_url ); ?>/wp-json/oa/v1/progress</code></td><td>Read or write progress JSON</td></tr>
                <tr><td>POST</td><td><code><?php echo esc_html( $site_url ); ?>/wp-json/oa/v1/elementor-write</code></td><td>Write Elementor page data</td></tr>
                <tr><td>POST</td><td><code><?php echo esc_html( $site_url ); ?>/wp-json/oa/v1/elementor-flush</code></td><td>Flush Elementor + cache</td></tr>
            </tbody>
        </table>
    </div>
    <?php
}

// ──────────────────────────────────────────────────────────────────────────────
//  Activation: generate API key if none set
// ──────────────────────────────────────────────────────────────────────────────

register_activation_hook( __FILE__, 'oa_activate' );

function oa_activate() {
    if ( empty( get_option( OA_OPTION_KEY, '' ) ) ) {
        update_option( OA_OPTION_KEY, wp_generate_password( 32, false ) );
    }
}
