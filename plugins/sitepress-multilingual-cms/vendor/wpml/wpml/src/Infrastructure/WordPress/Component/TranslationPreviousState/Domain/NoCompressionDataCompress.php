<?php

namespace WPML\Infrastructure\WordPress\Component\TranslationPreviousState\Domain;

use WPML\Core\Component\TranslationPreviousState\Domain\DataCompressInterface;

/**
 * @phpstan-import-type   PreviousStateData from \WPML\Core\Component\TranslationPreviousState\Domain\PreviousState
 *
 */
class NoCompressionDataCompress implements DataCompressInterface {


  /**
   * @inheritDoc
   */
  public function compress( array $data ): string {
    return serialize( $data );
  }


  /**
   * @inheritDoc
   */
  public function decompress( string $data ): array {
    if ( empty( $data ) ) {
      return [];
    }

    $unserialized = @unserialize( $data );
    if ( is_array( $unserialized ) ) {
      return $unserialized;
    }

    return [];
  }


  /**
   * @inheritDoc
   */
  public function isCompressed( string $data ): bool {
    // Always return false as this implementation doesn't use compression
    return false;
  }


}
