# Delicious Brains WordPress Migrations

A WordPress library for managing database table schema upgrades and data seeding.

Ever had to create a custom table for some plugin or custom code to use? To keep the site updated with the latest version of that table you need to keep track of what version the table is at. This can get overly complex for lots of tables.

This package is inspired by [Laravel's database migrations](https://laravel.com/docs/5.8/migrations). You create a new migration PHP file, add your schema update code, and optionally include a rollback method to reverse the change. 

Simply run `wp dbi migrate` on the command line using WP CLI and any migrations not already run will be executed.

The great thing about making database schema and data updates with migrations, is that the changes are file-based and therefore can be stored in version control, giving you better control when working across different branches. 

### Requirements

This package is designed to be used on a WordPress site project, not for a plugin or theme. 

It needs to be running PHP 5.3 or higher.

You need to have access to run WP CLI on the server. Typically `wp dbi migrate` will be run as a last stage build step in your deployment process.

### Installation

- `composer require deliciousbrains/wp-migrations`
- Bootstrap the package by adding `\DeliciousBrains\WPMigrations\Database\Migrator::instance();` to an mu-plugin.

### Migrations

By default, the command will look for migration files in `/app/migrations` directory alongside the vendor folder. This can be altered with the filter `dbi_wp_migrations_path`.
Other paths can be added using the `dbi_wp_migrations_paths` filter. 

Migration file names should follow the `yyyy_mm_dd_classname` format, eg. 2020_04_09_AddCustomTable.php

An example migration to create a table would look like:

**2020_04_09_AddCustomTable.php**
```
<?php

use DeliciousBrains\WPMigrations\Database\AbstractMigration;

class AddCustomTable extends AbstractMigration {

    public function run() {
        global $wpdb;

        $sql = "
            CREATE TABLE " . $wpdb->prefix . "my_table (
            id bigint(20) NOT NULL auto_increment,
            some_column varchar(50) NOT NULL,
            PRIMARY KEY (id)
            ) {$this->get_collation()};
        ";

        dbDelta( $sql );
    }
	
    public function rollback() {
        global $wpdb;
        $wpdb->query( 'DROP TABLE ' . $wpdb->prefix . 'my_table');
    }
}
```

We are also using the migrations to deploy development data changes at deployment time. Instead of trying to merge the development database into the production one.

For example, to add a new page:

```
<?php

use DeliciousBrains\WPMigrations\Database\AbstractMigration;

class AddPricingPage extends AbstractMigration {

    public function run() {
        $pricing_page_id = wp_insert_post( array(
            'post_title'  => 'Pricing',
            'post_status' => 'publish',
            'post_type'   => 'page',
        ) );
        update_post_meta( $pricing_page_id, '_wp_page_template', 'page-pricing.php' );
    }
}
```

### Use

You can run specific migrations using the filename as an argument, eg. `wp dbi migrate AddCustomTable`.

To rollback all migrations you can run `wp dbi migrate --rollback`, or just a specific migration `wp dbi migrate AddCustomTable --rollback`.

To quickly scaffold a new migration you can run `wp scaffold migration <name>`. For example, `wp scaffold migration MyMigration` will create a new class named `MyMigration` in the default migration files directory with the correct filename and all required boilerplate code.
