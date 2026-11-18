<?php
/**
 * Quiz analytics aggregation.
 *
 * @since      1.2.0
 *
 * @package    Maqsy
 * @subpackage Maqsy/includes/quiz
 */

/**
 * Aggregates the sessions and events tables into dashboard reports.
 *
 * Metric definitions (kept in one place so the dashboard, the quiz list
 * table, and CSV export agree):
 *
 * - Starts:       sessions in range, excluding ones the visitor restarted
 *                 (the restart itself is a new session, counted instead).
 * - Completions:  started sessions that reached a result.
 * - Drop-offs:    unfinished sessions idle for longer than
 *                 Maqsy_Quiz_Store::INACTIVE_AFTER.
 * - In progress:  unfinished sessions active more recently than that.
 * - Leads:        started sessions where at least one contact field was
 *                 captured (a restart carries them over to the new session).
 * - CTA clicks:   started sessions where the visitor clicked a result's CTA.
 *
 * @since      1.2.0
 * @package    Maqsy
 * @subpackage Maqsy/includes/quiz
 */
class Maqsy_Quiz_Analytics {

	/**
	 * Sessions per page in get_sessions().
	 *
	 * @since 1.2.0
	 * @var   int
	 */
	const SESSIONS_PER_PAGE = 20;

	/**
	 * Build the full dashboard report.
	 *
	 * @since  1.2.0
	 * @param  int $quiz_id Quiz ID, or 0 for all quizzes.
	 * @param  int $days    Range in days, or 0 for all time.
	 * @return array
	 */
	public static function get_report( $quiz_id, $days ) {

		$range = self::range( $days );

		$report = array(
			'range'    => array(
				'from' => $range['from'],
				'to'   => $range['to'],
				'days' => (int) $days,
			),
			'totals'   => self::get_totals( $quiz_id, $range ),
			'daily'    => self::get_daily( $quiz_id, $range ),
			'sources'  => self::get_sources( $quiz_id, $range ),
			'outcomes' => array(),
			'funnel'   => array(),
			'quizzes'  => array(),
		);

		if ( $quiz_id ) {
			$quiz               = Maqsy_Quizzes::get_definition( $quiz_id );
			$report['outcomes'] = self::get_outcomes( $quiz_id, $quiz, $range );
			$report['funnel']   = self::get_funnel( $quiz_id, $quiz, $range );
		} else {
			$report['quizzes'] = self::get_quiz_breakdown( $range );
		}

		return $report;
	}

