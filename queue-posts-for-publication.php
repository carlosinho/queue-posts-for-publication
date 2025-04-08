<?php
/**
 * Plugin Name: Queue Posts for Publication
 * Plugin URI: https://wpwork.shop/
 * Description: A plugin to queue and schedule posts for future publication on the next available slot.
 * Version: 1.0.0
 * Author: Karol K
 * Author URI: https://wpwork.shop/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: queue-posts-for-publication
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('QPFP_VERSION', '1.0.0');
define('QPFP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('QPFP_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main plugin class
 */
class Queue_Posts_For_Publication {
    private const DEBUG_LOGGING = false; // Enable debug logging?

    private static $instance = null; // Plugin instance

    /**
     * Helper method for debug logging.
     *
     * @param string $message The message to log
     * @return void
     */
    private function qpfp_log($message) {
        if (self::DEBUG_LOGGING) {
            error_log($message);
        }
    }

    /**
     * Get plugin instance.
     *
     * @return Queue_Posts_For_Publication
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
        // Core WordPress hooks
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        
        // Activation/Deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Post editor hooks
        add_action('admin_footer', array($this, 'render_queue_dropdown'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Block editor hooks - use enqueue_block_editor_assets hook
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_block_editor_assets'));
        
        // Add filter to prevent post lock corruption
        add_filter('wp_check_post_lock', array($this, 'prevent_post_lock_corruption'), 10, 2);

        // Add AJAX handlers for classic editor
        add_action('wp_ajax_qpfp_get_slots', array($this, 'handle_get_slots_ajax'));
        add_action('wp_ajax_qpfp_queue_post', array($this, 'handle_queue_post_ajax'));
    }

    /**
     * Initialize plugin.
     */
    public function init() {
        // Load text domain
        load_plugin_textdomain('queue-posts-for-publication', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    /**
     * Plugin activation.
     */
    public function activate() {
        $this->create_tables();
        //$this->cleanup_corrupted_locks(); // Used one time when the plugin corrupted a lot of posts by locking them for editing. This happened when in earlier versions we tried to save the publication date directly in the database vs using WordPress functions.
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

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Publication slots table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}qpfp_publication_slots (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            day_of_week tinyint(1) NOT NULL,
            time_of_day time NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        $wpdb->query($sql);

        if ($wpdb->last_error) {
            $this->qpfp_log('Database table creation error: ' . $wpdb->last_error);
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
     * Clean up corrupted post locks.
     */
    private function cleanup_corrupted_locks() {
        global $wpdb;
        
        // First, clean up any corrupted post locks from the options table
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'post_lock_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_post_lock_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_post_lock_%'");
        
        // Get all posts with corrupted locks
        $corrupted_posts = $wpdb->get_results("
            SELECT ID, post_title 
            FROM {$wpdb->posts} 
            WHERE post_status = 'future' 
            AND post_date > NOW()
        ");
        
        if (!empty($corrupted_posts)) {
            $this->qpfp_log('Found ' . count($corrupted_posts) . ' posts with corrupted locks');
            
            foreach ($corrupted_posts as $post) {
                // Delete any corrupted post locks
                delete_transient('post_lock_' . $post->ID);
                
                // Clear post cache
                clean_post_cache($post->ID);
                
                // Force update post status to ensure it's correct
                $wpdb->update(
                    $wpdb->posts,
                    array('post_status' => 'future'),
                    array('ID' => $post->ID),
                    array('%s'),
                    array('%d')
                );
                
                // Clear any corrupted post meta
                $wpdb->delete(
                    $wpdb->postmeta,
                    array('post_id' => $post->ID, 'meta_key' => '_edit_lock'),
                    array('%d', '%s')
                );
                
                $this->qpfp_log('Cleaned up locks for post: ' . $post->post_title . ' (ID: ' . $post->ID . ')');
            }
        }
        
        // Clear all transients related to post locks
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_post_lock_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_post_lock_%'");
        
        // Clear object cache if it exists
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    }

    /**
     * Get available publication slots.
     * 
     * @param int $limit Maximum number of slots to return (0 for unlimited)
     * @return array Array of available slots with their timestamps
     */
    private function get_available_slots($limit = 0) {
        global $wpdb;

        $this->qpfp_log('get_available_slots called with limit: ' . $limit);
        
        // Get all slots
        $slots = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}qpfp_publication_slots ORDER BY day_of_week, time_of_day");
        
        if (empty($slots)) {
            return array();
        }

        // Get current time in site's timezone
        $current_time = current_time('timestamp');

        // Get all future posts with their scheduled dates
        $scheduled_posts = get_posts(array(
            'post_status' => 'future',
            'posts_per_page' => -1,
            'fields' => 'ids'
        ));
        
        // Map of scheduled dates (Y-m-d H:i:s format) in local timezone
        $taken_slots = array();
        foreach ($scheduled_posts as $post_id) {
            $taken_slots[get_post_time('Y-m-d H:i:s', false, $post_id)] = $post_id;
        }

        //$this->qpfp_log('Taken slots: ' . print_r($taken_slots, true));

        // Map weekly slots to next 10 weeks of actual dates
        $possible_dates = array();
        foreach ($slots as $slot) {
            $date = strtotime("next " . $this->get_day_name($slot->day_of_week) . " " . $slot->time_of_day);
            
            // If it's today but not passed yet, use today
            if (date('N', $current_time) == $slot->day_of_week && $slot->time_of_day > date('H:i:s', $current_time)) {
                $today = strtotime("today " . $slot->time_of_day);
                if ($today > $current_time) {
                    $date = $today;
                }
            }

            // Add this slot's next 10 occurrences
            for ($i = 0; $i < 10; $i++) {
                $possible_dates[] = array(
                    'slot' => $slot,
                    'timestamp' => $i === 0 ? $date : strtotime("+{$i} weeks", $date)
                );
            }
        }

        // Sort by date
        usort($possible_dates, function($a, $b) {
            return $a['timestamp'] - $b['timestamp'];
        });

        /*
        $this->qpfp_log('Possible dates: ' . implode("\n", array_map(function($date) {
            return date('Y-m-d H:i:s', $date['timestamp']);
        }, $possible_dates)));
        */

        // Filter out taken slots
        $available_slots = array();
        foreach ($possible_dates as $date) {
            $slot_date = date('Y-m-d H:i:s', $date['timestamp']);
            if (!isset($taken_slots[$slot_date])) {
                $available_slots[] = array(
                    'id' => $date['slot']->id,
                    'slot' => $date['slot'],
                    'timestamp' => $date['timestamp']
                );
            }
            
            // Break if we've reached the limit
            if ($limit > 0 && count($available_slots) >= $limit) {
                break;
            }
        }

        $this->qpfp_log('Available slots: ' . "\n- " . implode("\n- ", array_map(function($date) {
            return date('Y-m-d H:i:s', $date['timestamp']);
        }, $available_slots)));

        return $available_slots;
    }

    /**
     * Helper function to get day name from number.
     */
    private function get_day_name($day_number) {
        $days = array(
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            7 => 'Sunday'
        );
        return $days[$day_number];
    }

    /**
     * Schedule cron jobs.
     */
    private function schedule_cron_jobs() {
        if (!wp_next_scheduled('qpfp_check_publication_slots')) {
            wp_schedule_event(time(), 'hourly', 'qpfp_check_publication_slots');
        }
    }

    /**
     * Unschedule cron jobs.
     */
    private function unschedule_cron_jobs() {
        wp_clear_scheduled_hook('qpfp_check_publication_slots');
    }

    /**
     * Register plugin settings.
     */
    public function register_settings() {
        register_setting('qpfp_options', 'qpfp_publication_slots');
        register_setting('qpfp_options', 'qpfp_timezone');
        
        // Add settings section
        add_settings_section(
            'qpfp_slots_section',
            __('Publication Slots', 'queue-posts-for-publication'),
            array($this, 'render_slots_section'),
            'queue-posts-slots'
        );
    }

    /**
     * Render slots section description.
     */
    public function render_slots_section() {
        echo '<p>' . esc_html__('Configure your publication slots. Each slot represents a specific day and time when posts can be published.', 'queue-posts-for-publication') . '</p>';
    }

    /**
     * Render slots page.
     */
    public function render_slots_page() {
        // Handle form submission
        if (isset($_POST['qpfp_add_slot']) && check_admin_referer('qpfp_add_slot', 'qpfp_add_slot_nonce')) {
            $this->handle_add_slot();
        }

        if (isset($_POST['qpfp_delete_slot']) && check_admin_referer('qpfp_delete_slot', 'qpfp_delete_slot_nonce')) {
            $this->handle_delete_slot();
        }

        // Get existing slots
        global $wpdb;
        $slots = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}qpfp_publication_slots ORDER BY day_of_week, time_of_day");
        
        // Get WordPress time format
        $time_format = get_option('time_format');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Publication Slots', 'queue-posts-for-publication'); ?></h1>
            
            <?php settings_errors('qpfp_messages'); ?>
            
            <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=queue-posts-slots')); ?>">
                <?php wp_nonce_field('qpfp_add_slot', 'qpfp_add_slot_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php echo esc_html__('Add New Slot', 'queue-posts-for-publication'); ?></th>
                        <td>
                            <select name="day_of_week" required>
                                <option value=""><?php echo esc_html__('Select Day', 'queue-posts-for-publication'); ?></option>
                                <?php
                                $days = array(
                                    1 => __('Monday', 'queue-posts-for-publication'),
                                    2 => __('Tuesday', 'queue-posts-for-publication'),
                                    3 => __('Wednesday', 'queue-posts-for-publication'),
                                    4 => __('Thursday', 'queue-posts-for-publication'),
                                    5 => __('Friday', 'queue-posts-for-publication'),
                                    6 => __('Saturday', 'queue-posts-for-publication'),
                                    7 => __('Sunday', 'queue-posts-for-publication')
                                );
                                foreach ($days as $value => $label) {
                                    echo '<option value="' . esc_attr($value) . '">' . esc_html($label) . '</option>';
                                }
                                ?>
                            </select>
                            <input type="time" name="time_of_day" required>
                            <input type="submit" name="qpfp_add_slot" class="button button-primary" value="<?php echo esc_attr__('Add Slot', 'queue-posts-for-publication'); ?>">
                        </td>
                    </tr>
                </table>
            </form>

            <h2><?php echo esc_html__('Current Slots', 'queue-posts-for-publication'); ?></h2>
            <?php if (empty($slots)) : ?>
                <p><?php echo esc_html__('No publication slots configured yet.', 'queue-posts-for-publication'); ?></p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('Day', 'queue-posts-for-publication'); ?></th>
                            <th><?php echo esc_html__('Time', 'queue-posts-for-publication'); ?></th>
                            <th><?php echo esc_html__('Actions', 'queue-posts-for-publication'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($slots as $slot) : 
                            $day_name = $days[$slot->day_of_week];
                            $timestamp = strtotime("next $day_name " . $slot->time_of_day);
                            if ($timestamp < time()) {
                                $timestamp = strtotime("+1 week", $timestamp);
                            }
                        ?>
                            <tr>
                                <td><?php echo esc_html($day_name); ?></td>
                                <td><?php echo esc_html(date_i18n($time_format, $timestamp)); ?></td>
                                <td>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=queue-posts-slots')); ?>" style="display:inline;">
                                        <?php wp_nonce_field('qpfp_delete_slot', 'qpfp_delete_slot_nonce'); ?>
                                        <input type="hidden" name="slot_id" value="<?php echo esc_attr($slot->id); ?>">
                                        <input type="submit" name="qpfp_delete_slot" class="button button-small" value="<?php echo esc_attr__('Delete', 'queue-posts-for-publication'); ?>" onclick="return confirm('<?php echo esc_js(__('Are you sure you want to delete this slot?', 'queue-posts-for-publication')); ?>');">
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Handle adding a new slot.
     */
    private function handle_add_slot() {
        if (!isset($_POST['day_of_week']) || !isset($_POST['time_of_day'])) {
            add_settings_error(
                'qpfp_messages',
                'qpfp_missing_data',
                __('Please select both day and time.', 'queue-posts-for-publication'),
                'error'
            );
            return;
        }

        $day_of_week = intval($_POST['day_of_week']);
        $time_of_day = sanitize_text_field($_POST['time_of_day']);

        if ($day_of_week < 1 || $day_of_week > 7 || !preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time_of_day)) {
            add_settings_error(
                'qpfp_messages',
                'qpfp_invalid_slot',
                __('Invalid day or time format.', 'queue-posts-for-publication'),
                'error'
            );
            return;
        }

        global $wpdb;
        
        // Debug information
        $this->qpfp_log('Attempting to add slot: day=' . $day_of_week . ', time=' . $time_of_day);
        $this->qpfp_log('Table name: ' . $wpdb->prefix . 'qpfp_publication_slots');
        
        $data = array(
            'day_of_week' => $day_of_week,
            'time_of_day' => $time_of_day
        );
        
        $format = array('%d', '%s');
        
        $this->qpfp_log('Data: ' . print_r($data, true));
        $this->qpfp_log('Format: ' . print_r($format, true));
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'qpfp_publication_slots',
            $data,
            $format
        );

        if ($result === false) {
            $this->qpfp_log('Database error: ' . $wpdb->last_error);
            add_settings_error(
                'qpfp_messages',
                'qpfp_db_error',
                sprintf(__('Failed to add slot. Error: %s', 'queue-posts-for-publication'), $wpdb->last_error),
                'error'
            );
        } else {
            add_settings_error(
                'qpfp_messages',
                'qpfp_success',
                __('Slot added successfully.', 'queue-posts-for-publication'),
                'success'
            );
        }
    }

    /**
     * Handle deleting a slot.
     */
    private function handle_delete_slot() {
        if (!isset($_POST['slot_id'])) {
            return;
        }

        $slot_id = intval($_POST['slot_id']);

        global $wpdb;
        $result = $wpdb->delete(
            $wpdb->prefix . 'qpfp_publication_slots',
            array('id' => $slot_id),
            array('%d')
        );

        if ($result === false) {
            add_settings_error(
                'qpfp_messages',
                'qpfp_db_error',
                __('Failed to delete slot.', 'queue-posts-for-publication'),
                'error'
            );
        } else {
            add_settings_error(
                'qpfp_messages',
                'qpfp_success',
                __('Slot deleted successfully.', 'queue-posts-for-publication'),
                'success'
            );
        }
    }

    /**
     * Add admin menu items.
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Publication Slots', 'queue-posts-for-publication'),
            __('Queue Posts', 'queue-posts-for-publication'),
            'manage_options',
            'queue-posts-slots',
            array($this, 'render_slots_page'),
            'dashicons-clock',
            30
        );

        add_submenu_page(
            'queue-posts-slots',
            __('Publication Slots', 'queue-posts-for-publication'),
            __('Publication Slots', 'queue-posts-for-publication'),
            'manage_options',
            'queue-posts-slots',
            array($this, 'render_slots_page')
        );

        add_submenu_page(
            'queue-posts-slots',
            __('Queued Posts', 'queue-posts-for-publication'),
            __('Queued Posts', 'queue-posts-for-publication'),
            'manage_options',
            'queue-posts-list',
            array($this, 'render_queue_list_page')
        );
    }

    /**
     * Render queue dropdown in footer.
     */
    public function render_queue_dropdown() {
        global $post;
        if (!$post || $post->post_status === 'publish' || $post->post_status === 'future') {
            return;
        }

        // Get available slots
        $available_slots = $this->get_available_slots(10);
        
        if (empty($available_slots)) {
            return;
        }

        $date_format = get_option('date_format');
        $time_format = get_option('time_format');
        $days = array(
            1 => __('Monday', 'queue-posts-for-publication'),
            2 => __('Tuesday', 'queue-posts-for-publication'),
            3 => __('Wednesday', 'queue-posts-for-publication'),
            4 => __('Thursday', 'queue-posts-for-publication'),
            5 => __('Friday', 'queue-posts-for-publication'),
            6 => __('Saturday', 'queue-posts-for-publication'),
            7 => __('Sunday', 'queue-posts-for-publication')
        );
        ?>
        <div id="qpfp-queue-dropdown" style="display:none;">
            <div class="qpfp-dropdown-content">
                <h4><?php echo esc_html__('Select Publication Slot', 'queue-posts-for-publication'); ?></h4>
                <select id="qpfp-slot-select">
                    <option value=""><?php echo esc_html__('Choose a slot...', 'queue-posts-for-publication'); ?></option>
                    <?php foreach ($available_slots as $slot_info) : 
                        $day_of_week = date('N', $slot_info['timestamp']);
                        $day_name = $days[$day_of_week];
                    ?>
                        <option value="<?php echo esc_attr($slot_info['id']); ?>">
                            <?php echo esc_html(date_i18n($date_format . ' ' . $time_format, $slot_info['timestamp']) . ' (' . $day_name . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="qpfp-dropdown-actions">
                    <button type="button" class="button button-primary" id="qpfp-confirm-queue">
                        <?php echo esc_html__('Queue', 'queue-posts-for-publication'); ?>
                    </button>
                    <button type="button" class="button" id="qpfp-cancel-queue">
                        <?php echo esc_html__('Cancel', 'queue-posts-for-publication'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Enqueue block editor assets.
     */
    public function enqueue_block_editor_assets() {
        $screen = get_current_screen();
        
        // Only load on post edit screens
        if (!$screen || !in_array($screen->base, array('post', 'post-new'))) {
            return;
        }

        // Register and enqueue the script
        wp_register_script(
            'qpfp-block-editor',
            QPFP_PLUGIN_URL . 'block-editor.js',
            array(
                'wp-plugins',
                'wp-edit-post',
                'wp-element',
                'wp-components',
                'wp-data',
                'wp-i18n',
                'wp-api-fetch',
                'wp-compose',
                'wp-hooks',
                'wp-block-editor',
                'wp-blocks',
                'wp-server-side-render'
            ),
            QPFP_VERSION,
            false
        );

        // Set translations
        wp_set_script_translations('qpfp-block-editor', 'queue-posts-for-publication');

        // Add localized data
        wp_localize_script('qpfp-block-editor', 'qpfpBlockEditor', array(
            'i18n' => array(
                'queueButton' => __('Queue for publication', 'queue-posts-for-publication'),
                'selectSlot' => __('Select Publication Slot', 'queue-posts-for-publication'),
                'chooseSlot' => __('Choose a slot...', 'queue-posts-for-publication'),
                'queue' => __('Queue', 'queue-posts-for-publication'),
                'cancel' => __('Cancel', 'queue-posts-for-publication'),
                'confirmQueue' => __('Are you sure you want to queue this post for publication?', 'queue-posts-for-publication'),
                'queueSuccess' => __('Post scheduled for %s', 'queue-posts-for-publication'),
                'queueError' => __('Failed to queue post.', 'queue-posts-for-publication'),
                'slotConflict' => __('This slot is already taken by "%s". Do you want to reschedule that post and use this slot?', 'queue-posts-for-publication'),
                'noSlots' => __('No publication slots configured.', 'queue-posts-for-publication'),
                'queueForNext' => __('Queue for next slot', 'queue-posts-for-publication'),
                'pickSlot' => __('Pick a slot', 'queue-posts-for-publication')
            ),
            'restNonce' => wp_create_nonce('wp_rest'),
            'restUrl' => esc_url_raw(rest_url())
        ));

        // Finally enqueue the script
        wp_enqueue_script('qpfp-block-editor');
    }

    /**
     * Enqueue admin assets.
     */
    public function enqueue_admin_assets($hook) {
        // Load admin.js only on post edit screens
        if (in_array($hook, array('post.php', 'post-new.php'))) {
            wp_enqueue_style(
                'qpfp-admin',
                QPFP_PLUGIN_URL . 'admin.css',
                array(),
                QPFP_VERSION
            );

            wp_enqueue_script(
                'qpfp-admin',
                QPFP_PLUGIN_URL . 'admin.js',
                array('jquery'),
                QPFP_VERSION,
                true
            );

            wp_localize_script('qpfp-admin', 'qpfpAdmin', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('qpfp-queue-nonce'),
                'i18n' => array(
                    'confirmQueue' => __('Are you sure you want to queue this post for publication?', 'queue-posts-for-publication'),
                    'queueSuccess' => __('Post queued successfully.', 'queue-posts-for-publication'),
                    'queueError' => __('Failed to queue post.', 'queue-posts-for-publication')
                )
            ));
        }

        // Load calendar-view.js only on the queue list page
        if ($hook === 'queue-posts_page_queue-posts-list') {
            wp_enqueue_style(
                'qpfp-admin',
                QPFP_PLUGIN_URL . 'admin.css',
                array(),
                QPFP_VERSION
            );

            wp_enqueue_script(
                'qpfp-calendar-view',
                QPFP_PLUGIN_URL . 'calendar-view.js',
                array('jquery'),
                QPFP_VERSION,
                true
            );

            wp_localize_script('qpfp-calendar-view', 'qpfpAdmin', array(
                'i18n' => array(
                    'showListView' => __('Show List View', 'queue-posts-for-publication'),
                    'showCalendarView' => __('Show Calendar View', 'queue-posts-for-publication')
                )
            ));
        }

        // Load only admin.css on slots page since no JS is needed
        if ($hook === 'queue-posts_page_queue-posts-slots') {
            wp_enqueue_style(
                'qpfp-admin',
                QPFP_PLUGIN_URL . 'admin.css',
                array(),
                QPFP_VERSION
            );
        }
    }

    /**
     * Render settings page.
     */
    public function render_settings_page() {
        // Settings page will be implemented later
    }

    /**
     * Render queue list page.
     */
    public function render_queue_list_page() {
        // Get all future posts
        $posts = get_posts(array(
            'post_status' => 'future',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'ASC'
        ));

        if (empty($posts)) {
            echo '<div class="wrap">';
            echo '<h1>' . esc_html__('Scheduled Posts', 'queue-posts-for-publication') . '</h1>';
            echo '<p>' . esc_html__('No posts are currently scheduled for future publication.', 'queue-posts-for-publication') . '</p>';
            echo '</div>';
            return;
        }

        // Group posts by date
        $posts_by_date = array();
        foreach ($posts as $post) {
            $date_key = date('Y-m-d', strtotime($post->post_date));
            if (!isset($posts_by_date[$date_key])) {
                $posts_by_date[$date_key] = array();
            }
            $posts_by_date[$date_key][] = $post;
        }

        // Get WordPress week start setting
        $week_start = get_option('start_of_week', 1); // 1 = Monday, 0 = Sunday

        // Get first and last scheduled post dates
        $first_post_date = strtotime($posts[0]->post_date);
        $last_post_date = strtotime($posts[count($posts) - 1]->post_date);
        $start_month = date('n', $first_post_date);
        $start_year = date('Y', $first_post_date);
        $end_month = date('n', $last_post_date);
        $end_year = date('Y', $last_post_date);

        // Get current date for highlighting
        $current_date = current_time('Y-m-d');

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Scheduled Posts', 'queue-posts-for-publication') . '</h1>';
        
        // Add view toggle button
        echo '<div class="qpfp-view-toggle">';
        echo '<button type="button" class="button" id="qpfp-toggle-view">' . esc_html__('Toggle View', 'queue-posts-for-publication') . '</button>';
        echo '</div>';

        // Calendar View
        echo '<div class="qpfp-calendar">';

        // Display calendar for each month from first post to last post
        for ($year = $start_year; $year <= $end_year; $year++) {
            for ($month = ($year == $start_year ? $start_month : 1); $month <= ($year == $end_year ? $end_month : 12); $month++) {
                // Get first day of current month
                $first_day = mktime(0, 0, 0, $month, 1, $year);
                $first_day_of_week = date('w', $first_day);
                
                // Adjust first day of week based on WordPress setting
                $first_day_of_week = ($first_day_of_week - $week_start + 7) % 7;

                // Get last day of current month
                $last_day = date('t', $first_day);

                // Get month name
                $month_name = date_i18n('F Y', $first_day);

                // Get days of week based on WordPress setting
                $days_of_week = array();
                for ($i = 0; $i < 7; $i++) {
                    $day_index = ($i + $week_start) % 7;
                    $days_of_week[] = date_i18n('D', strtotime("Sunday +$day_index days"));
                }

                echo '<div class="qpfp-calendar-month">';
                echo '<h3>' . esc_html($month_name) . '</h3>';
                echo '<table>';
                echo '<tr>';
                foreach ($days_of_week as $day) {
                    echo '<th>' . esc_html($day) . '</th>';
                }
                echo '</tr>';

                // Add empty cells for days before the first day of the month
                echo '<tr>';
                for ($i = 0; $i < $first_day_of_week; $i++) {
                    echo '<td class="qpfp-calendar-day empty"></td>';
                }

                // Add days of the month
                for ($day = 1; $day <= $last_day; $day++) {
                    $date_key = date('Y-m-d', mktime(0, 0, 0, $month, $day, $year));
                    $has_posts = isset($posts_by_date[$date_key]);
                    $is_current_day = ($date_key === $current_date);
                    
                    $day_classes = array('qpfp-calendar-day');
                    if (!$has_posts) $day_classes[] = 'empty';
                    if ($has_posts) $day_classes[] = 'has-posts';
                    $emoji_html = '';
                    if ($is_current_day) {
                        $day_classes[] = 'current-day';
                        if (!$has_posts) {
                            $today_emojis = array('⏰', '📌', '☀️', '🫵');
                            $random_emoji = $today_emojis[array_rand($today_emojis)];
                            $emoji_html = '<div class="today-emoji">' . esc_html($random_emoji) . '</div>';
                        }
                    } 
                    
                    echo '<td class="' . esc_attr(implode(' ', $day_classes)) . '">';
                    echo '<div class="day-number">' . esc_html($day) . '</div>';
                    echo $emoji_html;
                    
                    if ($has_posts) {
                        echo '<div class="scheduled-posts">';
                        foreach ($posts_by_date[$date_key] as $post) {
                            echo '<div class="scheduled-post">';
                            echo '<a href="' . get_edit_post_link($post->ID) . '">' . esc_html($post->post_title) . '</a>';
                            echo '<div class="post-meta">' . esc_html(date_i18n(get_option('time_format'), strtotime($post->post_date))) . '</div>';
                            echo '</div>';
                        }
                        echo '</div>';
                    }
                    echo '</td>';

                    // Start new row if we're at the end of the week
                    if (($day + $first_day_of_week) % 7 == 0 && $day < $last_day) {
                        echo '</tr><tr>';
                    }
                }

                // Add empty cells for the last week if needed
                $remaining_cells = 7 - (($last_day + $first_day_of_week) % 7);
                if ($remaining_cells < 7) {
                    for ($i = 0; $i < $remaining_cells; $i++) {
                        echo '<td class="qpfp-calendar-day empty"></td>';
                    }
                }

                echo '</tr>';
                echo '</table>';
                echo '</div>';
            }
        }

        echo '</div>'; // End calendar view

        // List View
        echo '<div class="qpfp-list-view">';
        $current_month = '';
        
        foreach ($posts_by_date as $date => $day_posts) {
            $month = date_i18n('F Y', strtotime($date));
            
            if ($month !== $current_month) {
                if ($current_month !== '') {
                    echo '</div>'; // Close previous month
                }
                echo '<div class="qpfp-list-month">';
                echo '<h3>' . esc_html($month) . '</h3>';
                $current_month = $month;
            }
            
            echo '<div class="qpfp-list-day">';
            echo '<div class="qpfp-list-date">' . esc_html(date_i18n(get_option('date_format'), strtotime($date))) . '</div>';
            echo '<div class="qpfp-list-posts">';
            
            foreach ($day_posts as $post) {
                echo '<div class="qpfp-list-post">';
                echo '<a href="' . get_edit_post_link($post->ID) . '">' . esc_html($post->post_title) . '</a>';
                echo '<div class="qpfp-list-time">' . esc_html(date_i18n(get_option('time_format'), strtotime($post->post_date))) . '</div>';
                echo '</div>';
            }
            
            echo '</div>'; // End list-posts
            echo '</div>'; // End list-day
        }
        
        if ($current_month !== '') {
            echo '</div>'; // Close last month
        }
        
        echo '</div>'; // End list view
        echo '</div>'; // End wrap
    }

    /**
     * Register REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route('wp/v2/qpfp', '/slots', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_slots_rest'),
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            }
        ));

        register_rest_route('wp/v2/qpfp', '/queue', array(
            'methods' => 'POST',
            'callback' => array($this, 'queue_post_rest'),
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            },
            'args' => array(
                'post_id' => array(
                    'required' => true,
                    'type' => 'integer',
                ),
                'slot_id' => array(
                    'required' => false,
                    'type' => 'string'
                )
            )
        ));
    }

    /**
     * Get slots REST endpoint.
     */
    public function get_slots_rest($request) {
        // Get available slots
        $available_slots = $this->get_available_slots(10);
        
        if (empty($available_slots)) {
            return new WP_REST_Response(array(), 200);
        }

        $date_format = get_option('date_format');
        $time_format = get_option('time_format');
        $days = array(
            1 => __('Monday', 'queue-posts-for-publication'),
            2 => __('Tuesday', 'queue-posts-for-publication'),
            3 => __('Wednesday', 'queue-posts-for-publication'),
            4 => __('Thursday', 'queue-posts-for-publication'),
            5 => __('Friday', 'queue-posts-for-publication'),
            6 => __('Saturday', 'queue-posts-for-publication'),
            7 => __('Sunday', 'queue-posts-for-publication')
        );
        
        $formatted_slots = array_map(function($slot_info) use ($date_format, $time_format, $days) {
            $day_of_week = date('N', $slot_info['timestamp']);
            $day_name = $days[$day_of_week];
            return array(
                'id' => $slot_info['id'],
                'label' => date($date_format . ' ' . $time_format, $slot_info['timestamp']) . ' (' . $day_name . ')'
            );
        }, $available_slots);

        return new WP_REST_Response($formatted_slots, 200);
    }

    /**
     * Queue post REST endpoint.
     */
    public function queue_post_rest($request) {
        $post_id = $request->get_param('post_id');
        $slot_id = $request->get_param('slot_id');
        
        // Get available slots: 
        // if slot_id is provided get multiple slots to find the specific one,
        // otherwise just get the next available slot
        $available_slots = $this->get_available_slots($slot_id ? 10 : 1);
        
        // Get the selected slot
        $selected_slot = null;
        if ($slot_id) {
            foreach ($available_slots as $slot) {
                if ($slot['id'] == $slot_id) {
                    $selected_slot = $slot;
                    break;
                }
            }
        } else {
            $selected_slot = $available_slots[0];
        }
        
        // Get the post
        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error('invalid_post', __('Invalid post ID.', 'queue-posts-for-publication'));
        }
        
        // Get current timestamp in site's timezone
        $current_time = current_time('timestamp');
        
        // Debug log the timestamps
        $this->qpfp_log('Current time: ' . date('Y-m-d H:i:s', $current_time));
        $this->qpfp_log('Slot timestamp: ' . date('Y-m-d H:i:s', $selected_slot['timestamp']));
        
        // Get the local datetime string (timestamp is already in site's timezone)
        $local_datetime = date('Y-m-d H:i:s', $selected_slot['timestamp']);
        $this->qpfp_log('Local datetime: ' . $local_datetime);
        
        // Convert local datetime to GMT datetime
        $gmt_datetime = get_gmt_from_date($local_datetime);
        //$this->qpfp_log('GMT datetime: ' . $gmt_datetime);
        
        // Update the post
        $post_data = array(
            'ID' => $post_id,
            'post_status' => 'future',
            'post_date' => $local_datetime,
            'post_date_gmt' => $gmt_datetime,
            'post_title' => $post->post_title,
            'post_content' => $post->post_content,
            'post_excerpt' => $post->post_excerpt
        );
        
        // Temporarily remove filters that might interfere with post status
        remove_all_filters('wp_insert_post_data');
        remove_all_filters('wp_insert_post');
        
        // Update the post
        $update_result = wp_insert_post($post_data, true);
        
        // Restore filters
        add_filter('wp_insert_post_data', 'wp_filter_post_data');
        add_filter('wp_insert_post', 'wp_insert_post');
        
        if (is_wp_error($update_result)) {
            $this->qpfp_log('Failed to schedule post: ' . $update_result->get_error_message());
            return new WP_Error('schedule_error', $update_result->get_error_message());
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'scheduled_time' => date(get_option('date_format') . ' ' . get_option('time_format'), $selected_slot['timestamp'])
        ), 200);
    }

    /**
     * Handle AJAX request to get available slots. For classic editor.
     */
    public function handle_get_slots_ajax() {
        check_ajax_referer('qpfp-queue-nonce', '_ajax_nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied');
            return;
        }

        $available_slots = $this->get_available_slots(10);
        
        if (empty($available_slots)) {
            wp_send_json_success(array()); // Match REST behavior
            return;
        }

        $date_format = get_option('date_format');
        $time_format = get_option('time_format');
        $days = array(
            1 => __('Monday', 'queue-posts-for-publication'),
            2 => __('Tuesday', 'queue-posts-for-publication'),
            3 => __('Wednesday', 'queue-posts-for-publication'),
            4 => __('Thursday', 'queue-posts-for-publication'),
            5 => __('Friday', 'queue-posts-for-publication'),
            6 => __('Saturday', 'queue-posts-for-publication'),
            7 => __('Sunday', 'queue-posts-for-publication')
        );
        
        $formatted_slots = array_map(function($slot_info) use ($date_format, $time_format, $days) {
            $day_of_week = date('N', $slot_info['timestamp']);
            $day_name = $days[$day_of_week];
            return array(
                'id' => $slot_info['id'],
                'label' => date($date_format . ' ' . $time_format, $slot_info['timestamp']) . ' (' . $day_name . ')'
            );
        }, $available_slots);

        wp_send_json_success($formatted_slots);
    }

    /**
     * Handle AJAX request to queue a post. For classic editor.
     */
    public function handle_queue_post_ajax() {
        check_ajax_referer('qpfp-queue-nonce', '_ajax_nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied');
            return;
        }

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $slot_id = isset($_POST['slot_id']) ? $_POST['slot_id'] : null;

        if (!$post_id) {
            wp_send_json_error('Invalid post ID');
            return;
        }

        // Get available slots
        $available_slots = $this->get_available_slots($slot_id ? 10 : 1);
        
        // Get the selected slot
        $selected_slot = null;
        if ($slot_id) {
            foreach ($available_slots as $slot) {
                if ($slot['id'] == $slot_id) {
                    $selected_slot = $slot;
                    break;
                }
            }
        } else {
            $selected_slot = $available_slots[0];
        }

        if (!$selected_slot) {
            wp_send_json_error('Selected slot not available');
            return;
        }

        // Get the post
        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error(__('Invalid post ID.', 'queue-posts-for-publication'));
            return;
        }

        // Get current timestamp in site's timezone
        $current_time = current_time('timestamp');
        
        // Debug log the timestamps
        $this->qpfp_log('Current time: ' . date('Y-m-d H:i:s', $current_time));
        $this->qpfp_log('Slot timestamp: ' . date('Y-m-d H:i:s', $selected_slot['timestamp']));
        
        // Get the local datetime string (timestamp is already in site's timezone)
        $local_datetime = date('Y-m-d H:i:s', $selected_slot['timestamp']);
        $this->qpfp_log('Local datetime: ' . $local_datetime);
        
        // Convert local datetime to GMT datetime
        $gmt_datetime = get_gmt_from_date($local_datetime);

        // Prepare post data
        $post_data = array(
            'ID' => $post_id,
            'post_status' => 'future',
            'post_date' => $local_datetime,
            'post_date_gmt' => $gmt_datetime,
            'post_title' => $post->post_title,
            'post_content' => $post->post_content,
            'post_excerpt' => $post->post_excerpt
        );
        
        // Temporarily remove filters that might interfere with post status
        remove_all_filters('wp_insert_post_data');
        remove_all_filters('wp_insert_post');
        
        // Update the post
        $update_result = wp_insert_post($post_data, true);
        
        // Restore filters
        add_filter('wp_insert_post_data', 'wp_filter_post_data');
        add_filter('wp_insert_post', 'wp_insert_post');

        if (is_wp_error($update_result)) {
            $this->qpfp_log('Failed to schedule post: ' . $update_result->get_error_message());
            wp_send_json_error($update_result->get_error_message());
            return;
        }

        wp_send_json_success(array(
            'success' => true,
            'scheduled_time' => date(get_option('date_format') . ' ' . get_option('time_format'), $selected_slot['timestamp'])
        ));
    }
}

// Initialize the plugin
function queue_posts_for_publication_init() {
    return Queue_Posts_For_Publication::get_instance();
}

// Start the plugin
queue_posts_for_publication_init();

