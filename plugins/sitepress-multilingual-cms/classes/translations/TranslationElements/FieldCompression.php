<?php

namespace WPML\Translation\TranslationElements;

class FieldCompression {

	/** @var callable|null */
	private static $functionExistsCallback;

	/**
	 * @param callable|null $functionExistsCallback
	 */
	public static function setFunctionExistsCallback( $functionExistsCallback = null ) {
		self::$functionExistsCallback = $functionExistsCallback;
	}

	/**
	 * @param string $functionName
	 *
	 * @return bool
	 */
	private static function functionExists( string $functionName ): bool {
		if ( self::$functionExistsCallback ) {
			return ( self::$functionExistsCallback )( $functionName );
		}

		return function_exists( $functionName );
	}

	/**
	 * @param string|null $data
	 * @param bool $isAlreadyBase64Compressed
	 *
	 * @return string|null
	 */
	public static function compress( $data, bool $isAlreadyBase64Compressed = true ) {
		if ( $data === null ) {
			return null;
		}

		if ( ! self::functionExists( 'gzcompress' ) || $data === '' ) {
			return $isAlreadyBase64Compressed ? $data : base64_encode( $data );
		}

		$decoded = $isAlreadyBase64Compressed ? base64_decode( $data ) : $data;
		if ( $decoded === false ) {
			return $data;
		}

		$compressed = gzcompress( $decoded );
		if ( $compressed === false ) {
			return $data;
		}

		return base64_encode( $compressed );
	}

	/**
	 * @param string|null $data
	 * @param bool $preserveBase64Encoding
	 *
	 * @return string|null
	 */
	public static function decompress( $data, bool $preserveBase64Encoding = false ) {
		if ( $data === null ) {
			return null;
		}

		if ( ! self::functionExists( 'gzuncompress' ) || $data === '' ) {
			return $preserveBase64Encoding ? $data : base64_decode( $data );
		}

		$decoded = base64_decode( $data );
		if ( $decoded === false ) {
			return $data;
		}

		$decompressed = @gzuncompress( $decoded );
		if ( $decompressed === false ) {
			return $preserveBase64Encoding ? $data : $decoded;
		}

		return $preserveBase64Encoding ? base64_encode( $decompressed ) : $decompressed;
	}
}