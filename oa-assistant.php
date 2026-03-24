<?php
/**
 * Plugin Name: OA Assistant
 * Plugin URI:  https://github.com/odear/oa-assistant
 * Description: AI Assistant toolkit for WordPress - REST API for Elementor page management
 * Version:     1.3.0
 * Author:      Olivier de Armenteras
 * License:     MIT
 * Text Domain: oa-assistant
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'OA_VERSION',     '1.3.0' );
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

    // GET /oa/v1/menus
    register_rest_route( OA_NAMESPACE, '/menus', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'oa_endpoint_menus',
        'permission_callback' => 'oa_check_key',
    ) );

    // POST /oa/v1/menu-item
    register_rest_route( OA_NAMESPACE, '/menu-item', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'oa_endpoint_menu_item',
        'permission_callback' => 'oa_check_key',
        'args'                => array(
            'menu_id'    => array( 'required' => true,  'sanitize_callback' => 'absint' ),
            'title'      => array( 'required' => true,  'sanitize_callback' => 'sanitize_text_field' ),
            'url'        => array( 'required' => true,  'sanitize_callback' => 'esc_url_raw' ),
            'menu_order' => array( 'required' => false, 'sanitize_callback' => 'absint', 'default' => 0 ),
        ),
    ) );

    // GET /oa/v1/header-nav-item
    register_rest_route( OA_NAMESPACE, '/header-nav-item', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'oa_endpoint_get_header_nav_items',
        'permission_callback' => 'oa_check_key',
        'args'                => array(
            'header_id' => array( 'required' => false, 'sanitize_callback' => 'absint' ),
        ),
    ) );

    // POST /oa/v1/header-nav-item
    register_rest_route( OA_NAMESPACE, '/header-nav-item', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'oa_endpoint_header_nav_item',
        'permission_callback' => 'oa_check_key',
        'args'                => array(
            'title'     => array( 'required' => true,  'sanitize_callback' => 'sanitize_text_field' ),
            'url'       => array( 'required' => true,  'sanitize_callback' => 'esc_url_raw' ),
            'header_id' => array( 'required' => false, 'sanitize_callback' => 'absint' ),
        ),
    ) );

    // DELETE /oa/v1/header-nav-item
    register_rest_route( OA_NAMESPACE, '/header-nav-item', array(
        'methods'             => WP_REST_Server::DELETABLE,
        'callback'            => 'oa_endpoint_delete_header_nav_item',
        'permission_callback' => 'oa_check_key',
        'args'                => array(
            'url'       => array( 'required' => true,  'sanitize_callback' => 'sanitize_text_field' ),
            'header_id' => array( 'required' => false, 'sanitize_callback' => 'absint' ),
        ),
    ) );

    // GET /oa/v1/footer-nav-item
    register_rest_route( OA_NAMESPACE, '/footer-nav-item', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'oa_endpoint_get_footer_nav_items',
        'permission_callback' => 'oa_check_key',
        'args'                => array(
            'footer_id' => array( 'required' => false, 'sanitize_callback' => 'absint' ),
        ),
    ) );

    // POST /oa/v1/footer-nav-item
    register_rest_route( OA_NAMESPACE, '/footer-nav-item', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'oa_endpoint_footer_nav_item',
        'permission_callback' => 'oa_check_key',
        'args'                => array(
            'title'     => array( 'required' => true,  'sanitize_callback' => 'sanitize_text_field' ),
            'url'       => array( 'required' => true,  'sanitize_callback' => 'esc_url_raw' ),
            'footer_id' => array( 'required' => false, 'sanitize_callback' => 'absint' ),
        ),
    ) );

    // DELETE /oa/v1/footer-nav-item
    register_rest_route( OA_NAMESPACE, '/footer-nav-item', array(
        'methods'             => WP_REST_Server::DELETABLE,
        'callback'            => 'oa_endpoint_delete_footer_nav_item',
        'permission_callback' => 'oa_check_key',
        'args'                => array(
            'url'       => array( 'required' => true,  'sanitize_callback' => 'sanitize_text_field' ),
            'footer_id' => array( 'required' => false, 'sanitize_callback' => 'absint' ),
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
//  Endpoint: GET /oa/v1/menus
// ──────────────────────────────────────────────────────────────────────────────

function oa_endpoint_menus( WP_REST_Request $request ) {
    $menus = wp_get_nav_menus();
    $result = array();
    foreach ( $menus as $menu ) {
        $result[] = array(
            'id'    => $menu->term_id,
            'name'  => $menu->name,
            'slug'  => $menu->slug,
            'count' => $menu->count,
        );
    }
    return rest_ensure_response( $result );
}

// ──────────────────────────────────────────────────────────────────────────────
//  Endpoint: POST /oa/v1/menu-item
// ──────────────────────────────────────────────────────────────────────────────

function oa_endpoint_menu_item( WP_REST_Request $request ) {
    $menu_id    = $request->get_param( 'menu_id' );
    $title      = $request->get_param( 'title' );
    $url        = $request->get_param( 'url' );
    $menu_order = $request->get_param( 'menu_order' ) ?: 0;

    // Verify menu exists
    $menu = wp_get_nav_menu_object( $menu_id );
    if ( ! $menu ) {
        return new WP_Error( 'oa_not_found', "Menu {$menu_id} not found.", array( 'status' => 404 ) );
    }

    // Check if an item with this URL already exists in this menu
    $existing_items = wp_get_nav_menu_items( $menu_id );
    if ( is_array( $existing_items ) ) {
        foreach ( $existing_items as $item ) {
            if ( $item->url === $url ) {
                return rest_ensure_response( array(
                    'ok'      => true,
                    'item_id' => $item->ID,
                    'note'    => 'Item already exists',
                ) );
            }
        }
    }

    $item_id = wp_update_nav_menu_item( $menu_id, 0, array(
        'menu-item-title'   => $title,
        'menu-item-url'     => $url,
        'menu-item-status'  => 'publish',
        'menu-item-type'    => 'custom',
        'menu-item-position' => $menu_order,
    ) );

    if ( is_wp_error( $item_id ) ) {
        return new WP_Error( 'oa_menu_error', $item_id->get_error_message(), array( 'status' => 500 ) );
    }

    return rest_ensure_response( array(
        'ok'      => true,
        'item_id' => $item_id,
        'menu_id' => $menu_id,
        'title'   => $title,
        'url'     => $url,
    ) );
}

// ──────────────────────────────────────────────────────────────────────────────
//  Endpoint: POST /oa/v1/header-nav-item
// ──────────────────────────────────────────────────────────────────────────────

/**
 * Recursively walk Elementor widget tree and return a reference to the first
 * text-editor widget whose 'editor' content contains a <nav tag.
 */