	/**
	 * Headline counts.
	 *
	 * @since  1.2.0
	 * @param  int   $quiz_id Quiz ID, or 0 for all.
	 * @param  array $range   From range().
	 * @return array
	 */
	public static function get_totals( $quiz_id, array $range ) {

		global $wpdb;

		$sessions = Maqsy_Quiz_Store::sessions_table();
		$where    = self::where( $quiz_id, $range );
		$inactive = gmdate( 'Y-m-d H:i:s', time() - Maqsy_Quiz_Store::INACTIVE_AFTER );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- $where is built by where() from prepared fragments.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					SUM( status <> 'restarted' ) AS starts,
					SUM( status = 'completed' ) AS completions,
					SUM( status IN ( 'active', 'paused' ) AND updated_at < %s ) AS dropoffs,
					SUM( status IN ( 'active', 'paused' ) AND updated_at >= %s ) AS in_progress,
					SUM( status = 'restarted' ) AS restarts,
					SUM( status <> 'restarted' AND ( lead_name <> '' OR lead_email <> '' OR lead_phone <> '' ) ) AS leads,
					SUM( status <> 'restarted' AND cta_clicked = 1 ) AS cta_sessions
				FROM $sessions WHERE $where",
				$inactive,
				$inactive
			),
			ARRAY_A
		);

		$cta_clicks = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Maqsy_Quiz_Store::events_table() . " WHERE type = 'cta_click' AND " . self::where( $quiz_id, $range, 'created_at' ) );
		// phpcs:enable

		$totals = array_map( 'intval', (array) $row );
		$starts = $totals['starts'];

		$totals['cta_clicks']      = $cta_clicks;
		$totals['completion_rate'] = $starts ? round( $totals['completions'] / $starts, 4 ) : 0;
		$totals['lead_rate']       = $starts ? round( $totals['leads'] / $starts, 4 ) : 0;
		$totals['cta_rate']        = $totals['completions'] ? round( $totals['cta_sessions'] / $totals['completions'], 4 ) : 0;

		return $totals;
	}

	/**
	 * Starts and completions per day (UTC), with zero-filled gaps.
	 *
	 * @since  1.2.0
	 * @param  int   $quiz_id Quiz ID, or 0 for all.
	 * @param  array $range   From range().
	 * @return array[] Each { date, starts, completions }.
	 */
	private static function get_daily( $quiz_id, array $range ) {

		global $wpdb;

		$sessions = Maqsy_Quiz_Store::sessions_table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
		$starts      = $wpdb->get_results( "SELECT DATE( started_at ) AS day, COUNT(*) AS n FROM $sessions WHERE status <> 'restarted' AND " . self::where( $quiz_id, $range ) . ' GROUP BY day', OBJECT_K );
		$completions = $wpdb->get_results( "SELECT DATE( completed_at ) AS day, COUNT(*) AS n FROM $sessions WHERE status = 'completed' AND " . self::where( $quiz_id, $range, 'completed_at' ) . ' GROUP BY day', OBJECT_K );
		// phpcs:enable

		// "All time" starts the chart at the first recorded day.
		$from = $range['from'];
		if ( ! $from ) {
			$days = array_keys( $starts );
			sort( $days );
			$from = $days ? $days[0] : gmdate( 'Y-m-d' );
		}

		$series = array();
		$cursor = strtotime( substr( $from, 0, 10 ) . ' 00:00:00 UTC' );
		$end    = strtotime( gmdate( 'Y-m-d' ) . ' 00:00:00 UTC' );
		// Cap at a year of bars; longer ranges are still fully counted in totals.
		$cursor = max( $cursor, $end - 365 * DAY_IN_SECONDS );
		for ( ; $cursor <= $end; $cursor += DAY_IN_SECONDS ) {
			$day      = gmdate( 'Y-m-d', $cursor );
			$series[] = array(
				'date'        => $day,
				'starts'      => isset( $starts[ $day ] ) ? (int) $starts[ $day ]->n : 0,
				'completions' => isset( $completions[ $day ] ) ? (int) $completions[ $day ]->n : 0,
			);
		}

		return $series;
	}

	/**
	 * How sessions were started: chat suggestion, quiz menu, or a link/shortcode.
	 *
	 * @since  1.2.0
	 * @param  int   $quiz_id Quiz ID, or 0 for all.
	 * @param  array $range   From range().
	 * @return array Counts keyed by source.
	 */
	private static function get_sources( $quiz_id, array $range ) {

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
		$rows = $wpdb->get_results( 'SELECT source, COUNT(*) AS n FROM ' . Maqsy_Quiz_Store::sessions_table() . " WHERE status <> 'restarted' AND " . self::where( $quiz_id, $range ) . ' GROUP BY source', ARRAY_A );

		$sources = array(
			'manual'    => 0,
			'suggested' => 0,
			'link'      => 0,
			'keyword'   => 0,
		);
		foreach ( $rows as $row ) {
			$sources[ $row['source'] ] = (int) $row['n'];
		}

		return $sources;
	}

	/**
	 * Completed sessions per outcome, in the quiz's outcome order.
	 *
	 * @since  1.2.0
	 * @param  int   $quiz_id Quiz ID.
	 * @param  array $quiz    The quiz definition.
	 * @param  array $range   From range().
	 * @return array[] Each { id, title, count, cta_clicks }.
	 */
	private static function get_outcomes( $quiz_id, array $quiz, array $range ) {

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
		$rows = $wpdb->get_results( 'SELECT outcome_id, COUNT(*) AS n, SUM( cta_clicked ) AS cta FROM ' . Maqsy_Quiz_Store::sessions_table() . " WHERE status = 'completed' AND " . self::where( $quiz_id, $range ) . ' GROUP BY outcome_id', OBJECT_K );

		$outcomes = array();
		foreach ( $quiz['outcomes'] as $outcome ) {
			$outcomes[] = array(
				'id'         => $outcome['id'],
				'title'      => $outcome['title'] ? $outcome['title'] : __( '(untitled outcome)', 'maqsy-faq-ai-chat' ),
				'count'      => isset( $rows[ $outcome['id'] ] ) ? (int) $rows[ $outcome['id'] ]->n : 0,
				'cta_clicks' => isset( $rows[ $outcome['id'] ] ) ? (int) $rows[ $outcome['id'] ]->cta : 0,
			);
			unset( $rows[ $outcome['id'] ] );
		}

		// Results recorded for outcomes since deleted from the quiz.
		$orphaned = 0;
		foreach ( $rows as $row ) {
			$orphaned += (int) $row->n;
		}
		if ( $orphaned ) {
			$outcomes[] = array(
				'id'         => '',
				'title'      => __( '(removed or no outcome)', 'maqsy-faq-ai-chat' ),
				'count'      => $orphaned,
				'cta_clicks' => 0,
			);
		}

		return $outcomes;
	}

	/**
	 * Per-question funnel, drop-off, and answer distribution.
	 *
	 * @since  1.2.0
	 * @param  int   $quiz_id Quiz ID.
	 * @param  array $quiz    The quiz definition.
	 * @param  array $range   From range().
	 * @return array[]
	 */
	private static function get_funnel( $quiz_id, array $quiz, array $range ) {

		global $wpdb;

		$events   = Maqsy_Quiz_Store::events_table();
		$sessions = Maqsy_Quiz_Store::sessions_table();
		$inactive = gmdate( 'Y-m-d H:i:s', time() - Maqsy_Quiz_Store::INACTIVE_AFTER );
		// Restarted sessions are left out of starts and drop-offs, so leave
		// their answers out too, or a question could be "reached" by more
		// visitors than started the quiz.
		$where_ev = self::where( $quiz_id, $range, 'created_at' ) . " AND session_id NOT IN ( SELECT id FROM $sessions WHERE status = 'restarted' AND " . self::where( $quiz_id, array( 'from' => null ) ) . ' )';

		// Only choice answers are tallied; free-text and lead answers would
		// each be a distinct row that is decoded and then thrown away.
		$choice_ids = array();
		foreach ( $quiz['questions'] as $question ) {
			if ( 'text' !== $question['type'] ) {
				$choice_ids[] = $question['id'];
			}
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
		$responses = $wpdb->get_results( "SELECT question_id, type, COUNT( DISTINCT session_id ) AS n FROM $events WHERE type IN ( 'answer', 'skip' ) AND $where_ev GROUP BY question_id, type", ARRAY_A );
		$values    = $choice_ids ? $wpdb->get_results(
			$wpdb->prepare(
				"SELECT question_id, value, COUNT(*) AS n FROM $events WHERE type = 'answer' AND question_id IN ( " . implode( ', ', array_fill( 0, count( $choice_ids ), '%s' ) ) . " ) AND $where_ev GROUP BY question_id, value",
				$choice_ids
			),
			ARRAY_A
		) : array();
		$waiting   = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT current_question, SUM( updated_at < %s ) AS dropped, SUM( updated_at >= %s ) AS active FROM $sessions WHERE status IN ( 'active', 'paused' ) AND " . self::where( $quiz_id, $range ) . ' GROUP BY current_question',
				$inactive,
				$inactive
			),
			OBJECT_K
		);
		// phpcs:enable

		$counts = array();
		foreach ( $responses as $row ) {
			$counts[ $row['question_id'] ][ $row['type'] ] = (int) $row['n'];
		}

		$tallies = array();
		foreach ( $values as $row ) {
			$value = json_decode( (string) $row['value'], true );
			foreach ( is_array( $value ) ? $value : array( $value ) as $option_id ) {
				if ( is_string( $option_id ) ) {
					$tallies[ $row['question_id'] ][ $option_id ] = ( isset( $tallies[ $row['question_id'] ][ $option_id ] ) ? $tallies[ $row['question_id'] ][ $option_id ] : 0 ) + (int) $row['n'];
				}
			}
		}

		$funnel = array();
		foreach ( $quiz['questions'] as $question ) {
			$id       = $question['id'];
			$answered = isset( $counts[ $id ]['answer'] ) ? $counts[ $id ]['answer'] : 0;
			$skipped  = isset( $counts[ $id ]['skip'] ) ? $counts[ $id ]['skip'] : 0;
			$dropped  = isset( $waiting[ $id ] ) ? (int) $waiting[ $id ]->dropped : 0;
			$active   = isset( $waiting[ $id ] ) ? (int) $waiting[ $id ]->active : 0;

			$item = array(
				'id'       => $id,
				'text'     => $question['text'],
				'type'     => $question['type'],
				'reached'  => $answered + $skipped + $dropped + $active,
				'answered' => $answered,
				'skipped'  => $skipped,
				'dropped'  => $dropped,
				'options'  => array(),
				'recent'   => array(),
			);

			if ( 'text' === $question['type'] ) {
				// Contact details are shown in the leads table, not here.
				if ( ! $question['lead_field'] ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
					$recent = $wpdb->get_col( $wpdb->prepare( "SELECT value FROM $events WHERE type = 'answer' AND question_id = %s AND $where_ev ORDER BY id DESC LIMIT 20", $id ) );
					foreach ( $recent as $value ) {
						$item['recent'][] = (string) json_decode( $value, true );
					}
				}
			} else {
				foreach ( $question['options'] as $option ) {
					$item['options'][] = array(
						'id'    => $option['id'],
						'label' => $option['label'],
						'count' => isset( $tallies[ $id ][ $option['id'] ] ) ? $tallies[ $id ][ $option['id'] ] : 0,
					);
				}
			}

			$funnel[] = $item;
		}

		return $funnel;
	}

	/**
	 * One row of headline numbers per quiz, for the "All quizzes" view.
	 *
	 * @since  1.2.0
	 * @param  array $range From range().
	 * @return array[]
	 */
	private static function get_quiz_breakdown( array $range ) {

		$ids = get_posts(
			array(
				'post_type'   => Maqsy_Quizzes::POST_TYPE,
				'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'numberposts' => 200, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_numberposts -- bounded admin listing.
				'fields'      => 'ids',
				'orderby'     => 'title',
				'order'       => 'ASC',
			)
		);

		$counts = self::get_counts_for_quizzes( $ids, $range );
		$rows   = array();
		foreach ( $ids as $id ) {
			$row    = isset( $counts[ $id ] ) ? $counts[ $id ] : array(
				'starts'      => 0,
				'completions' => 0,
				'leads'       => 0,
				'cta'         => 0,
			);
			$rows[] = array_merge(
				array(
					'id'     => $id,
					'title'  => Maqsy_Quizzes::get_title( $id ),
					'status' => get_post_status( $id ),
				),
				$row
			);
		}

		usort(
			$rows,
			static function ( $a, $b ) {
				return $b['starts'] - $a['starts'];
			}
		);

		return $rows;
	}

	/**
	 * Starts, completions, leads and CTA sessions for several quizzes in one query.
	 *
	 * @since  1.2.0
	 * @param  int[] $ids   Quiz IDs.
	 * @param  array $range From range().
	 * @return array Keyed by quiz ID.
	 */
	public static function get_counts_for_quizzes( array $ids, array $range ) {

		global $wpdb;

		$ids = array_filter( array_map( 'intval', $ids ) );
		if ( ! $ids ) {
			return array();
		}

		$in = implode( ',', $ids );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- $in is a list of integers.
		$rows = $wpdb->get_results(
			'SELECT quiz_id,
				SUM( status <> \'restarted\' ) AS starts,
				SUM( status = \'completed\' ) AS completions,
				SUM( status <> \'restarted\' AND ( lead_name <> \'\' OR lead_email <> \'\' OR lead_phone <> \'\' ) ) AS leads,
				SUM( status <> \'restarted\' AND cta_clicked = 1 ) AS cta
			FROM ' . Maqsy_Quiz_Store::sessions_table() . " WHERE quiz_id IN ( $in ) AND " . self::where( 0, $range ) . ' GROUP BY quiz_id',
			ARRAY_A
		);
		// phpcs:enable

		$counts = array();
		foreach ( $rows as $row ) {
			$counts[ (int) $row['quiz_id'] ] = array(
				'starts'      => (int) $row['starts'],
				'completions' => (int) $row['completions'],
				'leads'       => (int) $row['leads'],
				'cta'         => (int) $row['cta'],
			);
		}

		return $counts;
	}

	/**
	 * A page of sessions (newest first), optionally only those with lead details.
	 *
	 * @since  1.2.0
	 * @param  int  $quiz_id    Quiz ID, or 0 for all.
	 * @param  int  $days       Range in days, or 0 for all time.
	 * @param  bool $leads_only Only sessions with captured contact details.
	 * @param  int  $page       1-based page number.
	 * @param  int  $per_page   Rows per page; 0 for no limit (CSV export).
	 * @return array { items, total }
	 */
	public static function get_sessions( $quiz_id, $days, $leads_only, $page = 1, $per_page = self::SESSIONS_PER_PAGE ) {

		global $wpdb;

		$range    = self::range( $days );
		$sessions = Maqsy_Quiz_Store::sessions_table();
		$where    = self::where( $quiz_id, $range );
		if ( $leads_only ) {
			$where .= " AND ( lead_name <> '' OR lead_email <> '' OR lead_phone <> '' )";
		}
		$limit = $per_page ? $wpdb->prepare( ' LIMIT %d OFFSET %d', $per_page, max( 0, $page - 1 ) * $per_page ) : '';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $sessions WHERE $where" );
		$rows  = $wpdb->get_results( "SELECT * FROM $sessions WHERE $where ORDER BY started_at DESC, id DESC$limit", ARRAY_A );
		// phpcs:enable

		$definitions = array();
		$items       = array();
		foreach ( $rows as $row ) {
			$id = (int) $row['quiz_id'];
			if ( ! isset( $definitions[ $id ] ) ) {
				$definitions[ $id ] = Maqsy_Quizzes::get_definition( $id );
			}
			$quiz    = $definitions[ $id ];
			$answers = json_decode( (string) $row['answers'], true );
			$answers = is_array( $answers ) ? $answers : array();
			$map     = Maqsy_Quiz_Engine::answer_map( $answers );

			$outcome_title = '';
			foreach ( $quiz['outcomes'] as $outcome ) {
				if ( $outcome['id'] === $row['outcome_id'] ) {
					$outcome_title = $outcome['title'];
				}
			}

			$responses = array();
			foreach ( $quiz['questions'] as $question ) {
				if ( isset( $map[ $question['id'] ] ) ) {
					$responses[] = array(
						'id'       => $question['id'],
						'question' => $question['text'],
						'answer'   => Maqsy_Quiz_Engine::answer_label( $question, $map[ $question['id'] ] ),
						'skipped'  => ! empty( $map[ $question['id'] ]['skipped'] ),
					);
				}
			}

			$items[] = array(
				'id'           => (int) $row['id'],
				'quiz_id'      => $id,
				'quiz_title'   => Maqsy_Quizzes::get_title( $id ),
				'status'       => self::display_status( $row ),
				'source'       => $row['source'],
				'name'         => $row['lead_name'],
				'email'        => $row['lead_email'],
				'phone'        => $row['lead_phone'],
				'outcome'      => $outcome_title,
				'cta_clicked'  => (bool) $row['cta_clicked'],
				'page_url'     => $row['page_url'],
				'started_at'   => gmdate( 'c', strtotime( $row['started_at'] . ' UTC' ) ),
				'completed_at' => $row['completed_at'] ? gmdate( 'c', strtotime( $row['completed_at'] . ' UTC' ) ) : null,
				'responses'    => $responses,
			);
		}

		return array(
			'items' => $items,
			'total' => $total,
		);
	}

	/**
	 * A session's status as shown in reports: completed, in_progress, dropped, or restarted.
	 *
	 * @since  1.2.0
	 * @param  array $row The session row.
	 * @return string
	 */
	private static function display_status( array $row ) {

		if ( in_array( $row['status'], array( 'completed', 'restarted' ), true ) ) {
			return $row['status'];
		}

		return strtotime( $row['updated_at'] . ' UTC' ) < time() - Maqsy_Quiz_Store::INACTIVE_AFTER ? 'dropped' : 'in_progress';
	}

	/**
	 * Resolve a day count into a UTC datetime range.
	 *
	 * @since  1.2.0
	 * @param  int $days Range in days, or 0 for all time.
	 * @return array { from: string|null, to: string }
	 */
	public static function range( $days ) {

		$days = max( 0, (int) $days );

		return array(
			// "Last N days" includes today, so it starts at midnight N-1 days ago.
			'from' => $days ? gmdate( 'Y-m-d 00:00:00', time() - ( $days - 1 ) * DAY_IN_SECONDS ) : null,
			'to'   => gmdate( 'Y-m-d H:i:s' ),
		);
	}

	/**
	 * Build a prepared WHERE fragment for a quiz and range.
	 *
	 * @since  1.2.0
	 * @param  int    $quiz_id Quiz ID, or 0 for all.
	 * @param  array  $range   From range().
	 * @param  string $column  The datetime column to filter on.
	 * @return string
	 */
	private static function where( $quiz_id, array $range, $column = 'started_at' ) {

		global $wpdb;

		$column = in_array( $column, array( 'started_at', 'created_at', 'completed_at' ), true ) ? $column : 'started_at';
		$parts  = array( '1=1' );

		if ( $quiz_id ) {
			$parts[] = $wpdb->prepare( 'quiz_id = %d', $quiz_id );
		}
		if ( $range['from'] ) {
			$parts[] = $wpdb->prepare( "$column >= %s", $range['from'] ); // phpcs:ignore WordPress.DB.PreparedSQL -- whitelisted column.
		}

		return implode( ' AND ', $parts );
	}
}
