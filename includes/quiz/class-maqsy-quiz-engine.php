<?php
/**
 * The quiz runtime: branching, conditions, and outcome selection.
 *
 * @since      1.2.0
 *
 * @package    Maqsy
 * @subpackage Maqsy/includes/quiz
 */

/**
 * Stateless quiz engine.
 *
 * Every method is a pure function of a sanitized quiz definition (see
 * Maqsy_Quiz_Schema) and an ordered list of answers, so the same code
 * drives both live visitor sessions and the admin builder's preview of an
 * unsaved quiz. An answer entry is:
 *
 *     array( 'question' => 'q_id', 'value' => string|string[]|null, 'skipped' => bool )
 *
 * where value is an option ID (single choice), a list of option IDs
 * (multiple choice), or free text (open-ended).
 *
 * @since      1.2.0
 * @package    Maqsy
 * @subpackage Maqsy/includes/quiz
 */
class Maqsy_Quiz_Engine {

	/**
	 * Work out what comes after the given answers.
	 *
	 * @since  1.2.0
	 * @param  array $quiz    A sanitized quiz definition.
	 * @param  array $answers Ordered answer entries.
	 * @return array { 'done' => bool, 'question' => array|null, 'answered' => int, 'remaining' => int }
	 */
	public static function advance( array $quiz, array $answers ) {

		$last = $answers ? end( $answers ) : null;
		$next = self::next_question( $quiz, self::answer_map( $answers ), $last ? $last['question'] : null );

		return array(
			'done'      => null === $next,
			'question'  => $next,
			'answered'  => count( $answers ),
			'remaining' => $next ? self::estimate_remaining( $quiz, self::answer_map( $answers ), $next['id'] ) : 0,
		);
	}

	/**
	 * Find the next visible question after $from_id (or the first one when null).
	 *
	 * @since  1.2.0
	 * @param  array       $quiz    A sanitized quiz definition.
	 * @param  array       $map     Answers keyed by question ID.
	 * @param  string|null $from_id The question just answered.
	 * @return array|null The next question, or null when the quiz is finished.
	 */
	public static function next_question( array $quiz, array $map, $from_id ) {

		$questions = $quiz['questions'];
		$index_of  = array_flip( array_column( $questions, 'id' ) );

		if ( null === $from_id || ! isset( $index_of[ $from_id ] ) ) {
			// No answers yet, or the last-answered question was deleted after
			// the session started: resume at the first unanswered question.
			$candidate = 0;
		} else {
			$from   = $questions[ $index_of[ $from_id ] ];
			$target = self::branch_target( $from, isset( $map[ $from_id ] ) ? $map[ $from_id ] : null );

			if ( Maqsy_Quiz_Schema::END === $target ) {
				return null;
			}
			$candidate = '' !== $target && isset( $index_of[ $target ] ) && $index_of[ $target ] > $index_of[ $from_id ]
				? $index_of[ $target ]
				: $index_of[ $from_id ] + 1;
		}

		// Conditional questions whose conditions fail are skipped silently.
		$count = count( $questions );
		for ( $i = $candidate; $i < $count; $i++ ) {
			if ( isset( $map[ $questions[ $i ]['id'] ] ) ) {
				continue;
			}
			if ( self::conditions_match( $questions[ $i ]['conditions'], $quiz, $map ) ) {
				return $questions[ $i ];
			}
		}

		return null;
	}

	/**
	 * Estimate how many questions remain, including $from_id itself, assuming
	 * no further branching — used only to draw the progress bar.
	 *
	 * @since  1.2.0
	 * @param  array  $quiz    A sanitized quiz definition.
	 * @param  array  $map     Answers keyed by question ID.
	 * @param  string $from_id The question about to be asked.
	 * @return int
	 */
	private static function estimate_remaining( array $quiz, array $map, $from_id ) {

		$remaining = 0;
		$current   = $from_id;
		$guard     = count( $quiz['questions'] );

		while ( $current && $guard-- > 0 ) {
			++$remaining;
			$map[ $current ] = array(
				'value'   => null,
				'skipped' => true,
			);
			$next            = self::next_question( $quiz, $map, $current );
			$current         = $next ? $next['id'] : null;
		}

		return $remaining;
	}

	/**
	 * Where a question's answer sends the visitor: an option-level jump, the
	 * question's own "next" target, or '' to continue in order.
	 *
	 * @since  1.2.0
	 * @param  array      $question The question.
	 * @param  array|null $entry    Its answer entry.
	 * @return string
	 */
	private static function branch_target( array $question, $entry ) {

		if ( $entry && empty( $entry['skipped'] ) && 'text' !== $question['type'] ) {
			$selected = (array) $entry['value'];
			// Option order (not click order) decides when several selected
			// options of a multiple-choice question define different jumps.
			foreach ( $question['options'] as $option ) {
				if ( '' !== $option['goto'] && in_array( $option['id'], $selected, true ) ) {
					return $option['goto'];
				}
			}
		}

		return $question['next'];
	}

