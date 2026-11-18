<?php
/**
 * The quiz post type and quiz lookups.
 *
 * @since      1.2.0
 *
 * @package    Maqsy
 * @subpackage Maqsy/includes/quiz
 */

/**
 * Registers the `maqsy_quiz` post type and provides access to quiz definitions.
 *
 * A quiz is a private post: its title is the quiz name, its post status is the
 * draft/published state, and its full definition (see Maqsy_Quiz_Schema) is a
 * JSON blob in the `_maqsy_quiz` meta key.
 *
 * @since      1.2.0
 * @package    Maqsy
 * @subpackage Maqsy/includes/quiz
 */
class Maqsy_Quizzes {

	/**
	 * The quiz post type.
	 *
	 * @since 1.2.0
	 * @var   string
	 */
	const POST_TYPE = 'maqsy_quiz';

	/**
	 * The meta key holding the quiz definition JSON.
	 *
	 * @since 1.2.0
	 * @var   string
	 */
	const META_KEY = '_maqsy_quiz';

	/**
	 * The transient caching get_all_published(), suffixed with the plugin
	 * version so an update never serves a definition in an older shape.
	 *
	 * @since 1.2.0
	 * @var   string
	 */
	const CACHE_KEY = 'maqsy_published_quizzes';

	/**
	 * Register the post type and shortcode.
	 *
	 * @since 1.2.0
	 */
	public function init() {

		register_post_type(
			self::POST_TYPE,
			array(
				'label'               => __( 'Quizzes', 'maqsy-faq-ai-chat' ),
				'labels'              => array(
					'name'               => _x( 'Quizzes', 'Post Type General Name', 'maqsy-faq-ai-chat' ),
					'singular_name'      => _x( 'Quiz', 'Post Type Singular Name', 'maqsy-faq-ai-chat' ),
					'menu_name'          => __( 'Quizzes', 'maqsy-faq-ai-chat' ),
					'all_items'          => __( 'Quizzes', 'maqsy-faq-ai-chat' ),
					'add_new_item'       => __( 'Add New Quiz', 'maqsy-faq-ai-chat' ),
					'add_new'            => __( 'Add New Quiz', 'maqsy-faq-ai-chat' ),
					'new_item'           => __( 'New Quiz', 'maqsy-faq-ai-chat' ),
					'edit_item'          => __( 'Edit Quiz', 'maqsy-faq-ai-chat' ),
					'update_item'        => __( 'Update Quiz', 'maqsy-faq-ai-chat' ),
					'search_items'       => __( 'Search Quizzes', 'maqsy-faq-ai-chat' ),
					'not_found'          => __( 'No quizzes found', 'maqsy-faq-ai-chat' ),
					'not_found_in_trash' => __( 'No quizzes found in Trash', 'maqsy-faq-ai-chat' ),
					'items_list'         => __( 'Quizzes list', 'maqsy-faq-ai-chat' ),
				),
				'supports'            => array( 'title' ),
				'hierarchical'        => false,
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => 'edit.php?post_type=maqsy',
				'show_in_admin_bar'   => false,
				'show_in_nav_menus'   => false,
				'can_export'          => true,
				'has_archive'         => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'capability_type'     => 'post',
				'show_in_rest'        => false,
			)
		);

		add_shortcode( 'maqsy_quiz', array( $this, 'quiz_shortcode' ) );
	}

	/**
	 * Get a quiz's sanitized definition, regardless of its status.
	 *
	 * @since  1.2.0
	 * @param  int $quiz_id The quiz post ID.
	 * @return array
	 */
	public static function get_definition( $quiz_id ) {

		$raw = get_post_meta( (int) $quiz_id, self::META_KEY, true );

		return Maqsy_Quiz_Schema::sanitize( $raw ? json_decode( $raw, true ) : array() );
	}

	/**
	 * Save a quiz's definition (sanitizing it first).
	 *
	 * @since  1.2.0
	 * @param  int   $quiz_id The quiz post ID.
	 * @param  mixed $raw     The raw definition.
	 * @return array The sanitized, stored definition.
	 */
	public static function save_definition( $quiz_id, $raw ) {

		$quiz = Maqsy_Quiz_Schema::sanitize( $raw );

		// wp_slash() for the same reason as FAQ questions: update_post_meta()
		// unslashes, which would corrupt the JSON's own escape sequences.
		update_post_meta( (int) $quiz_id, self::META_KEY, wp_slash( wp_json_encode( $quiz ) ) );
		self::flush_cache();

		return $quiz;
	}

	/**
	 * A quiz's title as plain text.
	 *
	 * The get_the_title() output is texturized and entity-encoded, which suits HTML but
	 * shows up literally in the widget, emails, and CSV exports.
	 *
	 * @since  1.2.0
	 * @param  int|WP_Post $post The quiz post or its ID.
	 * @return string
	 */
	public static function get_title( $post ) {
		return html_entity_decode( get_the_title( $post ), ENT_QUOTES, get_bloginfo( 'charset' ) );
	}

