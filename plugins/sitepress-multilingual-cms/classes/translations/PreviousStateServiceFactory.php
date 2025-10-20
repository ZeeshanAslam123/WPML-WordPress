<?php

namespace WPML\Translation;

use WPML\Core\Component\TranslationPreviousState\Application\Service\MigrationService;
use WPML\Core\Component\TranslationPreviousState\Application\Service\PreviousStateService;
use WPML\Core\Component\TranslationPreviousState\Domain\Migration\Processor;
use WPML\Infrastructure\WordPress\Component\TranslationPreviousState\Domain\PreviousStateQuery;
use WPML\Infrastructure\WordPress\Component\TranslationPreviousState\Domain\PreviousStateRepository;
use WPML\Infrastructure\WordPress\Component\TranslationPreviousState\Domain\Migration\Query;
use WPML\Infrastructure\WordPress\Component\TranslationPreviousState\Domain\NoCompressionDataCompress as DataCompress;
use WPML\Infrastructure\WordPress\Port\Persistence\DatabaseWrite;
use WPML\Infrastructure\WordPress\Port\Persistence\QueryHandler;
use WPML\Infrastructure\WordPress\Port\Persistence\QueryPrepare;

class PreviousStateServiceFactory {
	/** @var PreviousStateService|null */
	private static $instance = null;

	/** @var MigrationService|null */
	private static $migrationService = null;

	public static function create(): PreviousStateService {
		// Check if the instance is already created
		if ( self::$instance === null ) {
			self::$instance = self::createNewInstance();
		}

		// Return the cached instance
		return self::$instance;
	}

	/**
	 * Set a custom instance of PreviousStateService. The main purpose it to mock it in the tests
	 *
	 * @param PreviousStateService $instance
	 *
	 * @return void
	 */
	public static function setService( PreviousStateService $instance ) {
		self::$instance = $instance;
	}

	/**
	 * Create a new instance of PreviousStateService
	 *
	 * @return PreviousStateService
	 */
	private static function createNewInstance(): PreviousStateService {
		global $wpdb;

		$dataCompress = new DataCompress();

		$query = new PreviousStateQuery(
			new QueryHandler( $wpdb ),
			new QueryPrepare( $wpdb ),
			$dataCompress
		);

		$repository = new PreviousStateRepository( new DatabaseWrite( $wpdb ), $dataCompress );

		return new PreviousStateService( $query, $repository );
	}
}