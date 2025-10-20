<?php

namespace WPML\Core\Component\Troubleshooting\TranslationTablesOptimization\Application\Service\MigrationStatus;

use WPML\Core\Component\Troubleshooting\TranslationTablesOptimization\Domain\MigrationStatus\MigrationStatus;

final class MigrationStatusDTO {

  /** @var bool */
  private $prevStateCompleted;

  /** @var bool */
  private $translationPackageCompleted;

  /** @var bool */
  private $obsoleteTranslationElementsRemovalCompleted;

  /** @var bool */
  private $translationElementsCompressionCompleted;


  public function __construct(
    bool $prevStateCompleted,
    bool $translationPackageCompleted,
    bool $obsoleteTranslationElementsRemovalCompleted,
    bool $translationElementsCompressionCompleted
  ) {
    $this->prevStateCompleted                          = $prevStateCompleted;
    $this->translationPackageCompleted                 = $translationPackageCompleted;
    $this->obsoleteTranslationElementsRemovalCompleted = $obsoleteTranslationElementsRemovalCompleted;
    $this->translationElementsCompressionCompleted     = $translationElementsCompressionCompleted;
  }


  public static function from( MigrationStatus $migrationStatus ): self {
    return new self(
      $migrationStatus->isPrevStateCompleted(),
      $migrationStatus->isTranslationPackageCompleted(),
      $migrationStatus->isObsoleteTranslationElementsRemovalCompleted(),
      $migrationStatus->isTranslationElementsCompressionCompleted()
    );
  }


  public function isPrevStateCompleted(): bool {
    return $this->prevStateCompleted;
  }


  public function isTranslationPackageCompleted(): bool {
    return $this->translationPackageCompleted;
  }


  public function isObsoleteTranslationElementsRemovalCompleted(): bool {
    return $this->obsoleteTranslationElementsRemovalCompleted;
  }


  public function isTranslationElementsCompressionCompleted(): bool {
    return $this->translationElementsCompressionCompleted;
  }


  public function isTotalProcessCompleted(): bool {
    return $this->prevStateCompleted
           && $this->translationPackageCompleted
           && $this->obsoleteTranslationElementsRemovalCompleted
           && $this->translationElementsCompressionCompleted;
  }


}
