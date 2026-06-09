<?php
/**
 * Plugin Name: Queue Posts for Publication
 * Plugin URI: https://wpwork.shop/
 * Description: A plugin to queue and schedule posts for future publication on the next available slot.
 * Version: 0.32
 * Author: Karol K
 * Author URI: https://wpwork.shop/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: queue-posts-for-publication
 * Domain Path: /languages
 */

defined('ABSPATH') || exit;

define('QPFP_VERSION', '0.31');
define('QPFP_PLUGIN_FILE', __FILE__);
define('QPFP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('QPFP_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once QPFP_PLUGIN_DIR . 'includes/qpfp-plugin-trait.php';
require_once QPFP_PLUGIN_DIR . 'includes/qpfp-scheduler-trait.php';
require_once QPFP_PLUGIN_DIR . 'includes/qpfp-admin-slots-trait.php';
require_once QPFP_PLUGIN_DIR . 'includes/qpfp-editor-trait.php';
require_once QPFP_PLUGIN_DIR . 'includes/qpfp-admin-queue-list-trait.php';
require_once QPFP_PLUGIN_DIR . 'includes/qpfp-api-trait.php';
require_once QPFP_PLUGIN_DIR . 'includes/class-queue-posts-for-publication.php';

/**
 * Initialize the plugin.
 *
 * @return Queue_Posts_For_Publication
 */
function queue_posts_for_publication_init() {
    return Queue_Posts_For_Publication::get_instance();
}

queue_posts_for_publication_init();
