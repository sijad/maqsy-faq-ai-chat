<?php
/**
 * The quiz definition schema: defaults, limits, and sanitization.
 *
 * @since      1.2.0
 *
 * @package    Maqsy
 * @subpackage Maqsy/includes/quiz
 */

/**
 * Normalizes a quiz definition into a known-good shape.
 *
 * Every quiz definition — whether it comes from the admin builder, from the
 * AI generator, or from post meta written by an older version — goes through
 * sanitize() before it is stored or run, so the engine can trust its input:
 * IDs are unique, every reference (branch targets, condition questions,
 * outcome score keys) points at something that exists, and branches only
 * ever jump forward, which makes every quiz path finite by construction.
 *
 * @since      1.2.0
 * @package    Maqsy
 * @subpackage Maqsy/includes/quiz
 */
class Maqsy_Quiz_Schema {

	const MAX_QUESTIONS       = 50;
	const MAX_OPTIONS         = 20;
	const MAX_OUTCOMES        = 20;
	const MAX_RECOMMENDATIONS = 10;
	const MAX_RULES           = 10;
	const MAX_ANSWER_LENGTH   = 1000;

	/**
	 * The special branch target that finishes the quiz.
	 *
	 * @since 1.2.0
	 * @var   string
	 */
	const END = 'end';

	/**
	 * The supported question types.
	 *
	 * @since  1.2.0
	 * @return array
	 */
	public static function get_question_types() {
		return array(
			'single'   => __( 'Multiple choice (one answer)', 'maqsy-faq-ai-chat' ),
			'multiple' => __( 'Multiple choice (several answers)', 'maqsy-faq-ai-chat' ),
			'text'     => __( 'Open-ended', 'maqsy-faq-ai-chat' ),
		);
	}

	/**
	 * Contact fields an open-ended question can capture as lead data.
	 *
	 * @since  1.2.0
	 * @return array
	 */
	public static function get_lead_fields() {
		return array(
			''      => __( 'None', 'maqsy-faq-ai-chat' ),
			'name'  => __( 'Name', 'maqsy-faq-ai-chat' ),
			'email' => __( 'Email', 'maqsy-faq-ai-chat' ),
			'phone' => __( 'Phone', 'maqsy-faq-ai-chat' ),
		);
	}

	/**
	 * Operators usable in a condition rule.
	 *
	 * @since  1.2.0
	 * @return string[]
	 */
	public static function get_operators() {
		return array( 'is', 'is_not', 'contains', 'not_contains', 'answered', 'skipped' );
	}

	/**
	 * The definition of a brand-new, empty quiz.
	 *
	 * @since  1.2.0
	 * @return array
	 */
	public static function get_defaults() {
		return array(
			'description'     => '',
			'intro'           => '',
			'start_label'     => '',
			'keywords'        => array(),
			'show_in_menu'    => true,
			'suggest'         => true,
			'allow_restart'   => true,
			'notify_email'    => '',
			'default_outcome' => '',
			'questions'       => array(),
			'outcomes'        => array(),
		);
	}

