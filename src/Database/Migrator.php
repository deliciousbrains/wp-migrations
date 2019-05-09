<?php

namespace DeliciousBrains\WPMigrations\Database;

use DeliciousBrains\WPMigrations\CLI\Command;
use DeliciousBrains\WPMigrations\Model\Migration;

class Migrator {

	/**
	 * Migrator constructor.
	 */
	public function __construct() {
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	}

	/**
	 * Bootstrap the CLI command
	 */
	public function init() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'dbi migrate', Command::class );
		}
	}

	/**
	 * Set up the table needed for storing the migrations.
	 *
	 * @return bool
	 */
	public function setup() {
		global $wpdb;

		$table = $wpdb->prefix . 'dbrns_migrations';

		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) === $table ) {
			return false;
		}

		$collation = ! $wpdb->has_cap( 'collation' ) ? '' : $wpdb->get_charset_collate();

		// Create migrations table
		$sql = "CREATE TABLE " . $table . " (
			id bigint(20) NOT NULL auto_increment,
			name varchar(255) NOT NULL,
			date_ran datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			PRIMARY KEY  (id)
			) {$collation};";

		dbDelta( $sql );

		return true;
	}

	/**
	 * Get all the migration files
	 *
	 * @param array $exclude   filenames without extension to exclude
	 * @param null  $migration Single migration class name to only perform the migration for
	 * @param bool  $rollback
	 *
	 * @return array
	 */
	protected function get_migrations( $exclude = array(), $migration = null, $rollback = false ) {
		$all_migrations = array();

		$path = apply_filters( 'dbi_wp_migrations_path', __DIR__ . '/app/migrations' );
		$migrations     = glob( trailingslashit( $path ) . '*.php' );
		if ( empty( $migrations ) ) {
			return $all_migrations;
		}

		foreach ( $migrations as $filename ) {
			$name = basename( $filename, '.php' );
			if ( ! $rollback && in_array( $name, $exclude ) ) {
				// The migration can't have been run before
				continue;
			}

			if ( $rollback && ! in_array( $name, $exclude ) ) {
				// As we are rolling back, it must have been run before
				continue;
			}

			if ( $migration && $this->get_class_name( $name ) !== $migration ) {
				continue;
			}

			$all_migrations[ $filename ] = $name;
		}

		return $all_migrations;
	}

	/**
	 * Get all the migrations to be run
	 *
	 * @param null $migration
	 *
	 * @param bool $rollback
	 *
	 * @return array
	 */
	protected function get_migrations_to_run( $migration = null, $rollback = false ) {
		$ran_migrations = Migration::all();
		$ran_migrations = $ran_migrations->pluck( 'name' )->all();

		$migrations = $this->get_migrations( $ran_migrations, $migration, $rollback );

		return $migrations;
	}

	/**
	 * Run the migrations
	 *
	 * @param null|string $migration
	 * @param bool        $rollback
	 *
	 * @return int
	 */
	public function run( $migration = null, $rollback = false ) {
		$count      = 0;
		$migrations = $this->get_migrations_to_run( $migration, $rollback );
		if ( empty( $migrations ) ) {
			return $count;
		}

		foreach ( $migrations as $file => $name ) {
			require_once $file;

			$class     = __NAMESPACE__ . '\\' . $this->get_class_name( $name );
			$migration = new $class;
			$method    = $rollback ? 'rollback' : 'run';
			if ( ! method_exists( $migration, $method ) ) {
				continue;
			}

			$migration->{$method}();
			$count ++;

			if ( $rollback ) {
				Migration::firstOrFail()->where( 'name', $name )->delete();
				continue;
			}

			$migration_record       = new Migration();
			$migration_record->name = $name;
			$migration_record->save();
		}

		return $count;
	}

	protected function get_class_name( $name ) {
		return $this->camel_case( substr( $name, 11 ) );
	}

	protected function camel_case( $string ) {
		$string = ucwords( str_replace( array( '-', '_' ), ' ', $string ) );

		return str_replace( ' ', '', $string );
	}
}