	/**
	 * Drop the cached published quizzes.
	 *
	 * Hooked to clean_post_cache, which fires whenever a post is updated,
	 * trashed, or deleted.
	 *
	 * @since 1.2.0
	 * @param int          $post_id Unused.
	 * @param WP_Post|null $post    The post whose cache was cleaned.
	 */
	public static function flush_cache( $post_id = 0, $post = null ) {

		if ( $post && self::POST_TYPE !== $post->post_type ) {
			return;
		}

		delete_transient( self::CACHE_KEY . '_' . MAQSY_VERSION );
	}

	/**
	 * Get a published, runnable quiz with its post data.
	 *
	 * @since  1.2.0
	 * @param  int $quiz_id The quiz post ID.
	 * @return array|null { id, title, quiz } or null if unavailable.
	 */
	public static function get_published( $quiz_id ) {

		$post = get_post( (int) $quiz_id );

		if ( ! $post || self::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
			return null;
		}

		$quiz = self::get_definition( $post->ID );
		if ( ! $quiz['questions'] ) {
			return null;
		}

		return array(
			'id'    => $post->ID,
			'title' => self::get_title( $post ),
			'quiz'  => $quiz,
		);
	}

	/**
	 * All published, runnable quizzes.
	 *
	 * @since  1.2.0
	 * @return array[] Each { id, title, quiz }.
	 */
	public static function get_all_published() {

		static $cache = null;

		if ( null !== $cache ) {
			return $cache;
		}

		// The chat widget's quiz menu needs this on every front-end page, so
		// keep it between requests until a quiz changes (see flush_cache()).
		// It expires rather than living forever so it isn't autoloaded.
		$cache = get_transient( self::CACHE_KEY . '_' . MAQSY_VERSION );
		if ( is_array( $cache ) ) {
			return $cache;
		}

		// Full posts rather than IDs, so get_posts() primes the post and meta
		// caches in two queries and get_published() below needs none.
		$posts = get_posts(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'publish',
				'numberposts' => 100,
				'orderby'     => 'menu_order title',
				'order'       => 'ASC',
			)
		);
		$cache = array();
		foreach ( $posts as $post ) {
			$published = self::get_published( $post->ID );
			if ( $published ) {
				$cache[] = $published;
			}
		}

		set_transient( self::CACHE_KEY . '_' . MAQSY_VERSION, $cache, DAY_IN_SECONDS );