	/**
	 * Sanitize and normalize a raw quiz definition.
	 *
	 * @since  1.2.0
	 * @param  mixed $raw Decoded JSON (or anything else, which yields the defaults).
	 * @return array
	 */
	public static function sanitize( $raw ) {

		$raw      = is_array( $raw ) ? $raw : array();
		$defaults = self::get_defaults();
		$quiz     = array();

		$quiz['description']   = isset( $raw['description'] ) ? self::textarea( $raw['description'], 1000 ) : '';
		$quiz['intro']         = isset( $raw['intro'] ) ? self::textarea( $raw['intro'], 2000 ) : '';
		$quiz['start_label']   = isset( $raw['start_label'] ) ? self::text( $raw['start_label'], 40 ) : '';
		$quiz['keywords']      = self::keywords( isset( $raw['keywords'] ) ? $raw['keywords'] : array() );
		$quiz['show_in_menu']  = isset( $raw['show_in_menu'] ) ? (bool) $raw['show_in_menu'] : $defaults['show_in_menu'];
		$quiz['suggest']       = isset( $raw['suggest'] ) ? (bool) $raw['suggest'] : $defaults['suggest'];
		$quiz['allow_restart'] = isset( $raw['allow_restart'] ) ? (bool) $raw['allow_restart'] : $defaults['allow_restart'];
		$quiz['notify_email']  = isset( $raw['notify_email'] ) && is_string( $raw['notify_email'] ) && is_email( $raw['notify_email'] ) ? sanitize_email( $raw['notify_email'] ) : '';

		// Outcomes first: question option scores reference outcome IDs.
		$outcome_ids  = array();
		$outcomes     = array();
		$raw_outcomes = isset( $raw['outcomes'] ) && is_array( $raw['outcomes'] ) ? array_slice( array_values( $raw['outcomes'] ), 0, self::MAX_OUTCOMES ) : array();
		foreach ( $raw_outcomes as $raw_outcome ) {
			if ( ! is_array( $raw_outcome ) ) {
				continue;
			}
			$id                 = self::unique_id( isset( $raw_outcome['id'] ) ? $raw_outcome['id'] : '', 'o', $outcome_ids );
			$outcome_ids[ $id ] = true;
			$outcomes[]         = array_merge( array( 'id' => $id ), self::sanitize_outcome_fields( $raw_outcome ) );
		}

		$question_ids  = array();
		$option_ids    = array();
		$questions     = array();
		$raw_questions = isset( $raw['questions'] ) && is_array( $raw['questions'] ) ? array_slice( array_values( $raw['questions'] ), 0, self::MAX_QUESTIONS ) : array();
		foreach ( $raw_questions as $raw_question ) {
			if ( ! is_array( $raw_question ) ) {
				continue;
			}
			$id                  = self::unique_id( isset( $raw_question['id'] ) ? $raw_question['id'] : '', 'q', $question_ids );
			$question_ids[ $id ] = true;
			$questions[]         = self::sanitize_question( $id, $raw_question, $outcome_ids, $option_ids );
		}

		// Second pass: references can only be validated once every ID is known.
		// Branch targets must point strictly forward, and conditions may only
		// look at earlier questions (whose answers exist by the time they run).
		$index_of = array_flip( array_column( $questions, 'id' ) );
		foreach ( $questions as $i => &$question ) {
			$question['next'] = self::sanitize_target( $question['next'], $i, $index_of );
			foreach ( $question['options'] as &$option ) {
				$option['goto'] = self::sanitize_target( $option['goto'], $i, $index_of );
			}
			unset( $option );
			$question['conditions'] = self::sanitize_conditions( $question['conditions'], $questions, $i );
		}
		unset( $question );

		foreach ( $outcomes as &$outcome ) {
			$outcome['conditions'] = self::sanitize_conditions( $outcome['conditions'], $questions, count( $questions ) );
		}
		unset( $outcome );

		$quiz['questions']       = $questions;
		$quiz['outcomes']        = $outcomes;
		$quiz['default_outcome'] = isset( $raw['default_outcome'] ) && is_string( $raw['default_outcome'] ) && isset( $outcome_ids[ $raw['default_outcome'] ] ) ? $raw['default_outcome'] : '';

		return $quiz;
	}

	/**
	 * Sanitize one question (references are resolved later by sanitize()).
	 *
	 * @since  1.2.0
	 * @param  string $id          The question's (already unique) ID.
	 * @param  array  $raw         The raw question.
	 * @param  array  $outcome_ids Known outcome IDs, as keys.
	 * @param  array  $option_ids  Option IDs used so far across the quiz, as keys (updated).
	 * @return array
	 */
	private static function sanitize_question( $id, array $raw, array $outcome_ids, array &$option_ids ) {

		$types = self::get_question_types();
		$type  = isset( $raw['type'] ) && is_string( $raw['type'] ) && isset( $types[ $raw['type'] ] ) ? $raw['type'] : 'single';

		$question = array(
			'id'          => $id,
			'type'        => $type,
			'text'        => isset( $raw['text'] ) ? self::text( $raw['text'], 500 ) : '',
			'help'        => isset( $raw['help'] ) ? self::textarea( $raw['help'], 1000 ) : '',
			'required'    => ! empty( $raw['required'] ),
			'lead_field'  => '',
			'placeholder' => '',
			'options'     => array(),
			'next'        => isset( $raw['next'] ) && is_string( $raw['next'] ) ? $raw['next'] : '',
			'conditions'  => isset( $raw['conditions'] ) ? $raw['conditions'] : array(),
		);

		if ( 'text' === $type ) {
			$lead_fields             = self::get_lead_fields();
			$question['lead_field']  = isset( $raw['lead_field'] ) && is_string( $raw['lead_field'] ) && isset( $lead_fields[ $raw['lead_field'] ] ) ? $raw['lead_field'] : '';
			$question['placeholder'] = isset( $raw['placeholder'] ) ? self::text( $raw['placeholder'], 200 ) : '';
			return $question;
		}

		$raw_options = isset( $raw['options'] ) && is_array( $raw['options'] ) ? array_slice( array_values( $raw['options'] ), 0, self::MAX_OPTIONS ) : array();
		foreach ( $raw_options as $raw_option ) {
			// Accept bare strings too — convenient for AI output and imports.
			if ( is_string( $raw_option ) ) {
				$raw_option = array( 'label' => $raw_option );
			}
			if ( ! is_array( $raw_option ) ) {
				continue;
			}
			$label = isset( $raw_option['label'] ) ? self::text( $raw_option['label'], 200 ) : '';
			if ( '' === $label ) {
				continue;
			}

			$option_id                = self::unique_id( isset( $raw_option['id'] ) ? $raw_option['id'] : '', 'a', $option_ids );
			$option_ids[ $option_id ] = true;

			$scores = array();
			if ( isset( $raw_option['scores'] ) && is_array( $raw_option['scores'] ) ) {
				foreach ( $raw_option['scores'] as $outcome_id => $score ) {
					if ( isset( $outcome_ids[ $outcome_id ] ) && is_numeric( $score ) && 0 !== (int) $score ) {
						$scores[ $outcome_id ] = max( -100, min( 100, (int) $score ) );
					}
				}
			}

			$question['options'][] = array(
				'id'     => $option_id,
				'label'  => $label,
				'scores' => (object) $scores,
				'goto'   => isset( $raw_option['goto'] ) && is_string( $raw_option['goto'] ) ? $raw_option['goto'] : '',
			);
		}

		return $question;
	}

