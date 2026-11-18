<?php
/**
 * REST API endpoints for quizzes.
 *
 * @since      1.2.0
 *
 * @package    Maqsy
 * @subpackage Maqsy/includes/quiz
 */

/**
 * Registers the quiz REST routes.
 *
 * Visitor routes (start/answer/pause/resume/restart/cta) are public and
 * authenticate a session by its unguessable token. The server is the single
 * source of truth for quiz progress: the client only ever answers the
 * question the server says is current, so analytics can't be skewed by a
 * modified client and scores/branches/outcomes never leave the server.
 *
 * Every visitor route responds with the same "state" shape (see state()).
 *
 * @since      1.2.0
 * @package    Maqsy
 * @subpackage Maqsy/includes/quiz
 */
class Maqsy_Quiz_Rest_Controller {

	/**
	 * Register all routes.
	 *
	 * @since 1.2.0
	 */
	public function register_routes() {

		$namespace = Maqsy_Rest_Controller::NAMESPACE_V1;
		$token_arg = array(
			'token' => array(
				'required' => true,
				'type'     => 'string',
				'pattern'  => '^[a-f0-9]{32}$',
			),
		);

		register_rest_route(
			$namespace,
			'/quiz/start',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'start' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'quiz_id'  => array(
						'required' => true,
						'type'     => 'integer',
						'minimum'  => 1,
					),
					'source'   => array(
						'type'    => 'string',
						'enum'    => array( 'manual', 'suggested', 'link', 'keyword' ),
						'default' => 'manual',
					),
					'page_url' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'esc_url_raw',
					),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/quiz/answer',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'answer' ),
				'permission_callback' => '__return_true',
				'args'                => array_merge(
					$token_arg,
					array(
						'question_id' => array(
							'required' => true,
							'type'     => 'string',
						),
						'value'       => array(
							'type' => array( 'string', 'array' ),
						),
						'skip'        => array(
							'type'    => 'boolean',
							'default' => false,
						),
					)
				),
			)
		);

		foreach ( array( 'state', 'pause', 'resume', 'restart' ) as $action ) {
			register_rest_route(
				$namespace,
				'/quiz/' . $action,
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'state' === $action ? 'get_state' : $action ),
					'permission_callback' => '__return_true',
					'args'                => $token_arg,
				)
			);
		}

		register_rest_route(
			$namespace,
			'/quiz/cta',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'cta' ),
				'permission_callback' => '__return_true',
				'args'                => array_merge(
					$token_arg,
					array(
						'url'  => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'esc_url_raw',
						),
						'kind' => array(
							'type'    => 'string',
							'enum'    => array( 'cta', 'recommendation', 'redirect' ),
							'default' => 'cta',
						),
					)
				),
			)
		);

		register_rest_route(
			$namespace,
			'/quiz/preview',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'preview' ),
				'permission_callback' => array( 'Maqsy_Rest_Controller', 'editor_permissions_check' ),
				'args'                => array(
					'quiz'    => array(
						'required' => true,
						'type'     => 'object',
					),
					'title'   => array(
						'type'    => 'string',
						'default' => '',
					),
					'answers' => array(
						'type'    => 'array',
						'default' => array(),
						'items'   => array( 'type' => 'object' ),
					),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/quiz/generate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'generate' ),
				'permission_callback' => array( 'Maqsy_Rest_Controller', 'admin_permissions_check' ),
				'args'                => array(
					'goal'         => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'audience'     => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'context'      => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'questions'    => array(
						'type'    => 'integer',
						'default' => 6,
						'minimum' => 3,
						'maximum' => 12,
					),
					'capture_lead' => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'use_faqs'     => array(
						'type'    => 'boolean',
						'default' => true,
					),
				),
			)
		);

		$report_args = array(
			'quiz' => array(
				'type'    => 'integer',
				'default' => 0,
				'minimum' => 0,
			),
			'days' => array(
				'type'    => 'integer',
				'default' => 30,
				'minimum' => 0,
				'maximum' => 3650,
			),
		);

		register_rest_route(
			$namespace,
			'/quiz/analytics',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'analytics' ),
				'permission_callback' => array( 'Maqsy_Rest_Controller', 'admin_permissions_check' ),
				'args'                => $report_args,
			)
		);

		register_rest_route(
			$namespace,
			'/quiz/sessions',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'sessions' ),
				'permission_callback' => array( 'Maqsy_Rest_Controller', 'admin_permissions_check' ),
				'args'                => array_merge(
					$report_args,
					array(
						'leads' => array(
							'type'    => 'boolean',
							'default' => false,
						),
						'page'  => array(
							'type'    => 'integer',
							'default' => 1,
							'minimum' => 1,
						),
					)
				),
			)
		);
	}

	/**
	 * Start a quiz session.
	 *
	 * @since  1.2.0
	 * @param  WP_REST_Request $request The request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function start( WP_REST_Request $request ) {

		$limited = self::rate_limit( 'start', 20, 10 * MINUTE_IN_SECONDS );
		if ( is_wp_error( $limited ) ) {
			return $limited;
		}

		$published = Maqsy_Quizzes::get_published( (int) $request->get_param( 'quiz_id' ) );
		if ( ! $published ) {
			return self::not_found();
		}

		$session = Maqsy_Quiz_Store::create_session( $published['id'], (string) $request->get_param( 'source' ), (string) $request->get_param( 'page_url' ) );
		if ( is_wp_error( $session ) ) {
			return $session;
		}

		return rest_ensure_response( $this->step( $published, $session, true ) );
	}

	/**
	 * Answer (or skip) the current question.
	 *
	 * @since  1.2.0
	 * @param  WP_REST_Request $request The request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function answer( WP_REST_Request $request ) {

		$context = $this->load( $request );
		if ( is_wp_error( $context ) ) {
			return $context;
		}
		list( $published, $session ) = $context;

		$quiz        = $published['quiz'];
		$question_id = (string) $request->get_param( 'question_id' );

		// The expected question comes from the engine, the same as in state(),
		// rather than the stored current_question, so the two can't disagree
		// after the quiz is edited mid-session.
		$step = Maqsy_Quiz_Engine::advance( $quiz, $session['answers'] );

		if ( 'completed' === $session['status'] || $step['done'] || $question_id !== $step['question']['id'] ) {
			// A double-submit, a stale tab, or an edited quiz: let the client
			// resync from the server's state instead of recording the answer.
			return new WP_Error(
				'maqsy_quiz_out_of_sync',
				__( 'This question was already answered.', 'maqsy-faq-ai-chat' ),
				array(
					'status' => 409,
					'state'  => $this->resync( $published, $session ),
				)
			);
		}

		$question = Maqsy_Quiz_Engine::find_question( $quiz, $question_id );
		if ( ! $question ) {
			return self::not_found();
		}

		if ( $request->get_param( 'skip' ) ) {
			if ( $question['required'] ) {
				return new WP_Error( 'maqsy_quiz_required', __( 'This question can’t be skipped.', 'maqsy-faq-ai-chat' ), array( 'status' => 400 ) );
			}
			$entry = array(
				'question' => $question_id,
				'value'    => null,
				'skipped'  => true,
			);
			Maqsy_Quiz_Store::log_event( $session, 'skip', $question_id );
		} else {
			$value = Maqsy_Quiz_Engine::validate_answer( $question, $request->get_param( 'value' ) );
			if ( is_wp_error( $value ) ) {
				$value->add_data( array( 'status' => 400 ) );
				return $value;
			}
			$entry = array(
				'question' => $question_id,
				'value'    => $value,
				'skipped'  => false,
			);
			Maqsy_Quiz_Store::log_event( $session, 'answer', $question_id, wp_json_encode( $value ) );
		}

		$session['answers'][] = $entry;
		$fields               = array(
			'answers'        => $session['answers'],
			'answered_count' => count( $session['answers'] ),
			'status'         => 'active',
		);

		if ( $question['lead_field'] && ! $entry['skipped'] ) {
			$had_lead                                    = '' !== $session['lead_name'] . $session['lead_email'] . $session['lead_phone'];
			$fields[ 'lead_' . $question['lead_field'] ] = mb_substr( (string) $entry['value'], 0, 'phone' === $question['lead_field'] ? 64 : 191 );
			if ( ! $had_lead ) {
				Maqsy_Quiz_Store::log_event( $session, 'lead', $question_id );
			}
		}

		$session               = array_merge( $session, $fields );
		$state                 = $this->step( $published, $session, false, $fields );
		$state['answer_label'] = Maqsy_Quiz_Engine::answer_label( $question, $entry );

		return rest_ensure_response( $state );
	}

	/**
	 * Fetch a session's current state without changing it (used to resync a
	 * session restored from the visitor's browser storage).
	 *
	 * @since  1.2.0
	 * @param  WP_REST_Request $request The request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_state( WP_REST_Request $request ) {

		$context = $this->load( $request );
		if ( is_wp_error( $context ) ) {
			return $context;
		}
		list( $published, $session ) = $context;

		return rest_ensure_response( $this->resync( $published, $session ) );
	}

	/**
	 * Pause a session (the visitor went back to free chat).
	 *
	 * @since  1.2.0
	 * @param  WP_REST_Request $request The request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function pause( WP_REST_Request $request ) {

		$context = $this->load( $request );
		if ( is_wp_error( $context ) ) {
			return $context;
		}
		list( $published, $session ) = $context;

		if ( 'active' === $session['status'] ) {
			Maqsy_Quiz_Store::update_session( $session, array( 'status' => 'paused' ) );
			Maqsy_Quiz_Store::log_event( $session, 'pause', $session['current_question'] );
			$session['status'] = 'paused';
		}

		return rest_ensure_response( $this->state( $published, $session ) );
	}

	/**
	 * Resume a paused session.
	 *
	 * @since  1.2.0
	 * @param  WP_REST_Request $request The request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function resume( WP_REST_Request $request ) {

		$context = $this->load( $request );
		if ( is_wp_error( $context ) ) {
			return $context;
		}
		list( $published, $session ) = $context;

		if ( 'paused' === $session['status'] ) {
			Maqsy_Quiz_Store::update_session( $session, array( 'status' => 'active' ) );
			Maqsy_Quiz_Store::log_event( $session, 'resume', $session['current_question'] );
			$session['status'] = 'active';
		}

		return rest_ensure_response( $this->state( $published, $session ) );
	}

	/**
	 * Restart: retire the session and start a fresh one for the same quiz.
	 *
	 * @since  1.2.0
	 * @param  WP_REST_Request $request The request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function restart( WP_REST_Request $request ) {

		$limited = self::rate_limit( 'start', 20, 10 * MINUTE_IN_SECONDS );
		if ( is_wp_error( $limited ) ) {
			return $limited;
		}

		$context = $this->load( $request );
		if ( is_wp_error( $context ) ) {
			return $context;
		}
		list( $published, $session ) = $context;

		if ( ! $published['quiz']['allow_restart'] ) {
			return new WP_Error( 'maqsy_quiz_no_restart', __( 'This quiz can’t be restarted.', 'maqsy-faq-ai-chat' ), array( 'status' => 400 ) );
		}

		Maqsy_Quiz_Store::log_event( $session, 'restart', $session['current_question'] );
		if ( 'completed' !== $session['status'] ) {
			Maqsy_Quiz_Store::update_session( $session, array( 'status' => 'restarted' ) );
		}

		$fresh = Maqsy_Quiz_Store::create_session( $published['id'], $session['source'], $session['page_url'] );
		if ( is_wp_error( $fresh ) ) {
			return $fresh;
		}

		// Keep contact details given in an abandoned attempt, so the lead
		// survives the restart (analytics skip leads on restarted sessions;
		// a completed session isn't marked restarted and keeps its own).
		$lead = 'completed' === $session['status'] ? array() : array_filter(
			array(
				'lead_name'  => $session['lead_name'],
				'lead_email' => $session['lead_email'],
				'lead_phone' => $session['lead_phone'],
			),
			'strlen'
		);
		if ( $lead ) {
			Maqsy_Quiz_Store::update_session( $fresh, $lead );
			$fresh = array_merge( $fresh, $lead );
		}

		return rest_ensure_response( $this->step( $published, $fresh, true ) );
	}

	/**
	 * Record a click on a result's CTA, recommendation link, or redirect.
	 *
	 * @since  1.2.0
	 * @param  WP_REST_Request $request The request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function cta( WP_REST_Request $request ) {

		$context = $this->load( $request );
		if ( is_wp_error( $context ) ) {
			return $context;
		}
		list( , $session ) = $context;

		if ( 'completed' !== $session['status'] ) {
			return new WP_Error( 'maqsy_quiz_not_completed', __( 'This quiz has not been completed yet.', 'maqsy-faq-ai-chat' ), array( 'status' => 400 ) );
		}

		Maqsy_Quiz_Store::log_event(
			$session,
			'cta_click',
			'',
			wp_json_encode(
				array(
					'kind'    => (string) $request->get_param( 'kind' ),
					'url'     => (string) $request->get_param( 'url' ),
					'outcome' => $session['outcome_id'],
				)
			)
		);
		if ( ! (int) $session['cta_clicked'] ) {
			Maqsy_Quiz_Store::update_session( $session, array( 'cta_clicked' => 1 ) );
		}

		return rest_ensure_response( array( 'ok' => true ) );
	}

	/**
	 * Run an (unsaved) quiz definition statelessly for the builder's preview.
	 *
	 * @since  1.2.0
	 * @param  WP_REST_Request $request The request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function preview( WP_REST_Request $request ) {

		$quiz = Maqsy_Quiz_Schema::sanitize( $request->get_param( 'quiz' ) );

		// Replay the answers through the engine so the preview follows exactly
		// the path a visitor would, discarding anything that's no longer valid
		// after the admin edited the quiz mid-preview.
		$answers = array();
		foreach ( (array) $request->get_param( 'answers' ) as $raw ) {
			$step = Maqsy_Quiz_Engine::advance( $quiz, $answers );
			if ( $step['done'] || ! is_array( $raw ) || ! isset( $raw['question'] ) || $raw['question'] !== $step['question']['id'] ) {
				break;
			}
			if ( ! empty( $raw['skipped'] ) && ! $step['question']['required'] ) {
				$answers[] = array(
					'question' => $raw['question'],
					'value'    => null,
					'skipped'  => true,
				);
				continue;
			}
			$value = Maqsy_Quiz_Engine::validate_answer( $step['question'], isset( $raw['value'] ) ? $raw['value'] : null );
			if ( is_wp_error( $value ) ) {
				$value->add_data( array( 'status' => 400 ) );
				return $value;
			}
			$answers[] = array(
				'question' => $raw['question'],
				'value'    => $value,
				'skipped'  => false,
			);
		}

		$step     = Maqsy_Quiz_Engine::advance( $quiz, $answers );
		$response = array(
			'accepted' => count( $answers ),
			'question' => $step['done'] ? null : Maqsy_Quiz_Engine::public_question( $step['question'] ),
			'progress' => array(
				'answered' => $step['answered'],
				'total'    => $step['answered'] + $step['remaining'],
			),
			'result'   => null,
			'debug'    => array(
				'scores'  => (object) Maqsy_Quiz_Engine::score( $quiz, Maqsy_Quiz_Engine::answer_map( $answers ) ),
				'outcome' => '',
			),
		);

		if ( $step['done'] ) {
			$outcome                      = Maqsy_Quiz_Engine::determine_outcome( $quiz, $answers );
			$response['result']           = Maqsy_Quiz_Engine::build_result( $quiz, $outcome, $answers );
			$response['debug']['outcome'] = $outcome ? $outcome['id'] : '';
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Generate a quiz draft with AI.
	 *
	 * @since  1.2.0
	 * @param  WP_REST_Request $request The request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function generate( WP_REST_Request $request ) {

		$result = Maqsy_Quiz_Generator::generate(
			array(
				'goal'         => (string) $request->get_param( 'goal' ),
				'audience'     => (string) $request->get_param( 'audience' ),
				'context'      => (string) $request->get_param( 'context' ),
				'questions'    => (int) $request->get_param( 'questions' ),
				'capture_lead' => (bool) $request->get_param( 'capture_lead' ),
				'use_faqs'     => (bool) $request->get_param( 'use_faqs' ),
			)
		);

		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	/**
	 * The analytics dashboard report.
	 *
	 * @since  1.2.0
	 * @param  WP_REST_Request $request The request.
	 * @return WP_REST_Response
	 */
	public function analytics( WP_REST_Request $request ) {
		return rest_ensure_response( Maqsy_Quiz_Analytics::get_report( (int) $request->get_param( 'quiz' ), (int) $request->get_param( 'days' ) ) );
	}

	/**
	 * A page of sessions / leads.
	 *
	 * @since  1.2.0
	 * @param  WP_REST_Request $request The request.
	 * @return WP_REST_Response
	 */
	public function sessions( WP_REST_Request $request ) {

		$result   = Maqsy_Quiz_Analytics::get_sessions(
			(int) $request->get_param( 'quiz' ),
			(int) $request->get_param( 'days' ),
			(bool) $request->get_param( 'leads' ),
			(int) $request->get_param( 'page' )
		);
		$response = rest_ensure_response( $result['items'] );
		$response->header( 'X-WP-Total', (string) $result['total'] );
		$response->header( 'X-WP-TotalPages', (string) max( 1, (int) ceil( $result['total'] / Maqsy_Quiz_Analytics::SESSIONS_PER_PAGE ) ) );

		return $response;
	}

	/**
	 * Advance a session after a start or an answer, persisting progress and
	 * finishing it when the engine says the quiz is done.
	 *
	 * @since  1.2.0
	 * @param  array $published { id, title, quiz }.
	 * @param  array $session   The session row (answers decoded).
	 * @param  bool  $is_start  Whether this is the first step (adds the intro).
	 * @param  array $fields    Pending session column updates to save along with progress.
	 * @return array The state payload.
	 */
	private function step( array $published, array $session, $is_start, array $fields = array() ) {

		$quiz = $published['quiz'];
		$step = Maqsy_Quiz_Engine::advance( $quiz, $session['answers'] );

		$result = null;
		if ( $step['done'] ) {
			$outcome = Maqsy_Quiz_Engine::determine_outcome( $quiz, $session['answers'] );
			$result  = Maqsy_Quiz_Engine::build_result( $quiz, $outcome, $session['answers'] );

			$fields['status']           = 'completed';
			$fields['current_question'] = '';
			$fields['outcome_id']       = $result['id'];
			$fields['completed_at']     = current_time( 'mysql', true );
		} else {
			$fields['current_question'] = $step['question']['id'];
		}

		Maqsy_Quiz_Store::update_session( $session, $fields );
		$session = array_merge( $session, $fields );

		if ( $result ) {
			Maqsy_Quiz_Store::log_event( $session, 'complete', '', $result['id'] );

			Maqsy_Quizzes::on_completed( $published, $session, $result );
		}

		$state = $this->state( $published, $session, $step );
		if ( $is_start ) {
			$state['intro'] = $quiz['intro'];
		}
		if ( $result ) {
			$state['result'] = $result;
		}

		return $state;
	}

	/**
	 * The state of an unfinished session after reconciling it with the quiz
	 * as currently published: finishes it if an edit left no questions to
	 * ask, and keeps the stored current_question (used for drop-off
	 * reporting) in step with the engine.
	 *
	 * @since  1.2.0
	 * @param  array $published { id, title, quiz }.
	 * @param  array $session   The session row.
	 * @return array The state payload.
	 */
	private function resync( array $published, array $session ) {

		if ( ! in_array( $session['status'], array( 'active', 'paused' ), true ) ) {
			return $this->state( $published, $session );
		}

		$step = Maqsy_Quiz_Engine::advance( $published['quiz'], $session['answers'] );
		if ( $step['done'] ) {
			return $this->step( $published, $session, false );
		}

		if ( $step['question']['id'] !== $session['current_question'] ) {
			$fields = array( 'current_question' => $step['question']['id'] );
			Maqsy_Quiz_Store::update_session( $session, $fields );
			$session = array_merge( $session, $fields );
		}

		return $this->state( $published, $session, $step );
	}

	/**
	 * The state payload every visitor route returns.
	 *
	 * @since  1.2.0
	 * @param  array      $published { id, title, quiz }.
	 * @param  array      $session   The session row.
	 * @param  array|null $step      A precomputed Maqsy_Quiz_Engine::advance() result.
	 * @return array
	 */
	private function state( array $published, array $session, $step = null ) {

		$quiz = $published['quiz'];
		$step = $step ? $step : Maqsy_Quiz_Engine::advance( $quiz, $session['answers'] );

		$state = array(
			'token'    => $session['token'],
			'quiz'     => array(
				'id'           => $published['id'],
				'title'        => $published['title'],
				'allowRestart' => (bool) $quiz['allow_restart'],
			),
			'status'   => $session['status'],
			'question' => 'completed' === $session['status'] || $step['done'] ? null : Maqsy_Quiz_Engine::public_question( $step['question'] ),
			'progress' => array(
				'answered' => $step['answered'],
				'total'    => $step['answered'] + $step['remaining'],
			),
			'result'   => null,
		);

		if ( 'completed' === $session['status'] && ! $step['done'] ) {
			// Edited after completion: report the stored outcome, not a new path.
			$state['progress']['total'] = $step['answered'];
		}

		if ( 'completed' === $session['status'] ) {
			$outcome = null;
			foreach ( $quiz['outcomes'] as $candidate ) {
				if ( $candidate['id'] === $session['outcome_id'] ) {
					$outcome = $candidate;
				}
			}
			$state['result'] = Maqsy_Quiz_Engine::build_result( $quiz, $outcome, $session['answers'] );
		}

		return $state;
	}

	/**
	 * Load and validate the published quiz and session for a token request.
	 *
	 * @since  1.2.0
	 * @param  WP_REST_Request $request The request.
	 * @return array|WP_Error array( $published, $session ).
	 */
	private function load( WP_REST_Request $request ) {

		$limited = self::rate_limit( 'step', 120, MINUTE_IN_SECONDS );
		if ( is_wp_error( $limited ) ) {
			return $limited;
		}

		$session = Maqsy_Quiz_Store::get_session_by_token( (string) $request->get_param( 'token' ) );
		if ( ! $session || 'restarted' === $session['status'] ) {
			return new WP_Error( 'maqsy_quiz_session_expired', __( 'This quiz session has expired. Please start the quiz again.', 'maqsy-faq-ai-chat' ), array( 'status' => 404 ) );
		}

		$published = Maqsy_Quizzes::get_published( (int) $session['quiz_id'] );
		if ( ! $published ) {
			return self::not_found();
		}

		return array( $published, $session );
	}

	/**
	 * The error for an unknown, unpublished, or empty quiz.
	 *
	 * @since  1.2.0
	 * @return WP_Error
	 */
	private static function not_found() {
		return new WP_Error( 'maqsy_quiz_not_found', __( 'This quiz is not available right now.', 'maqsy-faq-ai-chat' ), array( 'status' => 404 ) );
	}

	/**
	 * Apply one of the public routes' per-IP rate limits.
	 *
	 * @since  1.2.0
	 * @param  string $bucket Limit name.
	 * @param  int    $max    Requests allowed per window.
	 * @param  int    $window Window length in seconds.
	 * @return true|WP_Error
	 */
	private static function rate_limit( $bucket, $max, $window ) {

		if ( Maqsy_Rest_Controller::is_rate_limited( 'maqsy_quiz_' . $bucket, $max, $window ) ) {
			return new WP_Error( 'maqsy_quiz_rate_limited', __( 'You are going a little too fast. Please wait a moment and try again.', 'maqsy-faq-ai-chat' ), array( 'status' => 429 ) );
		}

		return true;
	}
}