	/**
	 * Evaluate a { match, rules } condition group. An empty group always matches.
	 *
	 * @since  1.2.0
	 * @param  array $group The condition group.
	 * @param  array $quiz  A sanitized quiz definition.
	 * @param  array $map   Answers keyed by question ID.
	 * @return bool
	 */
	public static function conditions_match( array $group, array $quiz, array $map ) {

		if ( empty( $group['rules'] ) ) {
			return true;
		}

		$any = 'any' === $group['match'];
		foreach ( $group['rules'] as $rule ) {
			$result = self::rule_matches( $rule, $quiz, $map );
			if ( $any && $result ) {
				return true;
			}
			if ( ! $any && ! $result ) {
				return false;
			}
		}

		return ! $any;
	}

	/**
	 * Evaluate a single condition rule.
	 *
	 * @since  1.2.0
	 * @param  array $rule { question, operator, value }.
	 * @param  array $quiz A sanitized quiz definition.
	 * @param  array $map  Answers keyed by question ID.
	 * @return bool
	 */
	private static function rule_matches( array $rule, array $quiz, array $map ) {

		$entry    = isset( $map[ $rule['question'] ] ) ? $map[ $rule['question'] ] : null;
		$answered = $entry && empty( $entry['skipped'] );

		switch ( $rule['operator'] ) {
			case 'answered':
				return $answered;
			case 'skipped':
				return ! $answered;
		}

		if ( ! $answered ) {
			// "Is not …" on a question never answered is vacuously true; the
			// positive operators are false.
			return in_array( $rule['operator'], array( 'is_not', 'not_contains' ), true );
		}

		$question = self::find_question( $quiz, $rule['question'] );
		$haystack = $question && 'text' === $question['type'] ? array( (string) $entry['value'] ) : (array) $entry['value'];
		$needle   = (string) $rule['value'];

		if ( $question && 'text' === $question['type'] ) {
			$text  = mb_strtolower( $haystack[0] );
			$match = in_array( $rule['operator'], array( 'contains', 'not_contains' ), true )
				? '' !== $needle && false !== mb_strpos( $text, mb_strtolower( $needle ) )
				: mb_strtolower( trim( $needle ) ) === trim( $text );
		} else {
			// For choice questions every operator compares option IDs.
			$match = in_array( $needle, $haystack, true );
		}

		return in_array( $rule['operator'], array( 'is', 'contains' ), true ) ? $match : ! $match;
	}

	/**
	 * Pick the outcome for a finished quiz.
	 *
	 * Resolution order:
	 * 1. The first outcome (in admin-defined order) with conditions that all match.
	 * 2. The outcome with the highest positive score from selected options
	 *    (ties go to the outcome listed first).
	 * 3. The quiz's default outcome, then the first unconditional outcome.
	 *
	 * @since  1.2.0
	 * @param  array $quiz    A sanitized quiz definition.
	 * @param  array $answers Ordered answer entries.
	 * @return array|null The outcome, or null if the quiz defines none.
	 */
	public static function determine_outcome( array $quiz, array $answers ) {

		if ( empty( $quiz['outcomes'] ) ) {
			return null;
		}

		$map = self::answer_map( $answers );

		foreach ( $quiz['outcomes'] as $outcome ) {
			if ( ! empty( $outcome['conditions']['rules'] ) && self::conditions_match( $outcome['conditions'], $quiz, $map ) ) {
				return $outcome;
			}
		}

		$scores = self::score( $quiz, $map );
		$best   = null;
		foreach ( $quiz['outcomes'] as $outcome ) {
			$score = isset( $scores[ $outcome['id'] ] ) ? $scores[ $outcome['id'] ] : 0;
			if ( $score > 0 && ( null === $best || $score > $scores[ $best['id'] ] ) ) {
				$best = $outcome;
			}
		}
		if ( $best ) {
			return $best;
		}

		foreach ( $quiz['outcomes'] as $outcome ) {
			if ( $outcome['id'] === $quiz['default_outcome'] ) {
				return $outcome;
			}
		}
		foreach ( $quiz['outcomes'] as $outcome ) {
			if ( empty( $outcome['conditions']['rules'] ) ) {
				return $outcome;
			}
		}

		return $quiz['outcomes'][0];
	}

