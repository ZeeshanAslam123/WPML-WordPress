<?php

namespace WPML\Core\Component\TranslationPreviousState\Domain;

/**
 * @phpstan-import-type   PreviousStateData from \WPML\Core\Component\TranslationPreviousState\Domain\PreviousState
 */
interface DataCompressInterface {


  /**
   * @param array<string, mixed> $data
   *
   * @return string
   */
  public function compress( array $data ): string;


  /**
   * @param string $data
   *
   * @return PreviousStateData
   */
  public function decompress( string $data ): array;


  public function isCompressed( string $data ): bool;


}
