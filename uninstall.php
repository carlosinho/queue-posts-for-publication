<?php
/**
 * Uninstall cleanup.
 *
 * @package QPFP_Pheasantly
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

global $wpdb;

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Plugin table removal on uninstall.
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}qpfp_publication_slots");
