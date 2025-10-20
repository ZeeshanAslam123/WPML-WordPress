<?php

namespace WPML\Core\Component\Troubleshooting\TranslationTablesOptimization\Application\Service;

use WPML\Core\Component\Troubleshooting\TranslationTablesOptimization\Application\Service\MigrationStatus\MigrationStatusDTO;
use WPML\Core\Component\Troubleshooting\TranslationTablesOptimization\Application\Service\MigrationStatus\MigrationStatusService;
use WPML\Core\Component\Troubleshooting\TranslationTablesOptimization\Domain\PreviousState\Factory as PreviousStateFactory;
use WPML\Core\Component\Troubleshooting\TranslationTablesOptimization\Domain\TranslationElements\Compress\Factory as CompressFactory;
use WPML\Core\Component\Troubleshooting\TranslationTablesOptimization\Domain\TranslationElements\RemoveOld\Factory as RemoveOldFactory;
use WPML\Core\Component\Troubleshooting\TranslationTablesOptimization\Domain\TranslationPackageColumnInterface;

final class EntryPointService {

  /** @var PreviousStateFactory */
  private $previousStateFactory;

  /** @var CompressFactory */
  private $compressFactory;

  /** @var RemoveOldFactory */
  private $removeOldFactory;

  /** @var TranslationPackageColumnInterface */
  private $translationStatusSchemaManager;

  /** @var MigrationStatusService */
  private $migrationStatusService;


  public function __construct(
    PreviousStateFactory $previousStateFactory,
    CompressFactory $compressFactory,
    RemoveOldFactory $removeOldFactory,
    TranslationPackageColumnInterface $translationStatusSchemaManager,
    MigrationStatusService $migrationStatusService
  ) {
    $this->previousStateFactory           = $previousStateFactory;
    $this->compressFactory                = $compressFactory;
    $this->removeOldFactory               = $removeOldFactory;
    $this->translationStatusSchemaManager = $translationStatusSchemaManager;
    $this->migrationStatusService         = $migrationStatusService;
  }


  /**
   * @inheritDoct
   */
  public function run( string $migrationToRun, bool $isInitialRequest = false ): int {
    $migrationStatus = $this->migrationStatusService->getMigrationStatus();

    if ( $migrationToRun === 'previous-state' ) {
      $remainingRecords = $this->runPreviousStateMigration( $migrationStatus, $isInitialRequest );

      return $remainingRecords;
    }

    if ( $migrationToRun === 'translation-elements' ) {
      $remainingRecords = $this->runTranslationElementsMigration( $migrationStatus, $isInitialRequest );

      return $remainingRecords;
    }

    if ( $migrationToRun === 'truncate-translation-package' ) {
      if ( $migrationStatus->isPrevStateCompleted() &&
           $migrationStatus->isObsoleteTranslationElementsRemovalCompleted() &&
           $migrationStatus->isTranslationElementsCompressionCompleted() &&
           ! $migrationStatus->isTranslationPackageCompleted()
      ) {
        $this->translationStatusSchemaManager->truncate();
        $this->migrationStatusService->markTranslationPackageCompleted();
      }
    }

    return 0;
  }


  private function runPreviousStateMigration( MigrationStatusDTO $migrationStatus, bool $isInitialRequest ): int {
    if ( $migrationStatus->isPrevStateCompleted() ) {
      return 0;
    }

    $migrateDataService = new MigrateDataService( $this->previousStateFactory );

    if ( $isInitialRequest ) {
      $remainingRecords = $migrateDataService->initProcessAndGetTotalElements();
    } else {
      $migrateDataService->run( 1000 );
      $remainingRecords = $migrateDataService->countRemaining();
    }

    if ( $remainingRecords === 0 ) {
      $migrateDataService->cleanTheProcess();
      $this->migrationStatusService->markPrevStateCompleted();
    }

    return $remainingRecords;
  }


  private function runTranslationElementsMigration( MigrationStatusDTO $migrationStatus, bool $isInitialRequest ): int {
    if ( ! $migrationStatus->isObsoleteTranslationElementsRemovalCompleted() ) {
      return $this->runObsoleteTranslationElementsRemovalMigration( $isInitialRequest );
    }

    if ( ! $migrationStatus->isTranslationElementsCompressionCompleted() ) {
      return $this->runTranslationElementsCompressionMigration();
    }

    return 0;
  }


  private function runObsoleteTranslationElementsRemovalMigration( bool $isInitialRequest ): int {
    $migrateDataService       = new MigrateDataService( $this->removeOldFactory );
    $compressMigrationService = new MigrateDataService( $this->compressFactory );

    if ( $isInitialRequest ) {
      $remainingRecords = $migrateDataService->initProcessAndGetTotalElements();
      $compressMigrationService->initProcessAndGetTotalElements();
    } else {
      $migrateDataService->run( 1000 );
      $remainingRecords = $migrateDataService->countRemaining();
    }

    if ( $remainingRecords === 0 ) {
      $migrateDataService->cleanTheProcess();
      $this->migrationStatusService->markObsoleteTranslationElementsRemovalCompleted();
    }

    return $remainingRecords + $compressMigrationService->countRemaining();
  }


  private function runTranslationElementsCompressionMigration(): int {
    $migrateDataService = new MigrateDataService( $this->compressFactory );

    $migrateDataService->run( 1000 );
    $remainingRecords = $migrateDataService->countRemaining();

    if ( $remainingRecords === 0 ) {
      $migrateDataService->cleanTheProcess();
      $this->migrationStatusService->markTranslationElementsCompressionCompleted();
    }

    return $remainingRecords;
  }


}
