<?php
/**
 * Publication slots admin screen.
 *
 * @package Queue_Posts_For_Publication
 */

defined('ABSPATH') || exit;

trait QPFP_Admin_Slots_Trait {
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
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; no core API.
        $slots = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}qpfp_publication_slots ORDER BY day_of_week, time_of_day");
        
        // Get WordPress time format
        $time_format = get_option('time_format');
        $days = $this->get_weekday_labels();
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
        check_admin_referer('qpfp_add_slot', 'qpfp_add_slot_nonce');

        if (!isset($_POST['day_of_week']) || !isset($_POST['time_of_day'])) {
            add_settings_error(
                'qpfp_messages',
                'qpfp_missing_data',
                __('Please select both day and time.', 'queue-posts-for-publication'),
                'error'
            );
            return;
        }

        $day_of_week = absint($_POST['day_of_week']);
        $time_of_day = sanitize_text_field(wp_unslash($_POST['time_of_day']));

        if ($day_of_week < 1 || $day_of_week > 7 || !preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time_of_day)) {
            add_settings_error(
                'qpfp_messages',
                'qpfp_invalid_slot',
                __('Invalid day or time format.', 'queue-posts-for-publication'),
                'error'
            );
            return;
        }

        $normalized_time_of_day = $time_of_day . ':00';

        global $wpdb;
        
        // Debug information
        $this->qpfp_log('Attempting to add slot: day=' . $day_of_week . ', time=' . $normalized_time_of_day);
        $this->qpfp_log('Table name: ' . $wpdb->prefix . 'qpfp_publication_slots');

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; no core API.
        $duplicate_slot_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}qpfp_publication_slots WHERE day_of_week = %d AND time_of_day = %s LIMIT 1",
                $day_of_week,
                $normalized_time_of_day
            )
        );

        if ($duplicate_slot_id) {
            add_settings_error(
                'qpfp_messages',
                'qpfp_duplicate_slot',
                __('That publication slot already exists.', 'queue-posts-for-publication'),
                'error'
            );
            return;
        }
        
        $data = array(
            'day_of_week' => $day_of_week,
            'time_of_day' => $normalized_time_of_day
        );
        
        $format = array('%d', '%s');
        
        $this->qpfp_log('Data: ' . print_r($data, true));
        $this->qpfp_log('Format: ' . print_r($format, true));
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom plugin table; no core API.
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
                /* translators: %s: Database error message */
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
        check_admin_referer('qpfp_delete_slot', 'qpfp_delete_slot_nonce');

        if (!isset($_POST['slot_id'])) {
            return;
        }

        $slot_id = absint($_POST['slot_id']);

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom plugin table; no core API.
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
}
