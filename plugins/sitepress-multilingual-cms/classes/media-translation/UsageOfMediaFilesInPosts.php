<?php

namespace WPML\MediaTranslation;

class UsageOfMediaFilesInPosts {
	const USAGES_AS_COPY_IN_POSTS_FIELD_NAME = '_wpml_media_usage_in_posts_as_copy';
	const USAGES_AS_REFERENCE_IN_POSTS_FIELD_NAME = '_wpml_media_usage_in_posts_as_reference';

	/**
	 * @param int   $post_id
	 * @param array $last_copied_media_file_ids
	 * @param array $last_referenced_media_file_ids
	 * @param array $copied_media_file_ids
	 * @param array $referenced_media_file_ids
	 */
	public function updateUsages( $post_id, $last_copied_media_file_ids, $last_referenced_media_file_ids, $copied_media_file_ids, $referenced_media_file_ids ) {
		$this->update( $post_id, $last_copied_media_file_ids, $copied_media_file_ids, self::USAGES_AS_COPY_IN_POSTS_FIELD_NAME );
		$this->update( $post_id, $last_referenced_media_file_ids, $referenced_media_file_ids, self::USAGES_AS_REFERENCE_IN_POSTS_FIELD_NAME );
	}

	private function update( $post_id, $last_media_file_ids, $media_file_ids, $field_name ) {
		foreach ( $last_media_file_ids as $last_media_file_id ) {
			$last_usages = $this->getUsages( $last_media_file_id, $field_name );
			if ( ! in_array( $post_id, $last_usages ) ) {
				continue;
			}

			$last_usages = array_diff( $last_usages, array( $post_id ) );
			update_post_meta( $last_media_file_id, $field_name, $last_usages );
		}

		foreach ( $media_file_ids as $media_file_id ) {
			$usages = $this->getUsages( $media_file_id, $field_name );
			$usages = array_merge( $usages, array( $post_id ) );
			$usages = array_values( array_unique( $usages ) );
			update_post_meta( $media_file_id, $field_name, $usages );
		}
	}

	/**
	 * @return array
	 */
	public function getUsagesAsCopy( $media_file_id ) {
		return $this->getUsages( $media_file_id, self::USAGES_AS_COPY_IN_POSTS_FIELD_NAME );
	}

	/**
	 * @return array
	 */
	public function getUsagesAsReference( $media_file_id ) {
		return $this->getUsages( $media_file_id, self::USAGES_AS_REFERENCE_IN_POSTS_FIELD_NAME );
	}

	private function getUsages( $media_file_id, $field_name ) {
		$usages = get_post_meta( $media_file_id, $field_name, true );
		return empty( $usages ) ? array() : $usages;
	}
}