		return $cache;
	}

	/**
	 * Quizzes offered in the chat widget's quiz menu, in the shape the widget expects.
	 *
	 * @since  1.2.0
	 * @return array[] Each { id, title, description }.
	 */
	public static function get_menu_quizzes() {

		$items = array();
		foreach ( self::get_all_published() as $published ) {
			if ( $published['quiz']['show_in_menu'] ) {
				$items[] = self::summary( $published );
			}
		}

		return $items;
	}

	/**
	 * The short, visitor-facing description of a published quiz.
	 *
	 * @since  1.2.0
	 * @param  array $published { id, title, quiz }.
	 * @return array { id, title, description, startLabel }
	 */
	public static function summary( array $published ) {
		return array(
			'id'          => $published['id'],
			'title'       => $published['title'],
			'description' => $published['quiz']['description'],
			'startLabel'  => $published['quiz']['start_label'],
		);
	}

	/**
	 * The published quiz whose chat keyword is the whole of a visitor's message.
	 *
	 * @since  1.2.0
	 * @param  string $message The visitor's chat message.
	 * @return array|null { id, title, description, startLabel }, or null when nothing matches.
	 */
	public static function match_keyword( $message ) {

		$message = Maqsy_Quiz_Schema::normalize_keyword( $message );
		if ( '' === $message || mb_strlen( $message ) > 50 ) {
			return null;
		}

		foreach ( self::get_all_published() as $published ) {
			if ( in_array( $message, $published['quiz']['keywords'], true ) ) {
				return self::summary( $published );
			}
		}

		return null;
	}

	/**
	 * The system prompt section telling the AI which quizzes it may suggest.
	 *
	 * @since  1.2.0
	 * @return string Empty when no quiz allows suggestions.
	 */
	public static function get_suggestion_prompt() {

		$lines = array();
		foreach ( self::get_all_published() as $published ) {
			if ( $published['quiz']['suggest'] ) {
				$description = $published['quiz']['description'] ? ' — ' . $published['quiz']['description'] : '';
				$lines[]     = sprintf( '- [[quiz:%d]] %s%s', $published['id'], $published['title'], str_replace( "\n", ' ', $description ) );
			}
		}

		if ( ! $lines ) {
			return '';
		}

		return __( 'Interactive quizzes: the site offers the short quizzes listed below, which ask the visitor a few questions and then recommend the most suitable option for them. When the visitor is trying to decide what is right for them, is unsure which option fits their needs, or asks for a personalized recommendation, briefly answer and then invite them to take the most relevant quiz by putting its exact token (for example [[quiz:12]]) on its own line at the end of your reply. The token is shown to the visitor as a button that starts the quiz, so never explain or describe it. Suggest at most one quiz per reply, only when it is genuinely relevant, and do not suggest a quiz again if you already did earlier in the conversation.', 'maqsy-faq-ai-chat' )
			. "\n" . implode( "\n", $lines );
	}

	/**
	 * Pull a quiz suggestion token out of an AI reply.
	 *
	 * @since  1.2.0
	 * @param  string $reply The AI reply.
	 * @return array { 'reply' => string, 'quiz' => array|null }
	 */
	public static function extract_suggestion( $reply ) {

		$suggested = null;

		if ( preg_match_all( '/\[\[\s*quiz\s*:\s*(\d+)\s*\]\]/i', $reply, $matches ) ) {
			foreach ( $matches[1] as $id ) {
				$published = self::get_published( (int) $id );
				if ( $published && $published['quiz']['suggest'] ) {
					$suggested = self::summary( $published );
					break;
				}
			}
			$reply = trim( preg_replace( '/[ \t]*\[\[\s*quiz\s*:\s*\d+\s*\]\][ \t]*/i', '', $reply ) );
		}

		return array(
			'reply' => $reply,
			'quiz'  => $suggested,
		);
	}

	/**
	 * Render [maqsy_quiz id="123" label="Find your plan"] as a button that opens
	 * the chat widget and starts the quiz.
	 *
	 * @since  1.2.0
	 * @param  array $atts Shortcode attributes: id, label.
	 * @return string
	 */
	public function quiz_shortcode( $atts ) {

		$atts = shortcode_atts(
			array(
				'id'    => 0,
				'label' => '',
			),
			$atts,
			'maqsy_quiz'
		);

		$settings  = Maqsy_Settings::get_settings();
		$published = self::get_published( (int) $atts['id'] );

		if ( ! $published || empty( $settings['enabled'] ) || empty( $settings['api_key'] ) ) {
			return '';
		}

		$label = '' !== $atts['label'] ? $atts['label'] : $published['title'];

		return sprintf(
			'<a class="maqsy-quiz-link wp-element-button" href="#maqsy-quiz-%1$d" data-maqsy-quiz="%1$d">%2$s</a>',
			(int) $published['id'],
			esc_html( $label )
		);
	}

	/**
	 * Run completion side effects: lead notification email and integration hooks.
	 *
	 * @since 1.2.0
	 * @param array $published { id, title, quiz }.
	 * @param array $session   The completed session row.
	 * @param array $result    The visitor-facing result payload.
	 */
	public static function on_completed( array $published, array $session, array $result ) {

		$quiz = $published['quiz'];
		$lead = Maqsy_Quiz_Engine::lead_details( $quiz, $session['answers'] );

		if ( $quiz['notify_email'] && ( $lead['email'] || $lead['phone'] || $lead['name'] ) ) {
			$lines = array(
				/* translators: %s: quiz title. */
				sprintf( __( 'A visitor completed the quiz "%s".', 'maqsy-faq-ai-chat' ), $published['title'] ),
				'',
			);
			foreach ( array(
				'name'  => __( 'Name', 'maqsy-faq-ai-chat' ),
				'email' => __( 'Email', 'maqsy-faq-ai-chat' ),
				'phone' => __( 'Phone', 'maqsy-faq-ai-chat' ),
			) as $field => $label ) {
				if ( $lead[ $field ] ) {
					$lines[] = $label . ': ' . $lead[ $field ];
				}
			}
			$lines[] = __( 'Result', 'maqsy-faq-ai-chat' ) . ': ' . $result['title'];
			$lines[] = '';
			$map     = Maqsy_Quiz_Engine::answer_map( $session['answers'] );
			foreach ( $quiz['questions'] as $question ) {
				if ( isset( $map[ $question['id'] ] ) ) {
					$answer  = Maqsy_Quiz_Engine::answer_label( $question, $map[ $question['id'] ] );
					$lines[] = $question['text'] . "\n  " . ( '' !== $answer ? $answer : __( '(skipped)', 'maqsy-faq-ai-chat' ) );
				}
			}
			if ( $session['page_url'] ) {
				$lines[] = '';
				$lines[] = __( 'Started on', 'maqsy-faq-ai-chat' ) . ': ' . $session['page_url'];
			}

			$headers = $lead['email'] ? array( 'Reply-To: ' . ( $lead['name'] ? str_replace( array( "\r", "\n", '<', '>', ',' ), '', $lead['name'] ) . ' ' : '' ) . '<' . $lead['email'] . '>' ) : array();

			wp_mail(
				$quiz['notify_email'],
				/* translators: 1: site name, 2: quiz title. */
				sprintf( __( '[%1$s] New quiz lead: %2$s', 'maqsy-faq-ai-chat' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), $published['title'] ),
				implode( "\n", $lines ),
				$headers
			);
		}

		/**
		 * Fires when a visitor completes a quiz.
		 *
		 * Use it to push leads into a CRM or newsletter tool.
		 *
		 * @since 1.2.0
		 * @param array $session   The session row (with decoded answers).
		 * @param array $result    The visitor-facing result.
		 * @param array $lead      { name, email, phone }.
		 * @param array $published { id, title, quiz }.
		 */
		do_action( 'maqsy_quiz_completed', $session, $result, $lead, $published );
	}
}