	/**
	 * Sanitize an outcome's own fields (everything but its ID and conditions references).
	 *
	 * @since  1.2.0
	 * @param  array $raw The raw outcome.
	 * @return array
	 */
	private static function sanitize_outcome_fields( array $raw ) {

		$recommendations = array();
		if ( isset( $raw['recommendations'] ) && is_array( $raw['recommendations'] ) ) {
			foreach ( array_slice( array_values( $raw['recommendations'] ), 0, self::MAX_RECOMMENDATIONS ) as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$title = isset( $item['title'] ) ? self::text( $item['title'], 200 ) : '';
				if ( '' === $title ) {
					continue;
				}
				$recommendations[] = array(
					'title'       => $title,
					'url'         => isset( $item['url'] ) ? self::url( $item['url'] ) : '',
					'description' => isset( $item['description'] ) ? self::textarea( $item['description'], 500 ) : '',
				);
			}
		}

		return array(
			'title'           => isset( $raw['title'] ) ? self::text( $raw['title'], 200 ) : '',
			'message'         => isset( $raw['message'] ) ? self::textarea( $raw['message'], 4000 ) : '',
			'offer'           => isset( $raw['offer'] ) ? self::textarea( $raw['offer'], 500 ) : '',
			'recommendations' => $recommendations,
			'cta_label'       => isset( $raw['cta_label'] ) ? self::text( $raw['cta_label'], 100 ) : '',
			'cta_url'         => isset( $raw['cta_url'] ) ? self::url( $raw['cta_url'] ) : '',
			'redirect_url'    => isset( $raw['redirect_url'] ) ? self::url( $raw['redirect_url'] ) : '',
			'auto_redirect'   => ! empty( $raw['auto_redirect'] ),
			'conditions'      => isset( $raw['conditions'] ) ? $raw['conditions'] : array(),
		);
	}

	/**
	 * Validate a branch target: '' (continue in order), 'end', or a later question's ID.
	 *
	 * @since  1.2.0
	 * @param  string $target   The raw target.
	 * @param  int    $index    The index of the question the branch belongs to.
	 * @param  array  $index_of Question index keyed by ID.
	 * @return string
	 */
	private static function sanitize_target( $target, $index, array $index_of ) {

		if ( self::END === $target ) {
			return self::END;
		}

		return isset( $index_of[ $target ] ) && $index_of[ $target ] > $index ? $target : '';
	}

