<?php

namespace DeliciousBrains\WPMigrations\Model;

use WeDevs\ORM\Eloquent\Model;

class Migration extends Model {

	/**
	 * Name for table without prefix
	 *
	 * @var string
	 */
	protected $table = 'dbrns_migrations';

	/**
	 * @var array
	 */
	protected $fillable = [
		'name',
		'date_ran',
	];

	/**
	 * @var string
	 */
	protected $guarded = [ 'id' ];

	/**
	 * @var bool
	 */
	public $timestamps = false;

	public function __construct( array $attributes = array() ) {
		$defaults = [
			'date_ran' => date( 'Y-m-d H:i:s' ),
		];

		$this->setRawAttributes( $defaults, true );

		parent::__construct( $attributes );
	}

	/**
	 * Overide parent method to make sure prefixing is correct.
	 *
	 * @return string
	 */
	public function getTable() {
		if ( isset( $this->table ) ) {
			$prefix = $this->getConnection()->db->prefix;

			return $prefix . $this->table;

		}

		return parent::getTable();
	}
}