	/**
	 * Total each outcome's score from the selected options.
	 *
	 * @since  1.2.0
	 * @param  array $quiz A sanitized quiz definition.
	 * @param  array $map  Answers keyed by question ID.
	 * @return array Scores keyed by outcome ID.
	 */
	public static function score( array $quiz, array $map ) {

		$totals = array();
		foreach ( $quiz['questions'] as $question ) {
			$entry = isset( $map[ $question['id'] ] ) ? $map[ $question['id'] ] : null;
			if ( ! $entry || ! empty( $entry['skipped'] ) || 'text' === $question['type'] ) {
				continue;
			}
			$selected = (array) $entry['value'];
			foreach ( $question['options'] as $option ) {
				if ( ! in_array( $option['id'], $selected, true ) ) {
					continue;
				}
				foreach ( (array) $option['scores'] as $outcome_id => $points ) {
					$totals[ $outcome_id ] = ( isset( $totals[ $outcome_id ] ) ? $totals[ $outcome_id ] : 0 ) + (int) $points;
				}
			}
		}

		return $totals;
	}

	/**
	 * Validate and normalize a visitor's answer to a question.
	 *
	 * @since  1.2.0
	 * @param  array $question The question being answered.
	 * @param  mixed $value    The submitted value.
	 * @return string|string[]|WP_Error The normalized value, or an error to show the visitor.
	 */
	public static function validate_answer( array $question, $value ) {

		if ( 'text' === $question['type'] ) {
			$text = is_scalar( $value ) ? trim( sanitize_textarea_field( (string) $value ) ) : '';

			if ( '' === $text ) {
				return new WP_Error( 'maqsy_quiz_empty_answer', __( 'Please type an answer.', 'maqsy-faq-ai-chat' ) );
			}
			if ( mb_strlen( $text ) > Maqsy_Quiz_Schema::MAX_ANSWER_LENGTH ) {
				return new WP_Error( 'maqsy_quiz_answer_too_long', __( 'Your answer is too long.', 'maqsy-faq-ai-chat' ) );
			}
			if ( 'email' === $question['lead_field'] && ! is_email( $text ) ) {
				return new WP_Error( 'maqsy_quiz_invalid_email', __( 'Please enter a valid email address.', 'maqsy-faq-ai-chat' ) );
			}
			if ( 'phone' === $question['lead_field'] && ! preg_match( '/^\+?[0-9 ().-]{6,25}$/', $text ) ) {
				return new WP_Error( 'maqsy_quiz_invalid_phone', __( 'Please enter a valid phone number.', 'maqsy-faq-ai-chat' ) );
			}

			return 'email' === $question['lead_field'] ? sanitize_email( $text ) : $text;
		}

		$valid    = array_column( $question['options'], 'id' );
		$selected = array_values( array_unique( array_filter( array_map( 'strval', is_array( $value ) ? $value : array( $value ) ) ) ) );
		$selected = array_values( array_intersect( $selected, $valid ) );

		if ( ! $selected ) {
			return new WP_Error( 'maqsy_quiz_empty_answer', __( 'Please choose an answer.', 'maqsy-faq-ai-chat' ) );
		}
		if ( 'single' === $question['type'] ) {
			if ( count( $selected ) > 1 ) {
				return new WP_Error( 'maqsy_quiz_too_many_answers', __( 'Please choose just one answer.', 'maqsy-faq-ai-chat' ) );
			}
			return $selected[0];
		}

		return $selected;
	}

	/**
	 * A human-readable version of an answer (option labels, or the text itself).
	 *
	 * @since  1.2.0
	 * @param  array $question The question.
	 * @param  array $entry    The answer entry.
	 * @return string
	 */
	public static function answer_label( array $question, array $entry ) {

		if ( ! empty( $entry['skipped'] ) ) {
			return '';
		}
		if ( 'text' === $question['type'] ) {
			return (string) $entry['value'];
		}

		$labels   = array();
		$selected = (array) $entry['value'];
		foreach ( $question['options'] as $option ) {
			if ( in_array( $option['id'], $selected, true ) ) {
				$labels[] = $option['label'];
			}
		}

		return implode( ', ', $labels );
	}

