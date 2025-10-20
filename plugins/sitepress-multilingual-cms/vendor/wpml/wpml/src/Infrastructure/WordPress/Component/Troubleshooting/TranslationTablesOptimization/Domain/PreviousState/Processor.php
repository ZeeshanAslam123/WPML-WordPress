<?php

namespace WPML\Infrastructure\WordPress\Component\Troubleshooting\TranslationTablesOptimization\Domain\PreviousState;

use WPML\Core\Component\TranslationPreviousState\Domain\DataCompressInterface;
use WPML\Core\Component\TranslationPreviousState\Domain\PreviousState;
use WPML\Core\Component\TranslationPreviousState\Domain\PreviousStateRepositoryInterface;
use WPML\Core\Component\Troubleshooting\TranslationTablesOptimization\Domain\MigrationDataService\ProcessorInterface;
use WPML\PHP\Exception\InvalidItemIdException;

/**
 * @implements ProcessorInterface<array{translationId: int, previousState: string}>
 */
class Processor implements ProcessorInterface {

  /** @var PreviousStateRepositoryInterface */
  private $repository;

  /** @var DataCompressInterface */
  private $dataCompress;


  public function __construct(
    PreviousStateRepositoryInterface $repository,
    DataCompressInterface $dataCompress
  ) {
    $this->repository   = $repository;
    $this->dataCompress = $dataCompress;
  }


  /**
   * @param array<array{translationId: int, previousState: string}> $records
   *
   * @return int[]
   */
  public function process( array $records ): array {
    $processed = [];

    foreach ( $records as $record ) {
      if ( ! $this->dataCompress->isCompressed( $record['previousState'] ) ) {
        $data = $this->dataCompress->decompress( $record['previousState'] );
        if ( empty( $data ) ) {
          continue;
        }

        $previousState = PreviousState::fromArray( $data );
        try {
          $this->repository->update( $record['translationId'], $previousState );
          $processed[] = $record['translationId'];
        } catch ( InvalidItemIdException $e ) {
          continue;
        }
      }
    }

    return $processed;
  }


}
