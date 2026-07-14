<?php
/**
 * Main plugin class (composed from includes/ traits).
 *
 * @package QPFP_Pheasantly
 */

defined('ABSPATH') || exit;

/**
 * Main plugin class.
 */
class QPFP_Pheasantly {
    private const DEBUG_LOGGING = false;

    /** @var self|null */
    private static $instance = null;

    use QPFP_Plugin_Trait;
    use QPFP_Scheduler_Trait;
    use QPFP_Admin_Slots_Trait;
    use QPFP_Editor_Trait;
    use QPFP_Admin_Queue_List_Trait;
    use QPFP_Api_Trait;

    /**
     * Get plugin instance.
     *
     * @return self
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize plugin hooks.
     */
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));

        register_activation_hook(QPFP_PLUGIN_FILE, array($this, 'activate'));
        register_deactivation_hook(QPFP_PLUGIN_FILE, array($this, 'deactivate'));

        add_action('admin_footer', array($this, 'render_queue_dropdown'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_block_editor_assets'));
        add_filter('wp_check_post_lock', array($this, 'prevent_post_lock_corruption'), 10, 2);

        add_action('wp_ajax_qpfp_get_slots', array($this, 'handle_get_slots_ajax'));
        add_action('wp_ajax_qpfp_queue_post', array($this, 'handle_queue_post_ajax'));
    }
}