	/**
	 * Build the visitor-facing result payload for an outcome, with
	 * {name} and {answer:QUESTION_ID} placeholders filled in.
	 *
	 * @since  1.2.0
	 * @param  array      $quiz    A sanitized quiz definition.
	 * @param  array|null $outcome The chosen outcome (null for a quiz without outcomes).
	 * @param  array      $answers Ordered answer entries.
	 * @return array
	 */
	public static function build_result( array $quiz, $outcome, array $answers ) {

		if ( ! $outcome ) {
			return array(
				'id'              => '',
				'title'           => __( 'Thanks for completing the quiz!', 'maqsy-faq-ai-chat' ),
				'message'         => '',
				'offer'           => '',
				'recommendations' => array(),
				'cta'             => null,
				'redirect'        => null,
			);
		}

		$replacements = self::placeholder_values( $quiz, $answers );
		$fill         = static function ( $text ) use ( $replacements ) {
			$text = strtr( $text, $replacements );
			// Unknown or unanswered placeholders disappear rather than leak, along
			// with the stray space they leave ("Hi {name}, …" → "Hi, …").
			$text = preg_replace( '/\{(?:name|answer:[A-Za-z0-9_-]+)\}/', '', $text );
			$text = preg_replace( '/[ \t]+([,.!?;:])/u', '$1', $text );
			return trim( preg_replace( '/[ \t]{2,}/', ' ', $text ) );
		};

		$recommendations = array();
		foreach ( $outcome['recommendations'] as $item ) {
			$recommendations[] = array(
				'title'       => $fill( $item['title'] ),
				'url'         => $item['url'],
				'description' => $fill( $item['description'] ),
			);
		}

		return array(
			'id'              => $outcome['id'],
			'title'           => $fill( $outcome['title'] ),
			'message'         => $fill( $outcome['message'] ),
			'offer'           => $fill( $outcome['offer'] ),
			'recommendations' => $recommendations,
			'cta'             => $outcome['cta_url'] ? array(
				'label' => $outcome['cta_label'] ? $fill( $outcome['cta_label'] ) : __( 'Learn more', 'maqsy-faq-ai-chat' ),
				'url'   => $outcome['cta_url'],
			) : null,
			'redirect'        => $outcome['redirect_url'] ? array(
				'url'  => $outcome['redirect_url'],
				'auto' => (bool) $outcome['auto_redirect'],
			) : null,
		);
	}

	/**
	 * Map placeholder tokens to their values for the given answers.
	 *
	 * @since  1.2.0
	 * @param  array $quiz    A sanitized quiz definition.
	 * @param  array $answers Ordered answer entries.
	 * @return array
	 */
	private static function placeholder_values( array $quiz, array $answers ) {

		$values = array();
		$map    = self::answer_map( $answers );
		foreach ( $quiz['questions'] as $question ) {
			if ( ! isset( $map[ $question['id'] ] ) || ! empty( $map[ $question['id'] ]['skipped'] ) ) {
				continue;
			}
			$label = self::answer_label( $question, $map[ $question['id'] ] );

			$values[ '{answer:' . $question['id'] . '}' ] = $label;
			if ( 'name' === $question['lead_field'] && ! isset( $values['{name}'] ) ) {
				$values['{name}'] = $label;
			}
		}

		return $values;
	}

	/**
	 * Extract lead contact details from the answers.
	 *
	 * @since  1.2.0
	 * @param  array $quiz    A sanitized quiz definition.
	 * @param  array $answers Ordered answer entries.
	 * @return array { name, email, phone } (empty strings when not captured).
	 */
	public static function lead_details( array $quiz, array $answers ) {

		$lead = array(
			'name'  => '',
			'email' => '',
			'phone' => '',
		);
		$map  = self::answer_map( $answers );
		foreach ( $quiz['questions'] as $question ) {
			$field = $question['lead_field'];
			if ( $field && '' === $lead[ $field ] && isset( $map[ $question['id'] ] ) && empty( $map[ $question['id'] ]['skipped'] ) ) {
				$lead[ $field ] = (string) $map[ $question['id'] ]['value'];
			}
		}

		return $lead;
	}

	/**
	 * Look up a question by ID.
	 *
	 * @since  1.2.0
	 * @param  array  $quiz A sanitized quiz definition.
	 * @param  string $id   Question ID.
	 * @return array|null
	 */
	public static function find_question( array $quiz, $id ) {

		foreach ( $quiz['questions'] as $question ) {
			if ( $question['id'] === $id ) {
				return $question;
			}
		}

		return null;
	}

	/**
	 * Index answer entries by question ID.
	 *
	 * @since  1.2.0
	 * @param  array $answers Ordered answer entries.
	 * @return array
	 */
	public static function answer_map( array $answers ) {

		$map = array();
		foreach ( $answers as $entry ) {
			if ( is_array( $entry ) && isset( $entry['question'] ) ) {
				$map[ $entry['question'] ] = $entry;
			}
		}

		return $map;
	}

	/**
	 * The visitor-facing shape of a question (no scores, branches, or conditions).
	 *
	 * @since  1.2.0
	 * @param  array $question The question.
	 * @return array
	 */
	public static function public_question( array $question ) {

		$options = array();
		foreach ( $question['options'] as $option ) {
			$options[] = array(
				'id'    => $option['id'],
				'label' => $option['label'],
			);
		}

		$input = 'text';
		if ( 'email' === $question['lead_field'] ) {
			$input = 'email';
		} elseif ( 'phone' === $question['lead_field'] ) {
			$input = 'tel';
		}

		return array(
			'id'          => $question['id'],
			'type'        => $question['type'],
			'text'        => $question['text'],
			'help'        => $question['help'],
			'required'    => (bool) $question['required'],
			'input'       => $input,
			'placeholder' => $question['placeholder'],
			'options'     => $options,
		);
	}
}
