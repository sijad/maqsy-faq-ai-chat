<?php
/**
 * Persistence for quiz sessions and analytics events.
 *
 * @since      1.2.0
 *
 * @package    Maqsy
 * @subpackage Maqsy/includes/quiz
 */

/**
 * Owns the plugin's two custom tables.
 *
 * - `maqsy_quiz_sessions`: one row per quiz attempt — its progress, answers,
 *   outcome, and any captured lead details.
 * - `maqsy_quiz_events`: an append-only log (start, answer, skip, pause,
 *   resume, restart, complete, cta_click, lead) the analytics dashboard
 *   aggregates over.
 *
 * Custom tables rather than post meta, because analytics need indexed range
 * queries and GROUP BYs over potentially hundreds of thousands of rows.
 *
 * @since      1.2.0
 * @package    Maqsy
 * @subpackage Maqsy/includes/quiz
 */
class Maqsy_Quiz_Store {

	/**
	 * Bump whenever the table schema changes; maybe_upgrade() reruns dbDelta().
	 *
	 * @since 1.2.0
	 * @var   string
	 */
	const DB_VERSION = '1';

	/**
	 * The option storing the installed schema version.
	 *
	 * @since 1.2.0
	 * @var   string
	 */
	const DB_VERSION_OPTION = 'maqsy_quiz_db_version';

	/**
	 * How long an unfinished session can be resumed.
	 *
	 * @since 1.2.0
	 * @var   int
	 */
	const SESSION_TTL = 30 * DAY_IN_SECONDS;

	/**
	 * Sessions untouched for this long, and not completed, count as drop-offs.
	 *
	 * @since 1.2.0
	 * @var   int
	 */
	const INACTIVE_AFTER = 30 * MINUTE_IN_SECONDS;

	/**
	 * The sessions table name.
	 *
	 * @since  1.2.0
	 * @return string
	 */
	public static function sessions_table() {
		global $wpdb;
		return $wpdb->prefix . 'maqsy_quiz_sessions';
	}

	/**
	 * The events table name.
	 *
	 * @since  1.2.0
	 * @return string
	 */
	public static function events_table() {
		global $wpdb;
		return $wpdb->prefix . 'maqsy_quiz_events';
	}

