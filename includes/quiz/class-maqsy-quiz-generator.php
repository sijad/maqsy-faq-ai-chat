<?php
/**
 * AI-assisted quiz generation.
 *
 * @since      1.2.0
 *
 * @package    Maqsy
 * @subpackage Maqsy/includes/quiz
 */

/**
 * Drafts a complete quiz definition from an admin's goals and business context.
 *
 * The model is asked for JSON in the same shape Maqsy_Quiz_Schema accepts, and
 * whatever comes back is run through the schema sanitizer, so a hallucinated
 * or malformed reference can only ever be dropped, never stored.
 *
 * @since      1.2.0
 * @package    Maqsy
 * @subpackage Maqsy/includes/quiz
 */
class Maqsy_Quiz_Generator {

	/**
	 * Generate a quiz.
	 *
	 * @since  1.2.0
	 * @param  array $brief {
	 *     The admin's generation brief.
	 *
	 *     @type string $goal          What the quiz should achieve.
	 *     @type string $audience      Who will take it.
	 *     @type string $context       Business context: products, services, offers.
	 *     @type int    $questions     Desired question count (3–12).
	 *     @type bool   $capture_lead  Whether to ask for the visitor's name and email.
	 *     @type bool   $use_faqs      Whether to include published FAQ content as context.
	 * }
	 * @return array|WP_Error { title, quiz } on success.
	 */
	public static function generate( array $brief ) {

		$settings = Maqsy_Settings::get_settings();
		$provider = Maqsy_AI_Registry::get_provider( $settings['provider'] );

		if ( ! $provider || empty( $settings['api_key'] ) ) {
			return new WP_Error( 'maqsy_ai_not_configured', __( 'Add an API key under FAQs → AI Chat Settings to generate quizzes with AI.', 'maqsy-faq-ai-chat' ), array( 'status' => 400 ) );
		}

		$count = max( 3, min( 12, (int) $brief['questions'] ) );

		$context = array();
		if ( ! empty( $settings['custom_instructions'] ) ) {
			$context[] = "Site instructions:\n" . $settings['custom_instructions'];
		}
		if ( '' !== $brief['context'] ) {
			$context[] = "Business context:\n" . $brief['context'];
		}
		if ( ! empty( $brief['use_faqs'] ) ) {
			$faqs = self::get_faq_digest();
			if ( $faqs ) {
				$context[] = "Published FAQ content:\n" . $faqs;
			}
		}

		$user = sprintf(
			"Goal of the quiz: %s\nTarget audience: %s\nNumber of questions: about %d%s\n\n%s",
			'' !== $brief['goal'] ? $brief['goal'] : 'Help visitors find the most relevant product or service for them.',
			'' !== $brief['audience'] ? $brief['audience'] : 'Website visitors',
			$count,
			! empty( $brief['capture_lead'] ) ? ', ending with an open-ended question asking for their first name (lead_field "name") and one asking for their email (lead_field "email"), both optional' : '',
			implode( "\n\n", $context )
		);

		set_time_limit( 120 ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- generation can outlast the default 30s.

		$reply = $provider->send_message(
			array(
				array(
					'role'    => 'system',
					'content' => self::get_system_prompt(),
				),
				array(
					'role'    => 'user',
					'content' => $user,
				),
			),
			array_merge( $settings, array( 'timeout' => 90 ) )
		);

		if ( is_wp_error( $reply ) ) {
			$data = (array) $reply->get_error_data();
			if ( isset( $data['provider_status'] ) && in_array( (int) $data['provider_status'], array( 401, 403 ), true ) ) {
				return new WP_Error(
					'maqsy_ai_auth_failed',
					sprintf(
						/* translators: %s: the provider's own error message, e.g. "User not found." */
						__( 'The AI provider rejected the API key (%s). Check the key under FAQs → AI Chat Settings.', 'maqsy-faq-ai-chat' ),
						$reply->get_error_message()
					),
					array( 'status' => 502 )
				);
			}
			$reply->add_data( array( 'status' => 502 ) );
			return $reply;
		}

		$data = self::decode_json( $reply );
		if ( ! is_array( $data ) || empty( $data['questions'] ) ) {
			return new WP_Error( 'maqsy_quiz_generation_invalid', __( 'The AI returned a quiz in an unexpected format. Please try again.', 'maqsy-faq-ai-chat' ), array( 'status' => 502 ) );
		}

		$quiz = Maqsy_Quiz_Schema::sanitize( $data );
		if ( ! $quiz['questions'] ) {
			return new WP_Error( 'maqsy_quiz_generation_empty', __( 'The AI did not return any usable questions. Please try again.', 'maqsy-faq-ai-chat' ), array( 'status' => 502 ) );
		}

		return array(
			'title' => isset( $data['title'] ) && is_string( $data['title'] ) ? sanitize_text_field( $data['title'] ) : '',
			'quiz'  => $quiz,
		);
	}

	/**
	 * The generation system prompt, documenting the JSON format.
	 *
	 * @since  1.2.0
	 * @return string
	 */
	private static function get_system_prompt() {

		return <<<'PROMPT'
You design short, engaging conversational quizzes that a website's chat assistant runs with visitors, one question at a time, to understand their needs and recommend the most relevant solution. Write in the same language as the business context. Keep questions short, friendly and easy to answer; prefer multiple-choice; use open-ended questions sparingly.

Respond with ONLY a JSON object (no prose, no code fences) of this exact shape:
{
  "title": "Quiz name",
  "description": "One sentence telling visitors what the quiz helps with.",
  "intro": "One or two friendly sentences the assistant says before the first question.",
  "questions": [
    {
      "id": "q1",
      "type": "single" | "multiple" | "text",
      "text": "The question",
      "help": "Optional short hint, or empty string",
      "required": false,
      "lead_field": "" | "name" | "email" | "phone",   // only for type "text"
      "options": [                                       // omit for type "text"
        { "id": "q1a", "label": "Answer", "scores": { "o1": 2, "o2": 0 }, "goto": "" }
      ],
      "conditions": { "match": "all", "rules": [] }
    }
  ],
  "outcomes": [
    {
      "id": "o1",
      "title": "Result name, e.g. the recommended product or plan",
      "message": "2-4 sentences explaining why this fits. You may use {name} for the visitor's name.",
      "offer": "Optional short offer or incentive, or empty string",
      "recommendations": [ { "title": "Thing to check out", "url": "", "description": "Why" } ],
      "cta_label": "Call-to-action button text",
      "cta_url": "",
      "conditions": { "match": "all", "rules": [] }
    }
  ],
  "default_outcome": "o1"
}

Rules:
- Create 2 to 5 distinct outcomes that map to the business's real offerings.
- Every option of every choice question gives points (0-3) to the outcomes it indicates; the outcome with the most points wins.
- Branching is optional: an option's "goto" may name a LATER question id (or "end") to skip ahead; otherwise leave it "".
- Conditional questions are optional: a question's "conditions.rules" may contain { "question": "<an EARLIER question id>", "operator": "is" | "is_not", "value": "<an option id of that question>" } so it is only asked when the rule matches.
- Only use URLs that appear in the business context; otherwise leave url fields empty.
- Never invent prices, discounts or guarantees that are not in the business context.
PROMPT;
	}

	/**
	 * Decode a JSON object from a model reply that may wrap it in prose or fences.
	 *
	 * @since  1.2.0
	 * @param  string $reply The raw reply.
	 * @return array|null
	 */
	private static function decode_json( $reply ) {

		$data = json_decode( $reply, true );
		if ( is_array( $data ) ) {
			return $data;
		}

		$start = strpos( $reply, '{' );
		$end   = strrpos( $reply, '}' );
		if ( false === $start || false === $end || $end <= $start ) {
			return null;
		}

		$data = json_decode( substr( $reply, $start, $end - $start + 1 ), true );

		return is_array( $data ) ? $data : null;
	}

	/**
	 * A compact digest of published FAQs to ground generated quizzes.
	 *
	 * @since  1.2.0
	 * @return string
	 */
	private static function get_faq_digest() {

		$lines = array();
		foreach ( Maqsy_AI_Chat::get_faq_groups( 50 ) as $group ) {
			foreach ( $group['questions'] as $question ) {
				$lines[] = 'Q: ' . wp_strip_all_tags( $question['question'] ) . "\nA: " . mb_substr( wp_strip_all_tags( $question['answer'] ), 0, 400 );
			}
		}

		// Keep the prompt within a sane size for small/free models.
		return mb_substr( implode( "\n", $lines ), 0, 12000 );
	}
}
