<?php
/**
 * Personal data export/erasure for quiz leads.
 *
 * @since      1.2.0
 *
 * @package    Maqsy
 * @subpackage Maqsy/includes/quiz
 */

/**
 * Hooks quiz sessions into WordPress's Tools → Export/Erase Personal Data.
 *
 * Sessions are matched by the email address a visitor gave in a quiz.
 *
 * @since      1.2.0
 * @package    Maqsy
 * @subpackage Maqsy/includes/quiz
 */
class Maqsy_Quiz_Privacy {

	/**
	 * Sessions handled per exporter/eraser page.
	 *
	 * @since 1.2.0
	 * @var   int
	 */
	const PER_PAGE = 50;

	/**
	 * Register the exporter.
	 *
	 * @since  1.2.0
	 * @param  array $exporters Registered exporters.
	 * @return array
	 */
	public function register_exporter( $exporters ) {
		$exporters['maqsy-quiz'] = array(
			'exporter_friendly_name' => __( 'Maqsy quiz responses', 'maqsy-faq-ai-chat' ),
			'callback'               => array( $this, 'export' ),
		);
		return $exporters;
	}

	/**
	 * Register the eraser.
	 *
	 * @since  1.2.0
	 * @param  array $erasers Registered erasers.
	 * @return array
	 */
	public function register_eraser( $erasers ) {
		$erasers['maqsy-quiz'] = array(
			'eraser_friendly_name' => __( 'Maqsy quiz responses', 'maqsy-faq-ai-chat' ),
			'callback'             => array( $this, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * Export one page of a person's quiz sessions.
	 *
	 * @since  1.2.0
	 * @param  string $email The person's email address.
	 * @param  int    $page  1-based page.
	 * @return array
	 */
	public function export( $email, $page = 1 ) {

		$rows  = $this->find( $email, $page );
		$items = array();

		foreach ( $rows as $row ) {
			$quiz    = Maqsy_Quizzes::get_definition( (int) $row['quiz_id'] );
			$answers = json_decode( (string) $row['answers'], true );
			$map     = Maqsy_Quiz_Engine::answer_map( is_array( $answers ) ? $answers : array() );

			$data = array(
				array(
					'name'  => __( 'Quiz', 'maqsy-faq-ai-chat' ),
					'value' => get_the_title( (int) $row['quiz_id'] ),
				),
				array(
					'name'  => __( 'Started', 'maqsy-faq-ai-chat' ),
					'value' => $row['started_at'] . ' UTC',
				),
				array(
					'name'  => __( 'Name', 'maqsy-faq-ai-chat' ),
					'value' => $row['lead_name'],
				),
				array(
					'name'  => __( 'Email', 'maqsy-faq-ai-chat' ),
					'value' => $row['lead_email'],
				),
				array(
					'name'  => __( 'Phone', 'maqsy-faq-ai-chat' ),
					'value' => $row['lead_phone'],
				),
			);
			foreach ( $quiz['questions'] as $question ) {
				if ( isset( $map[ $question['id'] ] ) ) {
					$data[] = array(
						'name'  => $question['text'],
						'value' => Maqsy_Quiz_Engine::answer_label( $question, $map[ $question['id'] ] ),
					);
				}
			}

			$items[] = array(
				'group_id'    => 'maqsy-quiz',
				'group_label' => __( 'Quiz responses', 'maqsy-faq-ai-chat' ),
				'item_id'     => 'maqsy-quiz-session-' . $row['id'],
				'data'        => $data,
			);
		}

		return array(
			'data' => $items,
			'done' => count( $rows ) < self::PER_PAGE,
		);
	}

	/**
	 * Erase one page of a person's quiz sessions (and their events).
	 *
	 * Always reads page 1: each pass deletes what it finds, so the next
	 * batch moves up.
	 *
	 * @since  1.2.0
	 * @param  string $email The person's email address.
	 * @return array
	 */
	public function erase( $email ) {

		global $wpdb;

		$rows = $this->find( $email, 1 );
		$ids  = array_map( 'intval', array_column( $rows, 'id' ) );

		if ( $ids ) {
			$in = implode( ',', $ids );
			// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- $in is a list of integers.
			$wpdb->query( 'DELETE FROM ' . Maqsy_Quiz_Store::events_table() . " WHERE session_id IN ( $in )" );
			$wpdb->query( 'DELETE FROM ' . Maqsy_Quiz_Store::sessions_table() . " WHERE id IN ( $in )" );
			// phpcs:enable
		}

		return array(
			'items_removed'  => count( $ids ),
			'items_retained' => false,
			'messages'       => array(),
			'done'           => count( $rows ) < self::PER_PAGE,
		);
	}

	/**
	 * Find a page of sessions by lead email.
	 *
	 * @since  1.2.0
	 * @param  string $email Email address.
	 * @param  int    $page  1-based page.
	 * @return array[]
	 */
	private function find( $email, $page ) {

		global $wpdb;

		if ( ! is_email( $email ) ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . Maqsy_Quiz_Store::sessions_table() . ' WHERE lead_email = %s ORDER BY id LIMIT %d OFFSET %d', $email, self::PER_PAGE, ( max( 1, (int) $page ) - 1 ) * self::PER_PAGE ), ARRAY_A );
	}
}
