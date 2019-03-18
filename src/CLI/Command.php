<?php

namespace DeliciousBrains\WPMigrations\CLI;

class Command extends \WP_CLI_Command {

	/**
	 * Data migration command
	 *
	 * ## OPTIONS
	 *
	 * [<migration>]
	 * : Class name for the migration
	 *
	 * [--rollback]
	 * : If we are reverting a migration
	 *
	 * [--setup]
	 * : Set up the migrations table
	 *
	 * @param $args
	 * @param $assoc_args
	 *
	 * @throws \WP_CLI\ExitException
	 */
	public function migrate( $args, $assoc_args ) {
		$migrator = new \DeliciousBrains\WPMigrations\Database\Migrator();

		if ( isset( $assoc_args['setup'] ) ) {
			if ( ! $migrator->setup() ) {
				return \WP_CLI::warning( 'Migrations already setup' );
			}

			\WP_CLI::success( 'Migrations setup' );

			return;
		}

		$migration = null;
		if ( ! empty( $args[0] ) ) {
			$migration = $args[0];
		}

		$rollback = false;
		if ( $migration && isset( $assoc_args['rollback'] ) ) {
			// Can only rollback specific migration
			$rollback = true;
		}

		$total = $migrator->run( $migration, $rollback );
		if ( 0 == $total ) {
			\WP_CLI::warning( 'There are no migrations to run.' );
		} else {
			$action = $rollback ? 'rolled back' : 'run';
			\WP_CLI::success( $total . ' migrations ' . $action );
		}
	}
}
