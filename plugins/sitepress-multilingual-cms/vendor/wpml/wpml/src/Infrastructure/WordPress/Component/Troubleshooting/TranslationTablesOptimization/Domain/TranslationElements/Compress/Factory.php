<?php

namespace WPML\Infrastructure\WordPress\Component\Troubleshooting\TranslationTablesOptimization\Domain\TranslationElements\Compress;

use WPML\Core\Component\Troubleshooting\TranslationTablesOptimization\Domain\MigrationDataService\CompletedRecordsStorageInterface;
use WPML\Core\Component\Troubleshooting\TranslationTablesOptimization\Domain\MigrationDataService\ProcessorInterface;
use WPML\Core\Component\Troubleshooting\TranslationTablesOptimization\Domain\MigrationDataService\QueryInterface;
use WPML\Core\Component\Troubleshooting\TranslationTablesOptimization\Domain\TranslationElements\Compress\Factory as CompressFactory;
use WPML\Core\Port\Persistence\DatabaseSchemaInfoInterface;

class Factory implements CompressFactory {

  /** @var DatabaseSchemaInfoInterface */
  private $databaseSchemaInfo;

  /** @var \wpdb */
  private $wpdb;


  public function __construct(
    DatabaseSchemaInfoInterface $databaseSchemaInfo,
    \wpdb $wpdb
  ) {
    $this->databaseSchemaInfo = $databaseSchemaInfo;
    $this->wpdb               = $wpdb;
  }


  public function createQuery(): QueryInterface {
    return new Query(
      $this->wpdb
    );
  }


  public function createCompletedRecordsStorage(): CompletedRecordsStorageInterface {
    return new CompletedRecordsStorage(
      $this->wpdb,
      $this->databaseSchemaInfo
    );
  }


  /**
   * @return ProcessorInterface<object{tid: int, fieldData: string, fieldDataTranslated: string}>
   * @psalm-suppress ImplementedReturnTypeMismatch
   */
  public function createProcessor(): ProcessorInterface {
    return new Processor(
      $this->wpdb
    );
  }


}
