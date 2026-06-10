<?php
/**
 * Slot availability, queue resolution, and scheduling.
 *
 * @package Queue_Posts_For_Publication
 */

defined('ABSPATH') || exit;

trait QPFP_Scheduler_Trait {
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
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; no core API.
        $slots = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}qpfp_publication_slots ORDER BY day_of_week, time_of_day");
        
        if (empty($slots)) {
            return array();
        }

        // Get current time in site's timezone
        $current_time = current_time('timestamp');

        $taken_slots = $this->get_taken_slot_datetimes();

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
     * Check whether any recurring publication slots are configured.
     *
     * @return bool Whether the site has at least one slot definition.
     */
    private function has_publication_slots() {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; no core API.
        return (bool) $wpdb->get_var("SELECT 1 FROM {$wpdb->prefix}qpfp_publication_slots LIMIT 1");
    }

    /**
     * Select an available slot by concrete occurrence timestamp, falling back to recurring slot ID.
     *
     * @param array $available_slots Available slot occurrences.
     * @param mixed $slot_timestamp Selected occurrence timestamp.
     * @param mixed $slot_id Selected recurring slot ID.
     * @return array|null Matching available slot occurrence.
     */
    private function find_selected_available_slot($available_slots, $slot_timestamp = null, $slot_id = null) {
        if ($slot_timestamp !== null && $slot_timestamp !== '') {
            $selected_timestamp = intval($slot_timestamp);

            foreach ($available_slots as $slot) {
                if (intval($slot['timestamp']) === $selected_timestamp) {
                    return $slot;
                }
            }

            return null;
        }

        if ($slot_id !== null && $slot_id !== '') {
            foreach ($available_slots as $slot) {
                if ($slot['id'] == $slot_id) {
                    return $slot;
                }
            }

            return null;
        }

        return !empty($available_slots) ? $available_slots[0] : null;
    }

    /**
     * Localized weekday labels keyed by ISO day number (1 = Monday … 7 = Sunday).
     *
     * @return array<int, string>
     */
    private function get_weekday_labels() {
        return array(
            1 => __('Monday', 'queue-posts-for-publication'),
            2 => __('Tuesday', 'queue-posts-for-publication'),
            3 => __('Wednesday', 'queue-posts-for-publication'),
            4 => __('Thursday', 'queue-posts-for-publication'),
            5 => __('Friday', 'queue-posts-for-publication'),
            6 => __('Saturday', 'queue-posts-for-publication'),
            7 => __('Sunday', 'queue-posts-for-publication'),
        );
    }

    /**
     * Helper function to get day name from number.
     *
     * @param int $day_number ISO day number (1–7).
     * @return string
     */
    private function get_day_name($day_number) {
        $days = $this->get_weekday_labels();
        return $days[$day_number] ?? '';
    }

    /**
     * Map of taken local datetimes (Y-m-d H:i:s) to future post IDs.
     *
     * @return array<string, int>
     */
    private function get_taken_slot_datetimes() {
        $scheduled_posts = get_posts(array(
            'post_status' => 'future',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'ASC',
            'no_found_rows' => true,
        ));

        $taken_slots = array();
        foreach ($scheduled_posts as $post) {
            $taken_slots[$post->post_date] = (int) $post->ID;
        }

        return $taken_slots;
    }

    /**
     * Format available slot occurrences for editor UIs (REST, AJAX, classic dropdown).
     *
     * @param array $available_slots Raw slots from get_available_slots().
     * @return array<int, array<string, mixed>>
     */
    private function format_available_slots_for_ui(array $available_slots) {
        $date_format = get_option('date_format');
        $time_format = get_option('time_format');
        $days = $this->get_weekday_labels();

        return array_map(function ($slot_info) use ($date_format, $time_format, $days) {
            $day_of_week = (int) date('N', $slot_info['timestamp']);
            $day_name = $days[$day_of_week];

            return array(
                'id' => $slot_info['id'],
                'timestamp' => $slot_info['timestamp'],
                'label' => date_i18n($date_format . ' ' . $time_format, $slot_info['timestamp']) . ' (' . $day_name . ')',
            );
        }, $available_slots);
    }

    /**
     * Resolve the slot occurrence to queue, or return a WP_Error.
     *
     * @param mixed $slot_timestamp Selected occurrence timestamp.
     * @param mixed $slot_id Selected recurring slot ID.
     * @return array|WP_Error Selected slot occurrence from get_available_slots().
     */
    private function resolve_queue_slot($slot_timestamp = null, $slot_id = null) {
        $pick_specific_slot = ($slot_timestamp !== null && $slot_timestamp !== '')
            || ($slot_id !== null && $slot_id !== '');
        $available_slots = $this->get_available_slots($pick_specific_slot ? 10 : 1);

        if (empty($available_slots)) {
            return new WP_Error(
                'no_slots_defined',
                __('No publication slots configured.', 'queue-posts-for-publication')
            );
        }

        $selected_slot = $this->find_selected_available_slot($available_slots, $slot_timestamp, $slot_id);
        if (!$selected_slot) {
            return new WP_Error(
                'slot_not_available',
                __('Selected slot not available.', 'queue-posts-for-publication')
            );
        }

        return $selected_slot;
    }

    /**
     * Schedule a post on a resolved available slot occurrence.
     *
     * @param int   $post_id Post ID.
     * @param array $selected_slot Slot occurrence from get_available_slots().
     * @return true|WP_Error
     */
    private function schedule_post_on_available_slot($post_id, array $selected_slot) {
        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error('invalid_post', __('Invalid post ID.', 'queue-posts-for-publication'));
        }

        if (!current_user_can('edit_post', $post_id)) {
            return new WP_Error('rest_forbidden', __('You cannot edit this post.', 'queue-posts-for-publication'));
        }

        $local_datetime = date('Y-m-d H:i:s', $selected_slot['timestamp']);
        $gmt_datetime = get_gmt_from_date($local_datetime);

        $update_result = wp_insert_post(
            array(
                'ID' => $post_id,
                'post_status' => 'future',
                'post_date' => $local_datetime,
                'post_date_gmt' => $gmt_datetime,
                'post_title' => $post->post_title,
                'post_content' => $post->post_content,
                'post_excerpt' => $post->post_excerpt,
            ),
            true
        );

        if (is_wp_error($update_result)) {
            $this->qpfp_log('Failed to schedule post: ' . $update_result->get_error_message());
            return $update_result;
        }

        return true;
    }

    /**
     * Human-readable scheduled time for API responses.
     *
     * @param int $timestamp Slot occurrence timestamp (site timezone).
     * @return string
     */
    private function get_scheduled_time_label($timestamp) {
        return date_i18n(
            get_option('date_format') . ' ' . get_option('time_format'),
            $timestamp
        );
    }

    /**
     * HTTP status for queue resolution errors.
     *
     * @param WP_Error $error Queue resolution error.
     * @return int
     */
    private function get_queue_error_status(WP_Error $error) {
        if (in_array($error->get_error_code(), array('no_slots_defined', 'slot_not_available'), true)) {
            return 409;
        }

        return 400;
    }
}
