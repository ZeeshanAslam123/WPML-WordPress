<?php

namespace WPML\TM\Troubleshooting\Endpoints\OptimizeDbTables;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\Core\Component\Troubleshooting\TranslationTablesOptimization\Application\Service\EntryPointService;
use WPML\FP\Either;
use WPML\Core\Component\Troubleshooting\TranslationTablesOptimization\Application\Service\MigrationDataService\MigrateDataService;
use WPML\Infrastructure\WordPress\Component\Troubleshooting\TranslationTablesOptimization\Domain\PreviousState\Query;
use WPML\Infrastructure\WordPress\Port\Persistence\QueryHandler;

class Endpoint implements IHandler {


	public function run( Collection $data ) {
		global $wpml_dic;

		$service   = $wpml_dic->make( EntryPointService::class );
		$isInitialRequest = $data->get( 'isInitialRequest', false );
		$remaining = $service->run( $data->get( 'migrationType' ), $isInitialRequest );

		return Either::of( $remaining );
	}


}