<?php

namespace DeliciousBrains\WPMigrations\Database;

abstract class AbstractMigration {

	/**
	 * Get database collation.
	 *
	 * @return string
	 */
	protected function get_collation() {
		global $wpdb;

		if ( ! $wpdb->has_cap( 'collation' ) ) {
			return '';
		}

		return $wpdb->get_charset_collate();
	}

	/**
	 * @return mixed
	 */
	abstract function run();
}