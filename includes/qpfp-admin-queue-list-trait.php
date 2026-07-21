<?php
/**
 * Queued posts calendar and list admin screen.
 *
 * @package QPFP_Pheasantly
 */

defined('ABSPATH') || exit;

trait QPFP_Admin_Queue_List_Trait {
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
            $this->render_admin_page_heading(__('Queued Posts', 'pheasantly-queued-publication'));
            echo '<p>' . esc_html__('No posts are currently scheduled for future publication.', 'pheasantly-queued-publication') . '</p>';
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
        $this->render_admin_page_heading(__('Queued Posts', 'pheasantly-queued-publication'));
        
        // Add view toggle button
        echo '<div class="qpfp-view-toggle">';
        echo '<button type="button" class="button" id="qpfp-toggle-view">' . esc_html__('Toggle View', 'pheasantly-queued-publication') . '</button>';
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
                    if ($is_current_day) {
                        $day_classes[] = 'current-day';
                    }

                    echo '<td class="' . esc_attr(implode(' ', $day_classes)) . '">';
                    echo '<div class="day-number">' . esc_html($day) . '</div>';
                    if ($is_current_day && !$has_posts) {
                        $today_emojis = array('⏰', '📌', '☀️', '🫵', '🎯', '🌤️', '🌻');
                        $random_emoji = $today_emojis[array_rand($today_emojis)];
                        echo '<div class="today-emoji">' . esc_html($random_emoji) . '</div>';
                    }
                    
                    if ($has_posts) {
                        echo '<div class="scheduled-posts">';
                        foreach ($posts_by_date[$date_key] as $post) {
                            echo '<div class="scheduled-post">';
                            echo '<a href="' . esc_url(get_edit_post_link($post->ID)) . '">' . esc_html($post->post_title) . '</a>';
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
                echo '<a href="' . esc_url(get_edit_post_link($post->ID)) . '">' . esc_html($post->post_title) . '</a>';
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
}
