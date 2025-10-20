<?php

namespace WPML\Core\Component\Troubleshooting\TranslationTablesOptimization\Domain\MigrationStatus;

final class MigrationStatus {

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


  /**
   * @param bool $completed
   *
   * @return void
   */
  public function setPrevStateCompleted( bool $completed ) {
    $this->prevStateCompleted = $completed;
  }


  /**
   * @param bool $completed
   *
   * @return void
   */
  public function setTranslationPackageCompleted( bool $completed ) {
    $this->translationPackageCompleted = $completed;
  }


  /**
   * @param bool $completed
   *
   * @return void
   */
  public function setObsoleteTranslationElementsRemovalCompleted( bool $completed ) {
    $this->obsoleteTranslationElementsRemovalCompleted = $completed;
  }


  /**
   * @param bool $completed
   *
   * @return void
   */
  public function setTranslationElementsCompressionCompleted( bool $completed ) {
    $this->translationElementsCompressionCompleted = $completed;
  }


  public static function createCompletedStatus(): self {
    return new self( true, true, true, true );
  }


}
