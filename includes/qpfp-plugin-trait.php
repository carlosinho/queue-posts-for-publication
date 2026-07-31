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
                alt="Pheasantly"
                class="qpfp-page-title-logo"
                height="40"
            />
        </h1>
        <?php
    }

    /**
     * Get the admin menu icon as an SVG data URI.
     *
     * @return string Menu icon data URI or fallback Dashicon class.
     */
    private function get_admin_menu_icon() {
        $icon_path = QPFP_PLUGIN_DIR . 'images/pheasant-icon.svg';

        if (!is_readable($icon_path)) {
            return 'dashicons-clock';
        }

        $icon_svg = file_get_contents($icon_path);

        if (false === $icon_svg) {
            return 'dashicons-clock';
        }

        return 'data:image/svg+xml;base64,' . base64_encode($icon_svg);
    }

    public function add_admin_menu() {
        add_menu_page(
            __('Publication Slots', 'pheasantly-queued-publication'),
            __('Queue Posts', 'pheasantly-queued-publication'),
            'manage_options',
            'queue-posts-slots',
            array($this, 'render_slots_page'),
            $this->get_admin_menu_icon(),
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
