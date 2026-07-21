<?php
/**
 * Core plugin lifecycle, database, admin menu, and debug logging.
 *
 * @package QPFP_Pheasantly
 */

defined('ABSPATH') || exit;

trait QPFP_Plugin_Trait {
    private function qpfp_log($message) {
        if (self::DEBUG_LOGGING) {
            error_log($message);
        }
    }

    /**
     * Plugin activation.
     */
    public function activate() {
        $this->create_tables();
    }

    /**
     * Plugin deactivation.
     */
    public function deactivate() {
        // Nothing to do on deactivation
    }

    /**
     * Create database tables.
     */
    private function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Publication slots table
        $sql = "CREATE TABLE {$wpdb->prefix}qpfp_publication_slots (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            day_of_week tinyint(1) NOT NULL,
            time_of_day time NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $result = dbDelta($sql);

        if (empty($result)) {
            $this->qpfp_log('Database table creation failed: No SQL statements were executed');
        }
    }

    /**
     * Prevent post lock corruption.
     */
    public function prevent_post_lock_corruption($lock, $post_id) {
        if (is_array($lock)) {
            delete_transient('post_lock_' . $post_id);
            return false;
        }
        return $lock;
    }

    /**
     * Render an admin page heading with the plugin logo.
     *
     * @param string $title Page title.
     */
    protected function render_admin_page_heading($title) {
        ?>
        <h1 class="qpfp-page-title">
            <span class="qpfp-page-title-text"><?php echo esc_html($title); ?></span>
            <img
                src="<?php echo esc_url(QPFP_PLUGIN_URL . 'images/pheasants-queue.png'); ?>"
                alt="Pheasantly Queued Publication"
                class="qpfp-page-title-logo"
                height="40"
            />
        </h1>
        <?php
    }

    public function add_admin_menu() {
        add_menu_page(
            __('Publication Slots', 'pheasantly-queued-publication'),
            __('Queue Posts', 'pheasantly-queued-publication'),
            'manage_options',
            'queue-posts-slots',
            array($this, 'render_slots_page'),
            'dashicons-clock',
            30
        );

        add_submenu_page(
            'queue-posts-slots',
            __('Publication Slots', 'pheasantly-queued-publication'),
            __('Publication Slots', 'pheasantly-queued-publication'),
            'manage_options',
            'queue-posts-slots',
            array($this, 'render_slots_page')
        );

        add_submenu_page(
            'queue-posts-slots',
            __('Queued Posts', 'pheasantly-queued-publication'),
            __('Queued Posts', 'pheasantly-queued-publication'),
            'manage_options',
            'queue-posts-list',
            array($this, 'render_queue_list_page')
        );
    }
}