function &oa_find_nav_widget( array &$elements ) {
    $null = null;
    foreach ( $elements as &$element ) {
        if (
            isset( $element['widgetType'] ) &&
            $element['widgetType'] === 'text-editor' &&
            isset( $element['settings']['editor'] ) &&
            stripos( $element['settings']['editor'], '<nav' ) !== false
        ) {
            return $element;
        }
        if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
            $found = &oa_find_nav_widget( $element['elements'] );
            if ( $found !== null ) {
                return $found;
            }
        }
    }
    return $null;
}

/**
 * Generic resolver for elementor-hf templates (header or footer).
 * Returns int post ID or WP_Error.
 */
function oa_resolve_nav_post_id( $template_type, $id_override ) {
    if ( ! empty( $id_override ) ) {
        if ( ! get_post( $id_override ) ) {
            return new WP_Error( 'oa_not_found', "Post {$id_override} not found.", array( 'status' => 404 ) );
        }
        return (int) $id_override;
    }

    $posts = get_posts( array(
        'post_type'      => 'elementor-hf',
        'post_status'    => array( 'publish', 'draft', 'private' ),
        'posts_per_page' => 1,
        'meta_query'     => array(
            array(
                'key'     => 'ehf_template_type',
                'value'   => $template_type,
                'compare' => '=',
            ),
        ),
    ) );

    if ( ! empty( $posts ) ) {
        return (int) $posts[0]->ID;
    }

    // Fallback: title contains template_type keyword
    $all_hf = get_posts( array(
        'post_type'      => 'elementor-hf',
        'post_status'    => array( 'publish', 'draft', 'private' ),
        'posts_per_page' => -1,
    ) );
    foreach ( $all_hf as $p ) {
        if ( stripos( $p->post_title, $template_type ) !== false ) {
            return (int) $p->ID;
        }
    }

    return new WP_Error(
        'oa_no_template',
        "Could not auto-detect {$template_type} template. Pass {$template_type}_id explicitly.",
        array( 'status' => 404 )
    );
}

