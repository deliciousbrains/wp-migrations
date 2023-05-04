<?php

namespace DeliciousBrains\WPMigrations\Database;

use DeliciousBrains\WPMigrations\CLI\Migrate;
use DeliciousBrains\WPMigrations\CLI\Scaffold;

class Migrator {

	/**
	 * @var Migrator
	 */
	private static $instance;

	protected $table_name = 'dbrns_migrations';

	/**
	 * @param string $command_name
	 *
	 * @return Migrator Instance
	 */
	public static function instance( $command_name = 'dbi') {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof Migrator ) ) {
			self::$instance = new Migrator();
			self::$instance->init( $command_name );
		}

		return self::$instance;
	}

	/**
	 * @param string $command_name
	 */
	public function init( $command_name ) {
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( $command_name . ' migrate', Migrate::class );
            \WP_CLI::add_command( 'scaffold migration', Scaffold::class );
		}
	}

	/**
	 * Set up the table needed for storing the migrations.
	 *
	 * @return bool
	 */
	public function setup() {
		global $wpdb;

		$table = $wpdb->prefix . $this->table_name;

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
	 * @param array       $exclude   Filenames without extension to exclude
	 * @param string|null $migration Single migration class name to only perform the migration for
	 * @param bool        $rollback
	 *
	 * @return array
	 */
	protected function get_migrations( $exclude = array(), $migration = null, $rollback = false ) {
		$all_migrations = array();

		$path  = $this->get_migrations_path();
		$paths = apply_filters( 'dbi_wp_migrations_paths', array( $path ) );

		$migrations = array();
		foreach ( $paths as $path ) {
			$path_migrations = glob( trailingslashit( $path ) . '*.php' );
			$migrations      = array_merge( $migrations, $path_migrations );
		}

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
	 * Get the default migrations folder path.
	 *
	 * @return string
	 */
	protected function get_migrations_path() {
		$base_path = __FILE__;

		while ( basename( $base_path ) != 'vendor' ) {
			$base_path = dirname( $base_path );
		}

		return apply_filters( 'dbi_wp_migrations_path', dirname( $base_path ) . '/app/migrations' );
	}

	/**
	 * Get all the migrations to be run
	 *
	 * @param string|null $migration
	 * @param bool        $rollback
	 * @return array
	 */
	protected function get_migrations_to_run( $migration = null, $rollback = false ) {
		global $wpdb;
		$table = $wpdb->prefix . $this->table_name;
		$ran_migrations = $wpdb->get_col( "SELECT name FROM $table");

		$migrations = $this->get_migrations( $ran_migrations, $migration, $rollback );

		return $migrations;
	}

	/**
	 * Run the migrations
	 *
	 * @param string|null $migration
	 * @param bool        $rollback
	 *
	 * @return int
	 */
	public function run( $migration = null, $rollback = false ) {
		global $wpdb;
		$table = $wpdb->prefix . $this->table_name;
		$count      = 0;
		$migrations = $this->get_migrations_to_run( $migration, $rollback );
		if ( empty( $migrations ) ) {
			return $count;
		}

		foreach ( $migrations as $file => $name ) {
			require_once $file;

			$class_name    = $this->get_class_name( $name );
			$fq_class_name = $this->get_class_with_namespace( $class_name );
			if ( false === $fq_class_name ) {
				continue;
			}

			$class     = $fq_class_name;
			$migration = new $class;
			$method    = $rollback ? 'rollback' : 'run';
			if ( ! method_exists( $migration, $method ) ) {
				continue;
			}

			$migration->{$method}();
			$count ++;

			if ( $rollback ) {
				$wpdb->delete( $table, array( 'name' => $name ) );
				continue;
			}

			$wpdb->insert( $table, array( 'name' => $name, 'date_ran' => date("Y-m-d H:i:s") ) );
		}

		return $count;
	}

	protected function get_class_with_namespace( $class_name ) {
		$all_classes = get_declared_classes();
		foreach ( $all_classes as $class ) {
			if ( substr( $class, - strlen( $class_name ) ) === $class_name ) {
				return $class;
			}
		}

		return false;
	}

	protected function get_class_name( $name ) {
		return $this->camel_case( substr( $name, 11 ) );
	}

	protected function camel_case( $string ) {
		$string = ucwords( str_replace( array( '-', '_' ), ' ', $string ) );

		return str_replace( ' ', '', $string );
	}

	/**
	 * Scaffold a new migration using the stub from the `stubs` directory.
	 *
	 * @param string $migration_name Camel cased migration name, e.g. myMigration.
	 *
	 * @return string|WP_Error Name of created migration file on success, WP_Error
	 *                         instance on failure.
	 */
	public function scaffold( $migration_name ) {
		$migrations_path = $this->get_migrations_path();

		// Create migrations dir if it doesn't exist already.
		if ( ! is_dir( $migrations_path ) ) {
			if ( ! mkdir( $migrations_path, 0755 ) ) {
				return new \WP_Error(
					'migrations_folder_error',
					"Unable to create migrations folder {$migrations_path}"
				);
			}
		}

		$stub_dir  = dirname( dirname( __DIR__ ) ) . '/stubs';
		$stub_path = apply_filters( 'dbi_migration_stub_path', "{$stub_dir}/migration.stub" );
		$stub      = file_get_contents( $stub_path );

		if ( ! $stub ) {
			return new \WP_Error(
				'stub_file_error',
				"Unable to create migration file: Couldn't read from stub {$stub_path}."
			);
		}

		$date        = date( 'Y_m_d' );
		$filename    = "{$date}_{$migration_name}.php";
		$file_path   = "{$migrations_path}/{$filename}";
		$boilerplate = str_replace( '{{ class }}', $migration_name, $stub );

		if ( ! file_put_contents( $file_path, $boilerplate ) ) {
			return new \WP_Error(
				'file_creation_error',
				"Unable to create migration file {$migration_path}."
			);
		}

		return $filename;
	}

	/**
	 * Protected constructor to prevent creating a new instance of the
	 * class via the `new` operator from outside of this class.
	 */
	protected function __construct() {
	}

	/**
	 * As this class is a singleton it should not be clone-able
	 */
	protected function __clone() {
	}

	/**
	 * As this class is a singleton it should not be able to be unserialized
	 */
	protected function __wakeup() {
	}
}