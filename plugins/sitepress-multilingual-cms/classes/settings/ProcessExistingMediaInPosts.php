<?php

namespace WPML\TM\Settings;

use WPML\Collect\Support\Collection;
use WPML\Core\BackgroundTask\Command\UpdateBackgroundTask;
use WPML\Core\BackgroundTask\Model\BackgroundTask;
use WPML\BackgroundTask\AbstractTaskEndpoint;
use WPML\Core\BackgroundTask\Service\BackgroundTaskService;
use WPML\FP\Obj;
use WPML\MediaTranslation\PostWithMediaFilesFactory;

class ProcessExistingMediaInPosts extends AbstractTaskEndpoint {
	const LOCK_TIME         = 5;
	const MAX_RETRIES       = 10;
	const DESCRIPTION       = 'Processing media in posts.';
	const POSTS_PER_REQUEST = 40;

	/** @var \wpdb */
	private $wpdb;

	/** @var PostWithMediaFilesFactory $postWithMediaFilesFactory */
	private $postWithMediaFilesFactory;

	/**
	 * @param \wpdb                     $wpdb
	 * @param UpdateBackgroundTask      $updateBackgroundTask
	 * @param BackgroundTaskService     $backgroundTaskService
	 * @param PostWithMediaFilesFactory $postWithMediaFilesFactory
	 */
	public function __construct(
		\wpdb $wpdb,
		UpdateBackgroundTask $updateBackgroundTask,
		BackgroundTaskService $backgroundTaskService,
		PostWithMediaFilesFactory $postWithMediaFilesFactory
	) {
		$this->wpdb                      = $wpdb;
		$this->postWithMediaFilesFactory = $postWithMediaFilesFactory;

		parent::__construct( $updateBackgroundTask, $backgroundTaskService );
	}

	public function runBackgroundTask( BackgroundTask $task ) {
		$payload = $task->getPayload();
		$page    = Obj::propOr( 1, 'page', $payload );
		$postIds = $this->getPosts( $page );

		if ( count( $postIds ) > 0 ) {
			$this->processExistingMediaInPosts( $postIds );
			$payload['page'] = $page + 1;
			$task->setPayload( $payload );
			$task->addCompletedCount( count( $postIds ) );
			$task->setRetryCount(0 );
			if ( $task->getCompletedCount() >= $task->getTotalCount() ) {
				$task->finish();
			}
		} else {
			$task->finish();
		}
		return $task;
	}

	public function getDescription( Collection $data ) {
		return __( self::DESCRIPTION, 'sitepress' );
	}

	public function getTotalRecords( Collection $data ) {
		return $this->getPostsCount();
	}

	private function getAllowedPostTypes() {
		$allowedTypes = [
			'post',
			'page',
			'product',
			'portfolio',
			'project',
			'elementor_library',
			'vc_templates',
			'so_panels',
			'gallery',
			'slides',
			'slider'
		];

		return $allowedTypes;
	}

	/**
	 * @param int $page
	 *
	 * @return array
	 */
	private function getPosts( $page ) {
		$postTypes = $this->getAllowedPostTypes();
		if ( count( $postTypes ) === 0 ) {
			return [];
		}

		return $this->wpdb->get_col(
			$this->wpdb->prepare(
				"SELECT DISTINCT p.ID
						FROM {$this->wpdb->posts} AS p
						INNER JOIN {$this->wpdb->prefix}icl_translations t ON t.element_id = p.ID
						WHERE t.source_language_code IS NULL
						AND p.post_status NOT IN ('auto-draft', 'trash', 'inherit')
						AND p.post_type IN (" . wpml_prepare_in( $postTypes, '%s' ) . ")
						ORDER BY p.ID ASC
						LIMIT %d OFFSET %d",
				self::POSTS_PER_REQUEST,
				($page-1)*self::POSTS_PER_REQUEST
			)
		);
	}

	/**
	 * @return int
	 */
	private function getPostsCount() {
		$postTypes = $this->getAllowedPostTypes();
		if ( count( $postTypes ) === 0 ) {
			return 0;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $this->wpdb->get_var(
			"SELECT COUNT(DISTINCT(p.ID))
					FROM {$this->wpdb->posts} AS p
					INNER JOIN {$this->wpdb->prefix}icl_translations t ON t.element_id = p.ID
					WHERE t.source_language_code IS NULL
					AND p.post_status NOT IN ('auto-draft', 'trash', 'inherit')
					AND p.post_type IN (" . wpml_prepare_in( $postTypes, '%s' ) . ")"
		);
	}

	/**
	 * @param array $postIds
	 */
	private function processExistingMediaInPosts( array $postIds ) {
		foreach ( $postIds as $postId ) {
			$postMedia = $this->postWithMediaFilesFactory->create( $postId );
			$postMedia->extract_and_save_media_ids();
		}
	}
}