function oa_resolve_header_post_id( WP_REST_Request $request ) {
    return oa_resolve_nav_post_id( 'header', $request->get_param( 'header_id' ) );
}

function oa_resolve_footer_post_id( WP_REST_Request $request ) {
    return oa_resolve_nav_post_id( 'footer', $request->get_param( 'footer_id' ) );
}

/**
 * Parse all <a> tags from an HTML string and return [{title, url}].
 */
function oa_parse_nav_items( $html ) {
    $items = array();
    preg_match_all( '/<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $html, $matches, PREG_SET_ORDER );
    foreach ( $matches as $m ) {
        $items[] = array(
            'title' => wp_strip_all_tags( $m[2] ),
            'url'   => $m[1],
        );
    }
    return $items;
}

function oa_endpoint_header_nav_item( WP_REST_Request $request ) {
    $title = $request->get_param( 'title' );
    $url   = $request->get_param( 'url' );

    // Resolve header post ID
    $header_post_id = oa_resolve_header_post_id( $request );
    if ( is_wp_error( $header_post_id ) ) {
        return $header_post_id;
    }

    // Load Elementor data
    $raw = get_post_meta( $header_post_id, '_elementor_data', true );
    if ( empty( $raw ) || $raw === '[]' ) {
        return new WP_Error(
            'oa_no_data',
            "No Elementor data found for post {$header_post_id}.",
            array( 'status' => 404 )
        );
    }

    $data = json_decode( $raw, true );
    if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $data ) ) {
        return new WP_Error(
            'oa_parse_error',
            "Could not parse Elementor data for post {$header_post_id}.",
            array( 'status' => 500 )
        );
    }

    // Find the text-editor widget containing a <nav>
    $widget = &oa_find_nav_widget( $data );
    if ( $widget === null ) {
        return new WP_Error(
            'oa_not_found',
            "No text-editor widget with <nav> found in post {$header_post_id}.",
            array( 'status' => 404 )
        );
    }

    // Build the new <a> tag
    $style   = 'color: rgba(255,255,255,0.75); text-decoration: none; font-size: 14px; font-family: inherit;';
    $new_tag = '<a href="' . esc_url( $url ) . '" style="' . esc_attr( $style ) . '">' . esc_html( $title ) . '</a>';

    // Insert before </nav>
    $editor_html = $widget['settings']['editor'];
    if ( stripos( $editor_html, '</nav>' ) === false ) {
        return new WP_Error(
            'oa_no_nav_close',
            'Closing </nav> tag not found in widget content.',
            array( 'status' => 422 )
        );
    }

    $widget['settings']['editor'] = str_ireplace( '</nav>', $new_tag . '</nav>', $editor_html );

    // Save back
    $encoded = wp_json_encode( $data );
    if ( $encoded === false ) {
        return new WP_Error( 'oa_encode_error', 'Could not re-encode Elementor data.', array( 'status' => 500 ) );
    }

    update_post_meta( $header_post_id, '_elementor_data', wp_slash( $encoded ) );
    update_post_meta( $header_post_id, '_elementor_edit_mode', 'builder' );
    oa_flush_post_cache( $header_post_id );

    return rest_ensure_response( array(
        'ok'      => true,
        'post_id' => $header_post_id,
        'title'   => $title,
        'url'     => $url,
    ) );
}

