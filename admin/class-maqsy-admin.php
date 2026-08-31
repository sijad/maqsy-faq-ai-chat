<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @since      1.0.0
 *
 * @package    Maqsy
 * @subpackage Maqsy/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Maqsy
 * @subpackage Maqsy/admin
 */
class Maqsy_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string $plugin_name       The name of this plugin.
	 * @param      string $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		$this->templates   = Maqsy_Public::get_templates();
	}

	/**
	 * The available question templates, keyed by their stored value.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array
	 */
	private $templates;

	/**
	 * Questions Meta Box.
	 *
	 * @since    1.0.0
	 * @param    WP_Post $post The post being edited.
	 */
	public function edit_form_advanced( $post ) {
		if ( 'maqsy' !== get_post_type( $post ) ) {
			return;
		}

		$questions = get_post_meta( $post->ID, '_maqsy_questions', true );
		$questions = $questions ? json_decode( $questions, true ) : array();

		if ( ! $questions || count( $questions ) === 0 ) {
			$questions[] = array(
				'question' => '',
				'answer'   => '',
			);
		}

		$rows = '';
		foreach ( $questions as $q ) {
			$rows .= $this->question_row(
				isset( $q['question'] ) ? $q['question'] : '',
				isset( $q['answer'] ) ? $q['answer'] : ''
			);
		}

		// Markup for a fresh row, cloned client-side by admin/index.js. Kept in a
		// <template> so PHP stays the single source of truth for row structure.
		$empty_row = $this->question_row();

		$shortcode = 'new' !== $post->post_status && 'auto-draft' !== $post->post_status
			? '<div class="maqsy-shortcode"><label>' . esc_html__( 'Shortcode', 'maqsy-faq-ai-chat' ) . '</label> <code>[maqsy id="' . (int) $post->ID . '"]</code></div>'
			: '';

		wp_nonce_field( 'maqsy_save_questions', 'maqsy_questions_nonce' );

		$markup  = '<div id="maqsy">';
		$markup .= $shortcode;
		$markup .= '<div class="maqsy-questions" role="list">' . $rows . '</div>';
		$markup .= '<button type="button" id="maqsy-add-question" class="button button-secondary">';
		$markup .= '<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> ';
		$markup .= esc_html__( 'Add question', 'maqsy-faq-ai-chat' );
		$markup .= '</button>';
		$markup .= '<template id="maqsy-question-template">' . $empty_row . '</template>';
		$markup .= '</div>';

		// $shortcode, $rows and $empty_row are assembled from values escaped in
		// question_row() / above; the remainder is static markup.
		echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Build the markup for one question/answer row.
	 *
	 * @since    1.0.0
	 * @param    string $question The question text.
	 * @param    string $answer   The answer HTML.
	 * @return   string
	 */
	private function question_row( $question = '', $answer = '' ) {

		return sprintf(
			'<div class="maqsy-question" role="listitem">' .
				'<span class="maqsy-handle" aria-hidden="true"><span class="dashicons dashicons-menu"></span></span>' .
				'<div class="maqsy-fields">' .
					'<div class="maqsy-row-head">' .
						'<input type="text" required name="maqsy_questions[]" value="%1$s" placeholder="%2$s" class="maqsy-q" />' .
						'<button type="button" class="maqsy-delete button" aria-label="%3$s">' .
							'<span class="dashicons dashicons-trash" aria-hidden="true"></span>' .
						'</button>' .
					'</div>' .
					'<textarea name="maqsy_answers[]" placeholder="%4$s" rows="4" class="maqsy-a">%5$s</textarea>' .
				'</div>' .
			'</div>',
			esc_attr( $question ),
			esc_attr__( 'Question', 'maqsy-faq-ai-chat' ),
			esc_attr__( 'Delete question', 'maqsy-faq-ai-chat' ),
			esc_attr__( 'Answer', 'maqsy-faq-ai-chat' ),
			esc_textarea( $answer )
		);
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, array( 'maqsy', 'edit-maqsy' ), true ) ) {
			return;
		}

		$asset_file = plugin_dir_path( __DIR__ ) . 'build/admin.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = include $asset_file;

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __DIR__ ) . 'build/style-admin.css', array(), $asset['version'], 'all' );
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, array( 'maqsy', 'edit-maqsy' ), true ) ) {
			return;
		}

		$asset_file = plugin_dir_path( __DIR__ ) . 'build/admin.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = include $asset_file;

		wp_enqueue_editor();
		wp_enqueue_script(
			$this->plugin_name,
			plugin_dir_url( __DIR__ ) . 'build/admin.js',
			array_merge( $asset['dependencies'], array( 'jquery-ui-core', 'jquery-ui-sortable', 'editor' ) ),
			$asset['version'],
			true
		);
	}

	/**
	 * Save CPT meta values.
	 *
	 * @since    1.0.0
	 * @param    int $post_id The post being saved.
	 */
	public function save_post( $post_id ) {

		if ( 'maqsy' !== get_post_type( $post_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST['maqsy_questions_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['maqsy_questions_nonce'] ) ), 'maqsy_save_questions' )
		) {
			return;
		}

		if ( ! isset( $_POST['maqsy_questions'] ) || ! isset( $_POST['maqsy_answers'] ) ) {
			return;
		}

		$posted_questions = array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['maqsy_questions'] ) );
		$posted_answers   = array_map( 'wp_kses_post', wp_unslash( (array) $_POST['maqsy_answers'] ) );

		$questions = array();
		foreach ( $posted_questions as $k => $question ) {
			if ( '' === $question ) {
				continue;
			}
			$questions[] = array(
				'question' => $question,
				'answer'   => isset( $posted_answers[ $k ] ) ? $posted_answers[ $k ] : '',
			);
		}

		$posted_template = isset( $_POST['maqsy_questions_template'] ) ? sanitize_text_field( wp_unslash( $_POST['maqsy_questions_template'] ) ) : '';
		$template        = isset( $this->templates[ $posted_template ] ) ? $posted_template : 'simple';

		// wp_slash(): update_post_meta() runs wp_unslash() on the value, which
		// would strip the backslashes out of the JSON's own escape sequences
		// (e.g. "\r\n" between TinyMCE <p> blocks becomes the literal text "rn",
		// and escaped quotes would break the JSON entirely). Pre-slashing lets
		// that wp_unslash() cancel out and the JSON reach the DB intact.
		update_post_meta( $post_id, '_maqsy_questions', wp_slash( wp_json_encode( $questions ) ) );
		update_post_meta( $post_id, '_maqsy_questions_template', $template );
	}

	/**
	 * Add post actions.
	 *
	 * @since    1.0.0
	 * @param    WP_Post $post The post being edited.
	 */
	public function post_submitbox_misc_actions( $post ) {

		if ( 'maqsy' !== get_post_type( $post ) ) {
			return;
		}

		$selected = get_post_meta( $post->ID, '_maqsy_questions_template', true );
		$selected = $selected ? $selected : 'simple';

		?>
			<div id="questions_template" class="misc-pub-section misc-pub-post-status maqsy-template-section">
				<?php esc_html_e( 'Template:', 'maqsy-faq-ai-chat' ); ?>
				<strong><?php echo esc_html( $this->templates[ $selected ] ); ?></strong>
				<a href="#edit_questions_template" class="edit-questions-template hide-if-no-js" role="button">
					<span aria-hidden="true"><?php esc_html_e( 'Edit', 'maqsy-faq-ai-chat' ); ?></span>
					<span class="screen-reader-text"><?php esc_html_e( 'Edit questions template', 'maqsy-faq-ai-chat' ); ?></span>
				</a>
				<fieldset id="questions_template_div" class="hide-if-js">
					<legend class="screen-reader-text"><?php esc_html_e( 'Questions template', 'maqsy-faq-ai-chat' ); ?></legend>
					<select class="form-required" name="maqsy_questions_template">
						<?php
						foreach ( $this->templates as $k => $v ) :
							?>
									<option value="<?php echo esc_attr( $k ); ?>"<?php echo $k === $selected ? ' selected="selected"' : ''; ?>><?php echo esc_html( $v ); ?></option>
								<?php
							endforeach;
						?>
					</select>
				</fieldset>
			</div>
		<?php
	}
}
