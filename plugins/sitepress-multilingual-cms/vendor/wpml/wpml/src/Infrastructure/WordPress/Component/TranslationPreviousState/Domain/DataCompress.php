<?php

namespace WPML\Infrastructure\WordPress\Component\TranslationPreviousState\Domain;

use WPML\Core\Component\TranslationPreviousState\Domain\DataCompressInterface;

/**
 * @phpstan-import-type   PreviousStateData from \WPML\Core\Component\TranslationPreviousState\Domain\PreviousState
 *
 */
class DataCompress implements DataCompressInterface {

  /**
   * @var callable
   */
  private $functionChecker;


  /**
   * DataCompress constructor.
   *
   * @param callable|null $functionChecker Function to check if a PHP function exists
   */
  public function __construct( $functionChecker = null ) {
    $this->functionChecker = $functionChecker ?? function ( string $name ): bool {
      return function_exists( $name );
    };
  }


  /**
   * @inheritDoc
   */
  public function compress( array $data ): string {
    $serialized = serialize( $data );
    if ( ( $this->functionChecker )( 'gzcompress' ) ) {
      $compressed = gzcompress( $serialized, 9 );
      if ( ! $compressed ) {
        return $serialized;
      }

      return base64_encode( $compressed );
    }

    return $serialized;
  }


  /**
   * @inheritDoc
   */
  public function decompress( string $data ): array {
    if ( empty( $data ) ) {
      return [];
    }

    // Check if the data is base64 encoded (compressed)
    if ( $this->isCompressed( $data ) && ( $this->functionChecker )( 'gzuncompress' ) ) {
      $decoded = base64_decode( $data, true );
      if ( $decoded !== false ) {
        $decompressed = @gzuncompress( $decoded );
        if ( $decompressed !== false ) {
          return $this->unserializeData( $decompressed );
        }
      }
    }

    return $this->unserializeData( $data );
  }


  /**
   * @param string $data The data to unserialize
   *
   * @return PreviousStateData
   */
  private function unserializeData( string $data ): array {
    $unserialized = @unserialize( $data );
    if ( is_array( $unserialized ) ) {
      return $unserialized;
    }

    return [];
  }


  public function isCompressed( string $data ): bool {
    // Base64 encoded strings have a specific pattern
    // They only contain A-Z, a-z, 0-9, +, /, and = (for padding)
    return (bool) preg_match( '/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $data );
  }


}
