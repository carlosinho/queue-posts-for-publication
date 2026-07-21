<?php
/**
 * Editor assets and classic queue dropdown markup.
 *
 * @package QPFP_Pheasantly
 */

defined('ABSPATH') || exit;

trait QPFP_Editor_Trait {
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

        $formatted_slots = $this->format_available_slots_for_ui($available_slots);
        ?>
        <div id="qpfp-queue-dropdown" style="display:none;">
            <div class="qpfp-dropdown-content">
                <h4><?php echo esc_html__('Select Publication Slot', 'pheasantly-queued-publication'); ?></h4>
                <select id="qpfp-slot-select">
                    <option value=""><?php echo esc_html__('Choose a slot...', 'pheasantly-queued-publication'); ?></option>
                    <?php foreach ($formatted_slots as $slot_option) : ?>
                        <option value="<?php echo esc_attr($slot_option['timestamp']); ?>">
                            <?php echo esc_html($slot_option['label']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="qpfp-dropdown-actions">
                    <button type="button" class="button button-primary" id="qpfp-confirm-queue">
                        <?php echo esc_html__('Queue', 'pheasantly-queued-publication'); ?>
                    </button>
                    <button type="button" class="button" id="qpfp-cancel-queue">
                        <?php echo esc_html__('Cancel', 'pheasantly-queued-publication'); ?>
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
            QPFP_PLUGIN_URL . 'js/block-editor.js',
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
        wp_set_script_translations('qpfp-block-editor', 'pheasantly-queued-publication');

        // Add localized data
        $has_publication_slots = $this->has_publication_slots();
        wp_localize_script('qpfp-block-editor', 'qpfpBlockEditor', array(
            'i18n' => array(
                'queueButton' => __('Queue for publication', 'pheasantly-queued-publication'),
                'selectSlot' => __('Select Publication Slot', 'pheasantly-queued-publication'),
                'chooseSlot' => __('Choose a slot...', 'pheasantly-queued-publication'),
                'queue' => __('Queue', 'pheasantly-queued-publication'),
                'cancel' => __('Cancel', 'pheasantly-queued-publication'),
                'queueError' => __('Failed to queue post.', 'pheasantly-queued-publication'),
                'slotConflict' => /* translators: %s: Title of the post currently scheduled in this slot */ __('This slot is already taken by "%s". Do you want to reschedule that post and use this slot?', 'pheasantly-queued-publication'),
                'noSlots' => __('No publication slots configured.', 'pheasantly-queued-publication'),
                'configureSlotsFirst' => __('Define publication slots before queueing posts.', 'pheasantly-queued-publication'),
                'manageSlots' => __('Manage publication slots', 'pheasantly-queued-publication'),
                'queueForNext' => __('Queue for next slot', 'pheasantly-queued-publication'),
                'pickSlot' => __('Pick a slot', 'pheasantly-queued-publication')
            ),
            'hasPublicationSlots' => $has_publication_slots,
            'manageSlotsUrl' => $has_publication_slots || !current_user_can('manage_options') ? '' : admin_url('admin.php?page=queue-posts-slots'),
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
                QPFP_PLUGIN_URL . 'css/admin.css',
                array(),
                QPFP_VERSION
            );

            wp_enqueue_script(
                'qpfp-admin',
                QPFP_PLUGIN_URL . 'js/admin.js',
                array('jquery'),
                QPFP_VERSION,
                true
            );

            wp_localize_script('qpfp-admin', 'qpfpAdmin', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('qpfp-queue-nonce'),
                'hasPublicationSlots' => $this->has_publication_slots(),
                'manageSlotsUrl' => current_user_can('manage_options') ? admin_url('admin.php?page=queue-posts-slots') : '',
                'i18n' => array(
                    'queueSectionLabel' => __('Queue for publication:', 'pheasantly-queued-publication'),
                    'edit' => __('Edit', 'pheasantly-queued-publication'),
                    'editQueueOptions' => __('Edit queue options', 'pheasantly-queued-publication'),
                    'cancel' => __('Cancel', 'pheasantly-queued-publication'),
                    'ok' => __('OK', 'pheasantly-queued-publication'),
                    'queueError' => __('Failed to queue post.', 'pheasantly-queued-publication'),
                    'fetchSlotsError' => __('Failed to fetch slots.', 'pheasantly-queued-publication'),
                    'noSlots' => __('No slots available.', 'pheasantly-queued-publication'),
                    'configureSlotsFirst' => __('Define publication slots before queueing posts.', 'pheasantly-queued-publication'),
                    'manageSlots' => __('Manage publication slots', 'pheasantly-queued-publication'),
                    'chooseSlot' => __('Choose a slot...', 'pheasantly-queued-publication'),
                    'pickSlot' => __('Pick a slot', 'pheasantly-queued-publication'),
                    'queueForNext' => __('Queue for next slot', 'pheasantly-queued-publication'),
                    'showListView' => __('Show List View', 'pheasantly-queued-publication'),
                    'showCalendarView' => __('Show Calendar View', 'pheasantly-queued-publication')
                )
            ));
        }

        $plugin_admin_hooks = array(
            'toplevel_page_queue-posts-slots',
            'queue-posts_page_queue-posts-slots',
            'queue-posts_page_queue-posts-list',
        );

        if (in_array($hook, $plugin_admin_hooks, true)) {
            wp_enqueue_style(
                'qpfp-admin',
                QPFP_PLUGIN_URL . 'css/admin.css',
                array(),
                QPFP_VERSION
            );
        }

        // Load calendar-view.js only on the queue list page
        if ($hook === 'queue-posts_page_queue-posts-list') {
            wp_enqueue_script(
                'qpfp-calendar-view',
                QPFP_PLUGIN_URL . 'js/calendar-view.js',
                array('jquery'),
                QPFP_VERSION,
                true
            );

            wp_localize_script('qpfp-calendar-view', 'qpfpAdmin', array(
                'i18n' => array(
                    'showListView' => __('Show List View', 'pheasantly-queued-publication'),
                    'showCalendarView' => __('Show Calendar View', 'pheasantly-queued-publication')
                )
            ));
        }
    }
}
