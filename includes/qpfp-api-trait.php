<?php
/**
 * REST and AJAX endpoints for editor queueing.
 *
 * @package Queue_Posts_For_Publication
 */

defined('ABSPATH') || exit;

trait QPFP_Api_Trait {
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
                ),
                'slot_timestamp' => array(
                    'required' => false,
                    'type' => 'integer'
                )
            )
        ));
    }

    /**
     * Get slots REST endpoint.
     */
    public function get_slots_rest($request) {
        $available_slots = $this->get_available_slots(10);

        if (empty($available_slots)) {
            return new WP_REST_Response(array(), 200);
        }

        return new WP_REST_Response($this->format_available_slots_for_ui($available_slots), 200);
    }

    /**
     * Queue post REST endpoint.
     */
    public function queue_post_rest($request) {
        $post_id = (int) $request->get_param('post_id');
        $slot_id = $request->get_param('slot_id');
        $slot_timestamp = $request->get_param('slot_timestamp');

        $selected_slot = $this->resolve_queue_slot($slot_timestamp, $slot_id);
        if (is_wp_error($selected_slot)) {
            return new WP_Error(
                $selected_slot->get_error_code(),
                $selected_slot->get_error_message(),
                array('status' => $this->get_queue_error_status($selected_slot))
            );
        }

        $schedule_result = $this->schedule_post_on_available_slot($post_id, $selected_slot);
        if (is_wp_error($schedule_result)) {
            return new WP_Error(
                'schedule_error',
                $schedule_result->get_error_message(),
                array('status' => 500)
            );
        }

        return new WP_REST_Response(
            array(
                'success' => true,
                'scheduled_time' => $this->get_scheduled_time_label($selected_slot['timestamp']),
            ),
            200
        );
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
            wp_send_json_success(array());
            return;
        }

        wp_send_json_success($this->format_available_slots_for_ui($available_slots));
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

        $slot_id = null;
        if (isset($_POST['slot_id'])) {
            $slot_id = absint($_POST['slot_id']);
            if (!$slot_id) {
                $slot_id = null;
            }
        }

        $slot_timestamp = null;
        if (isset($_POST['slot_timestamp'])) {
            $slot_timestamp = absint($_POST['slot_timestamp']);
            if (!$slot_timestamp) {
                $slot_timestamp = null;
            }
        }

        if (!$post_id) {
            wp_send_json_error('Invalid post ID');
            return;
        }

        $selected_slot = $this->resolve_queue_slot($slot_timestamp, $slot_id);
        if (is_wp_error($selected_slot)) {
            wp_send_json_error($selected_slot->get_error_message());
            return;
        }

        $schedule_result = $this->schedule_post_on_available_slot($post_id, $selected_slot);
        if (is_wp_error($schedule_result)) {
            wp_send_json_error($schedule_result->get_error_message());
            return;
        }

        wp_send_json_success(
            array(
                'success' => true,
                'scheduled_time' => $this->get_scheduled_time_label($selected_slot['timestamp']),
                'redirect_url' => add_query_arg(
                    array(
                        'post' => $post_id,
                        'action' => 'edit',
                        'message' => 9,
                    ),
                    admin_url('post.php')
                ),
            )
        );
    }
}