// ──────────────────────────────────────────────────────────────────────────────
//  Endpoint: DELETE /oa/v1/header-nav-item
// ──────────────────────────────────────────────────────────────────────────────

function oa_endpoint_delete_header_nav_item( WP_REST_Request $request ) {
    $url = $request->get_param( 'url' );

    // Resolve header post ID
    $header_post_id = oa_resolve_header_post_id( $request );
    if ( is_wp_error( $header_post_id ) ) {
        return $header_post_id;
    }

    // Load Elementor data
    $raw = get_post_meta( $header_post_id, '_elementor_data', true );
    if ( empty( $raw ) || $raw === '[]' ) {
        return new WP_Error(
            'oa_no_data',
            "No Elementor data found for post {$header_post_id}.",
            array( 'status' => 404 )
        );
    }

    $data = json_decode( $raw, true );
    if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $data ) ) {
        return new WP_Error(
            'oa_parse_error',
            "Could not parse Elementor data for post {$header_post_id}.",
            array( 'status' => 500 )
        );
    }

    // Find the text-editor widget containing a <nav>
    $widget = &oa_find_nav_widget( $data );
    if ( $widget === null ) {
        return new WP_Error(
            'oa_not_found',
            "No text-editor widget with <nav> found in post {$header_post_id}.",
            array( 'status' => 404 )
        );
    }

    $editor_html = $widget['settings']['editor'];

    // Remove any <a> tag whose href contains the given url.
    // Matches both single and double quoted href attributes.
    $escaped_url  = preg_quote( $url, '/' );
    $pattern      = '/<a\s[^>]*href=["\'][^"\']*' . $escaped_url . '[^"\']*["\'][^>]*>.*?<\/a>/is';
    $new_html     = preg_replace( $pattern, '', $editor_html );

    if ( $new_html === $editor_html ) {
        return new WP_Error(
            'oa_not_found',
            "No <a> tag with href matching '{$url}' found in nav widget.",
            array( 'status' => 404 )
        );
    }

    $widget['settings']['editor'] = $new_html;

    // Save back
    $encoded = wp_json_encode( $data );
    if ( $encoded === false ) {
        return new WP_Error( 'oa_encode_error', 'Could not re-encode Elementor data.', array( 'status' => 500 ) );
    }

    update_post_meta( $header_post_id, '_elementor_data', wp_slash( $encoded ) );
    update_post_meta( $header_post_id, '_elementor_edit_mode', 'builder' );
    oa_flush_post_cache( $header_post_id );

    return rest_ensure_response( array(
        'ok'      => true,
        'post_id' => $header_post_id,
        'removed' => $url,
    ) );
}

// ──────────────────────────────────────────────────────────────────────────────
//  Endpoint: GET /oa/v1/header-nav-item
// ──────────────────────────────────────────────────────────────────────────────

function oa_endpoint_get_header_nav_items( WP_REST_Request $request ) {
    $post_id = oa_resolve_header_post_id( $request );
    if ( is_wp_error( $post_id ) ) {
        return $post_id;
    }

    $raw = get_post_meta( $post_id, '_elementor_data', true );
    if ( empty( $raw ) || $raw === '[]' ) {
        return new WP_Error( 'oa_no_data', "No Elementor data found for post {$post_id}.", array( 'status' => 404 ) );
    }

    $data = json_decode( $raw, true );
    if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $data ) ) {
        return new WP_Error( 'oa_parse_error', "Could not parse Elementor data for post {$post_id}.", array( 'status' => 500 ) );
    }

    $widget = &oa_find_nav_widget( $data );
    if ( $widget === null ) {
        return new WP_Error( 'oa_not_found', "No text-editor widget with <nav> found in post {$post_id}.", array( 'status' => 404 ) );
    }

    return rest_ensure_response( array(
        'post_id' => $post_id,
        'items'   => oa_parse_nav_items( $widget['settings']['editor'] ),
    ) );
}