	/**
	 * Sanitize a condition group so it only references questions before $before_index.
	 *
	 * @since  1.2.0
	 * @param  mixed $raw          The raw { match, rules } group.
	 * @param  array $questions    The quiz's sanitized questions.
	 * @param  int   $before_index Only questions with an index below this may be referenced.
	 * @return array
	 */
	private static function sanitize_conditions( $raw, array $questions, $before_index ) {

		$raw   = is_array( $raw ) ? $raw : array();
		$group = array(
			'match' => isset( $raw['match'] ) && 'any' === $raw['match'] ? 'any' : 'all',
			'rules' => array(),
		);

		$visible = array();
		foreach ( array_slice( $questions, 0, $before_index ) as $question ) {
			$visible[ $question['id'] ] = $question;
		}

		$raw_rules = isset( $raw['rules'] ) && is_array( $raw['rules'] ) ? array_slice( array_values( $raw['rules'] ), 0, self::MAX_RULES ) : array();
		foreach ( $raw_rules as $rule ) {
			if ( ! is_array( $rule ) || ! isset( $rule['question'] ) || ! is_string( $rule['question'] ) || ! isset( $visible[ $rule['question'] ] ) ) {
				continue;
			}
			$operator = isset( $rule['operator'] ) && in_array( $rule['operator'], self::get_operators(), true ) ? $rule['operator'] : 'is';
			$value    = isset( $rule['value'] ) && is_scalar( $rule['value'] ) ? self::text( (string) $rule['value'], 200 ) : '';

			// For choice questions, "is"/"is not" compare against an option ID,
			// so drop rules whose option no longer exists.
			$target = $visible[ $rule['question'] ];
			if ( in_array( $operator, array( 'is', 'is_not' ), true ) && 'text' !== $target['type'] && ! in_array( $value, array_column( $target['options'], 'id' ), true ) ) {
				continue;
			}

			$group['rules'][] = array(
				'question' => $rule['question'],
				'operator' => $operator,
				'value'    => $value,
			);
		}

		return $group;
	}

	/**
	 * Return a valid, unused ID: the candidate if it is acceptable, otherwise a fresh one.
	 *
	 * @since  1.2.0
	 * @param  mixed  $candidate The requested ID.
	 * @param  string $prefix    Prefix for generated IDs.
	 * @param  array  $used      IDs already taken, as keys.
	 * @return string
	 */
	private static function unique_id( $candidate, $prefix, array $used ) {

		if ( is_string( $candidate ) && self::END !== $candidate && preg_match( '/^[A-Za-z0-9_-]{1,64}$/', $candidate ) && ! isset( $used[ $candidate ] ) ) {
			return $candidate;
		}

		do {
			$id = $prefix . '_' . strtolower( wp_generate_password( 8, false ) );
		} while ( isset( $used[ $id ] ) );

		return $id;
	}

	/**
	 * Sanitize a single-line string and cap its length.
	 *
	 * @since  1.2.0
	 * @param  mixed $value  Raw value.
	 * @param  int   $length Maximum length in characters.
	 * @return string
	 */
	private static function text( $value, $length ) {
		return is_scalar( $value ) ? mb_substr( sanitize_text_field( (string) $value ), 0, $length ) : '';
	}

	/**
	 * Sanitize chat keywords: a list (or comma-separated string) of short
	 * phrases, normalized the way visitor messages are matched against them.
	 *
	 * @since  1.2.0
	 * @param  mixed $value Raw value.
	 * @return string[]
	 */
	private static function keywords( $value ) {

		if ( is_string( $value ) ) {
			$value = explode( ',', $value );
		}

		$keywords = array();
		foreach ( is_array( $value ) ? $value : array() as $keyword ) {
			$keyword = self::normalize_keyword( self::text( $keyword, 50 ) );
			if ( '' !== $keyword && ! in_array( $keyword, $keywords, true ) ) {
				$keywords[] = $keyword;
			}
		}

		return array_slice( $keywords, 0, 10 );
	}

	/**
	 * Normalize a keyword or chat message for matching: case-insensitive,
	 * collapsed whitespace, and no surrounding quotes or punctuation, so
	 * "Access!" and " access " both match the keyword ACCESS.
	 *
	 * @since  1.2.0
	 * @param  string $value Keyword or message.
	 * @return string
	 */
	public static function normalize_keyword( $value ) {

		$value = preg_replace( '/\s+/u', ' ', mb_strtolower( (string) $value ) );

		return (string) preg_replace( '/^[\s\p{P}]+|[\s\p{P}]+$/u', '', $value );
	}

	/**
	 * Sanitize a multi-line string and cap its length.
	 *
	 * @since  1.2.0
	 * @param  mixed $value  Raw value.
	 * @param  int   $length Maximum length in characters.
	 * @return string
	 */
	private static function textarea( $value, $length ) {
		return is_scalar( $value ) ? mb_substr( sanitize_textarea_field( (string) $value ), 0, $length ) : '';
	}

	/**
	 * Sanitize a URL, allowing only http(s) and site-relative paths.
	 *
	 * @since  1.2.0
	 * @param  mixed $value Raw value.
	 * @return string
	 */
	private static function url( $value ) {

		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return '';
		}

		$value = trim( $value );
		if ( '/' === $value[0] && ( ! isset( $value[1] ) || '/' !== $value[1] ) ) {
			return esc_url_raw( home_url( $value ) );
		}

		return (string) esc_url_raw( $value, array( 'http', 'https' ) );
	}
}
