<?php

namespace DeliciousBrains\WPMigrations\CLI;

class Scaffold extends \WP_CLI_Command {

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
	 * [--scaffold]
	 * : Scaffold a new migration class using the migration stub
	 *
	 * @param array $args
	 * @param array $assoc_args
	 *
	 * @throws \WP_CLI\ExitException
	 */
	public function __invoke( $args, $assoc_args ) {
        $migrator = \DeliciousBrains\WPMigrations\Database\Migrator::instance();

        $migration = null;
        if ( ! empty( $args[0] ) ) {
            $migration = $args[0];
        }


        if ( ! $migration ) {
            return \WP_CLI::error( 'Migration name must be specified when using --scaffold' );
        }

        $filename = $migrator->scaffold( $migration );

        if ( is_wp_error( $filename ) ) {
            return \WP_ClI::error( $filename->get_error_message() );
        }

        return \WP_CLI::success( "Created {$filename}" );
	}
}