// ──────────────────────────────────────────────────────────────────────────────
//  Shared helper: flush caches for a template post
// ──────────────────────────────────────────────────────────────────────────────

function oa_flush_post_cache( $post_id ) {
    if ( defined( 'ELEMENTOR_VERSION' ) ) {
        Elementor\Plugin::$instance->files_manager->clear_cache();
    }
    do_action( 'litespeed_purge_post', $post_id );
    if ( function_exists( 'rocket_clean_post' ) ) {
        rocket_clean_post( $post_id );
    }
}

// ──────────────────────────────────────────────────────────────────────────────
//  Endpoint: GET /oa/v1/footer-nav-item
// ──────────────────────────────────────────────────────────────────────────────

function oa_endpoint_get_footer_nav_items( WP_REST_Request $request ) {
    $post_id = oa_resolve_footer_post_id( $request );
    if ( is_wp_error( $post_id ) ) {
        return $post_id;
    }

    $raw = get_post_meta( $post_id, '_elementor_data', true );
    if ( empty( $raw ) || $raw === '[]' ) {
        return new WP_Error( 'oa_no_data', "No Elementor data found for post {$post_id}.", array( 'status' => 404 ) );
    }

    $data = json_decode( $raw, true );
    if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $data ) ) {
        return new WP_Error( 'oa_parse_error', "Could not parse Elementor data for post {$post_id}.", array( 'status' => 500 ) );
    }

    $widget = &oa_find_nav_widget( $data );
    if ( $widget === null ) {
        return new WP_Error( 'oa_not_found', "No text-editor widget with <nav> found in post {$post_id}.", array( 'status' => 404 ) );
    }

    return rest_ensure_response( array(
        'post_id' => $post_id,
        'items'   => oa_parse_nav_items( $widget['settings']['editor'] ),
    ) );
}

// ──────────────────────────────────────────────────────────────────────────────
//  Endpoint: POST /oa/v1/footer-nav-item
// ──────────────────────────────────────────────────────────────────────────────

function oa_endpoint_footer_nav_item( WP_REST_Request $request ) {
    $title = $request->get_param( 'title' );
    $url   = $request->get_param( 'url' );

    $post_id = oa_resolve_footer_post_id( $request );
    if ( is_wp_error( $post_id ) ) {
        return $post_id;
    }

    $raw = get_post_meta( $post_id, '_elementor_data', true );
    if ( empty( $raw ) || $raw === '[]' ) {
        return new WP_Error( 'oa_no_data', "No Elementor data found for post {$post_id}.", array( 'status' => 404 ) );
    }

    $data = json_decode( $raw, true );
    if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $data ) ) {
        return new WP_Error( 'oa_parse_error', "Could not parse Elementor data for post {$post_id}.", array( 'status' => 500 ) );
    }

    $widget = &oa_find_nav_widget( $data );
    if ( $widget === null ) {
        return new WP_Error( 'oa_not_found', "No text-editor widget with <nav> found in post {$post_id}.", array( 'status' => 404 ) );
    }

    $editor_html = $widget['settings']['editor'];
    if ( stripos( $editor_html, '</nav>' ) === false ) {
        return new WP_Error( 'oa_no_nav_close', 'Closing </nav> tag not found in widget content.', array( 'status' => 422 ) );
    }

    $style   = 'color: rgba(255,255,255,0.75); text-decoration: none; font-size: 14px; font-family: inherit;';
    $new_tag = '<a href="' . esc_url( $url ) . '" style="' . esc_attr( $style ) . '">' . esc_html( $title ) . '</a>';
    $widget['settings']['editor'] = str_ireplace( '</nav>', $new_tag . '</nav>', $editor_html );

    $encoded = wp_json_encode( $data );
    if ( $encoded === false ) {
        return new WP_Error( 'oa_encode_error', 'Could not re-encode Elementor data.', array( 'status' => 500 ) );
    }

    update_post_meta( $post_id, '_elementor_data', wp_slash( $encoded ) );
    update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
    oa_flush_post_cache( $post_id );

    return rest_ensure_response( array(
        'ok'      => true,
        'post_id' => $post_id,
        'title'   => $title,
        'url'     => $url,
    ) );
}