	/**
	 * Create or update the tables.
	 *
	 * @since 1.2.0
	 */
	public static function install() {

		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$sessions        = self::sessions_table();
		$events          = self::events_table();

		// dbDelta() is whitespace-sensitive: two spaces after PRIMARY KEY, one
		// field per line, and KEY rather than INDEX.
		dbDelta(
			"CREATE TABLE $sessions (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				token char(32) NOT NULL,
				quiz_id bigint(20) unsigned NOT NULL,
				status varchar(20) NOT NULL DEFAULT 'active',
				source varchar(20) NOT NULL DEFAULT 'manual',
				current_question varchar(64) NOT NULL DEFAULT '',
				answers longtext NULL,
				answered_count smallint(5) unsigned NOT NULL DEFAULT 0,
				outcome_id varchar(64) NOT NULL DEFAULT '',
				lead_name varchar(191) NOT NULL DEFAULT '',
				lead_email varchar(191) NOT NULL DEFAULT '',
				lead_phone varchar(64) NOT NULL DEFAULT '',
				cta_clicked tinyint(1) unsigned NOT NULL DEFAULT 0,
				page_url varchar(2048) NOT NULL DEFAULT '',
				user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				started_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				completed_at datetime NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY token (token),
				KEY quiz_started (quiz_id,started_at),
				KEY lead_email (lead_email)
			) $charset_collate;

			CREATE TABLE $events (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				session_id bigint(20) unsigned NOT NULL,
				quiz_id bigint(20) unsigned NOT NULL,
				type varchar(20) NOT NULL,
				question_id varchar(64) NOT NULL DEFAULT '',
				value text NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY quiz_type_created (quiz_id,type,created_at),
				KEY session_id (session_id)
			) $charset_collate;"
		);

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, true );
	}

	/**
	 * Install/upgrade the tables if the stored schema version is out of date.
	 *
	 * The activation hook does not run on plugin updates, so this also runs on
	 * every request's `init` (it is a single autoloaded option read).
	 *
	 * @since 1.2.0
	 */
	public static function maybe_upgrade() {
		if ( get_option( self::DB_VERSION_OPTION ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	/**
	 * Drop both tables (used on uninstall).
	 *
	 * @since 1.2.0
	 */
	public static function drop_tables() {

		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- table names cannot be placeholders.
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::sessions_table() );
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::events_table() );
		// phpcs:enable

		delete_option( self::DB_VERSION_OPTION );
	}

	/**
	 * Start a new session.
	 *
	 * @since  1.2.0
	 * @param  int    $quiz_id  The quiz post ID.
	 * @param  string $source   How the quiz was started: manual, suggested, link, or keyword.
	 * @param  string $page_url The page the visitor was on.
	 * @return array|WP_Error The session row.
	 */
	public static function create_session( $quiz_id, $source, $page_url ) {

		global $wpdb;

		$now   = current_time( 'mysql', true );
		$token = bin2hex( random_bytes( 16 ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$inserted = $wpdb->insert(
			self::sessions_table(),
			array(
				'token'      => $token,
				'quiz_id'    => (int) $quiz_id,
				'status'     => 'active',
				'source'     => $source,
				'answers'    => '[]',
				'page_url'   => mb_substr( $page_url, 0, 2048 ),
				'user_id'    => get_current_user_id(),
				'started_at' => $now,
				'updated_at' => $now,
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return new WP_Error( 'maqsy_quiz_db_error', __( 'Could not start the quiz. Please try again.', 'maqsy-faq-ai-chat' ), array( 'status' => 500 ) );
		}

		$session = self::get_session_by_token( $token );
		self::log_event( $session, 'start' );

		return $session;
	}

	/**
	 * Load a resumable session by its token.
	 *
	 * @since  1.2.0
	 * @param  string $token The session token.
	 * @return array|null The session with `answers` decoded, or null if unknown/expired.
	 */
	public static function get_session_by_token( $token ) {

		global $wpdb;

		if ( ! is_string( $token ) || ! preg_match( '/^[a-f0-9]{32}$/', $token ) ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::sessions_table() . ' WHERE token = %s', $token ), ARRAY_A );

		if ( ! $row || strtotime( $row['started_at'] . ' UTC' ) < time() - self::SESSION_TTL ) {
			return null;
		}

		$row['answers'] = json_decode( (string) $row['answers'], true );
		$row['answers'] = is_array( $row['answers'] ) ? $row['answers'] : array();

		return $row;
	}

	/**
	 * Update a session's columns (always bumps updated_at).
	 *
	 * @since 1.2.0
	 * @param array $session The session row.
	 * @param array $fields  Columns to update; `answers` may be passed as an array.
	 */
	public static function update_session( array $session, array $fields ) {

		global $wpdb;

		if ( isset( $fields['answers'] ) && is_array( $fields['answers'] ) ) {
			$fields['answers'] = wp_json_encode( $fields['answers'] );
		}
		$fields['updated_at'] = current_time( 'mysql', true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update( self::sessions_table(), $fields, array( 'id' => (int) $session['id'] ) );
	}

	/**
	 * Append an analytics event.
	 *
	 * @since 1.2.0
	 * @param array  $session     The session row.
	 * @param string $type        Event type.
	 * @param string $question_id Related question, if any.
	 * @param mixed  $value       Related value (JSON-encoded unless already a string).
	 */
	public static function log_event( array $session, $type, $question_id = '', $value = null ) {

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			self::events_table(),
			array(
				'session_id'  => (int) $session['id'],
				'quiz_id'     => (int) $session['quiz_id'],
				'type'        => $type,
				'question_id' => (string) $question_id,
				'value'       => null === $value || is_string( $value ) ? $value : wp_json_encode( $value ),
				'created_at'  => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Delete all analytics data for a quiz (when the quiz itself is deleted).
	 *
	 * @since 1.2.0
	 * @param int $quiz_id The quiz post ID.
	 */
	public static function delete_quiz_data( $quiz_id ) {

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( self::events_table(), array( 'quiz_id' => (int) $quiz_id ), array( '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( self::sessions_table(), array( 'quiz_id' => (int) $quiz_id ), array( '%d' ) );
	}
}