// ──────────────────────────────────────────────────────────────────────────────
//  Endpoint: DELETE /oa/v1/footer-nav-item
// ──────────────────────────────────────────────────────────────────────────────

function oa_endpoint_delete_footer_nav_item( WP_REST_Request $request ) {
    $url = $request->get_param( 'url' );

    $post_id = oa_resolve_footer_post_id( $request );
    if ( is_wp_error( $post_id ) ) {
        return $post_id;
    }

    $raw = get_post_meta( $post_id, '_elementor_data', true );
    if ( empty( $raw ) || $raw === '[]' ) {
        return new WP_Error( 'oa_no_data', "No Elementor data found for post {$post_id}.", array( 'status' => 404 ) );
    }

    $data = json_decode( $raw, true );
    if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $data ) ) {
        return new WP_Error( 'oa_parse_error', "Could not parse Elementor data for post {$post_id}.", array( 'status' => 500 ) );
    }

    $widget = &oa_find_nav_widget( $data );
    if ( $widget === null ) {
        return new WP_Error( 'oa_not_found', "No text-editor widget with <nav> found in post {$post_id}.", array( 'status' => 404 ) );
    }

    $editor_html  = $widget['settings']['editor'];
    $escaped_url  = preg_quote( $url, '/' );
    $pattern      = '/<a\s[^>]*href=["\'][^"\']*' . $escaped_url . '[^"\']*["\'][^>]*>.*?<\/a>/is';
    $new_html     = preg_replace( $pattern, '', $editor_html );

    if ( $new_html === $editor_html ) {
        return new WP_Error( 'oa_not_found', "No <a> tag with href matching '{$url}' found in nav widget.", array( 'status' => 404 ) );
    }

    $widget['settings']['editor'] = $new_html;

    $encoded = wp_json_encode( $data );
    if ( $encoded === false ) {
        return new WP_Error( 'oa_encode_error', 'Could not re-encode Elementor data.', array( 'status' => 500 ) );
    }

    update_post_meta( $post_id, '_elementor_data', wp_slash( $encoded ) );
    update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
    oa_flush_post_cache( $post_id );

    return rest_ensure_response( array(
        'ok'      => true,
        'post_id' => $post_id,
        'removed' => $url,
    ) );
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
                <tr><td>GET</td><td><code><?php echo esc_html( $site_url ); ?>/wp-json/oa/v1/menus</code></td><td>List all navigation menus</td></tr>
                <tr><td>POST</td><td><code><?php echo esc_html( $site_url ); ?>/wp-json/oa/v1/menu-item</code></td><td>Add item to navigation menu</td></tr>
                <tr><td>GET</td><td><code><?php echo esc_html( $site_url ); ?>/wp-json/oa/v1/header-nav-item</code></td><td>List &lt;a&gt; items in header nav</td></tr>
                <tr><td>POST</td><td><code><?php echo esc_html( $site_url ); ?>/wp-json/oa/v1/header-nav-item</code></td><td>Add &lt;a&gt; item to header nav</td></tr>
                <tr><td>DELETE</td><td><code><?php echo esc_html( $site_url ); ?>/wp-json/oa/v1/header-nav-item</code></td><td>Remove &lt;a&gt; item from header nav by url</td></tr>
                <tr><td>GET</td><td><code><?php echo esc_html( $site_url ); ?>/wp-json/oa/v1/footer-nav-item</code></td><td>List &lt;a&gt; items in footer nav</td></tr>
                <tr><td>POST</td><td><code><?php echo esc_html( $site_url ); ?>/wp-json/oa/v1/footer-nav-item</code></td><td>Add &lt;a&gt; item to footer nav</td></tr>
                <tr><td>DELETE</td><td><code><?php echo esc_html( $site_url ); ?>/wp-json/oa/v1/footer-nav-item</code></td><td>Remove &lt;a&gt; item from footer nav by url</td></tr>
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
