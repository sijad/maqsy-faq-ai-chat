<?php
/**
 * Admin screens for quizzes: the builder, list table, and analytics.
 *
 * @since      1.2.0
 *
 * @package    Maqsy
 * @subpackage Maqsy/admin
 */

/**
 * Wires the quiz builder and analytics dashboard into wp-admin.
 *
 * Quizzes reuse the classic post edit screen — title, draft/publish/schedule,
 * trash, and revisions-free saving all come for free — with the React
 * builder (src/quiz-builder) mounted below the title. The builder keeps a
 * hidden input in sync with the quiz definition JSON, which save_post()
 * sanitizes and stores on submit, just like the FAQ question rows.
 *
 * @since      1.2.0
 * @package    Maqsy
 * @subpackage Maqsy/admin
 */
class Maqsy_Quiz_Admin {

	/**
	 * The analytics page slug.
	 *
	 * @since 1.2.0
	 * @var   string
	 */
	const ANALYTICS_SLUG = 'maqsy-quiz-analytics';

	/**
	 * The analytics page's hook suffix.
	 *
	 * @since 1.2.0
	 * @var   string
	 */
	private $analytics_hook;

	/**
	 * Cached per-quiz counts for the list table.
	 *
	 * @since 1.2.0
	 * @var   array|null
	 */
	private $list_counts = null;

	/**
	 * Add the analytics submenu page.
	 *
	 * @since 1.2.0
	 */
	public function add_menu() {

		$this->analytics_hook = add_submenu_page(
			'edit.php?post_type=maqsy',
			__( 'Quiz Analytics', 'maqsy-faq-ai-chat' ),
			__( 'Quiz Analytics', 'maqsy-faq-ai-chat' ),
			'manage_options',
			self::ANALYTICS_SLUG,
			array( $this, 'render_analytics_page' )
		);
	}

	/**
	 * Print the builder's mount point under the title field.
	 *
	 * @since 1.2.0
	 * @param WP_Post $post The post being edited.
	 */
	public function edit_form_after_title( $post ) {

		if ( Maqsy_Quizzes::POST_TYPE !== $post->post_type ) {
			return;
		}

		wp_nonce_field( 'maqsy_save_quiz', 'maqsy_quiz_nonce' );

		printf(
			'<input type="hidden" id="maqsy-quiz-data" name="maqsy_quiz_data" value="%1$s" /><div id="maqsy-quiz-builder" class="maqsy-quiz-builder"><p class="hide-if-js">%2$s</p><p class="hide-if-no-js"><span class="spinner is-active" style="float:none"></span></p></div>',
			esc_attr( wp_json_encode( Maqsy_Quizzes::get_definition( $post->ID ) ) ),
			esc_html__( 'The quiz builder requires JavaScript.', 'maqsy-faq-ai-chat' )
		);
	}

	/**
	 * Placeholder for the title field.
	 *
	 * @since  1.2.0
	 * @param  string  $placeholder The default placeholder.
	 * @param  WP_Post $post        The post being edited.
	 * @return string
	 */
	public function enter_title_here( $placeholder, $post ) {
		return Maqsy_Quizzes::POST_TYPE === $post->post_type ? __( 'Quiz name (shown to visitors)', 'maqsy-faq-ai-chat' ) : $placeholder;
	}

	/**
	 * Save the quiz definition.
	 *
	 * @since 1.2.0
	 * @param int $post_id The post being saved.
	 */
	public function save_post( $post_id ) {

		if ( Maqsy_Quizzes::POST_TYPE !== get_post_type( $post_id ) || wp_is_post_revision( $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST['maqsy_quiz_nonce'], $_POST['maqsy_quiz_data'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['maqsy_quiz_nonce'] ) ), 'maqsy_save_quiz' )
		) {
			return;
		}

		// The JSON is sanitized field by field by Maqsy_Quiz_Schema::sanitize().
		$raw = json_decode( wp_unslash( $_POST['maqsy_quiz_data'] ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( is_array( $raw ) ) {
			Maqsy_Quizzes::save_definition( $post_id, $raw );
		}
	}

	/**
	 * Remove a quiz's analytics when the quiz is permanently deleted.
	 *
	 * @since 1.2.0
	 * @param int $post_id The post being deleted.
	 */
	public function before_delete_post( $post_id ) {
		if ( Maqsy_Quizzes::POST_TYPE === get_post_type( $post_id ) ) {
			Maqsy_Quiz_Store::delete_quiz_data( $post_id );
		}
	}

	/**
	 * Enqueue the builder or analytics app on their screens.
	 *
	 * @since 1.2.0
	 * @param string $hook The current admin page's hook suffix.
	 */
	public function enqueue_assets( $hook ) {

		$screen = get_current_screen();

		if ( $screen && Maqsy_Quizzes::POST_TYPE === $screen->id && in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			$this->enqueue_app( 'quiz-builder', 'maqsyQuizBuilder', $this->get_builder_config() );
			return;
		}

		if ( $screen && 'edit-' . Maqsy_Quizzes::POST_TYPE === $screen->id ) {
			$this->enqueue_app( 'quiz-builder', null, null, true );
			return;
		}

		if ( $hook === $this->analytics_hook ) {
			$this->enqueue_app(
				'quiz-analytics',
				'maqsyQuizAnalytics',
				array(
					'quizzes'   => $this->get_quiz_choices(),
					'quiz'      => isset( $_GET['quiz'] ) ? absint( $_GET['quiz'] ) : 0, // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
					'exportUrl' => wp_nonce_url( admin_url( 'admin-post.php?action=maqsy_quiz_export' ), 'maqsy_quiz_export' ),
					'editUrl'   => admin_url( 'post.php?action=edit&post=' ),
					'newUrl'    => admin_url( 'post-new.php?post_type=' . Maqsy_Quizzes::POST_TYPE ),
				)
			);
		}
	}

	/**
	 * Enqueue one of the wp-scripts-built admin apps.
	 *
	 * @since 1.2.0
	 * @param string      $entry      The build entry name.
	 * @param string|null $js_var     Global JS config variable name.
	 * @param array|null  $data       Config data.
	 * @param bool        $style_only Only enqueue the stylesheet.
	 */
	private function enqueue_app( $entry, $js_var, $data, $style_only = false ) {

		$asset_file = plugin_dir_path( __DIR__ ) . 'build/' . $entry . '.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}
		$asset = include $asset_file;

		wp_enqueue_style( 'maqsy-' . $entry, plugin_dir_url( __DIR__ ) . 'build/style-' . $entry . '.css', array( 'wp-components' ), $asset['version'] );

		if ( $style_only ) {
			return;
		}

		wp_enqueue_script( 'maqsy-' . $entry, plugin_dir_url( __DIR__ ) . 'build/' . $entry . '.js', $asset['dependencies'], $asset['version'], true );
		wp_set_script_translations( 'maqsy-' . $entry, 'maqsy-faq-ai-chat', plugin_dir_path( __DIR__ ) . 'languages' );

		if ( $js_var ) {
			wp_add_inline_script( 'maqsy-' . $entry, 'window.' . $js_var . ' = ' . wp_json_encode( $data ) . ';', 'before' );
		}
	}

	/**
	 * Configuration for the builder app.
	 *
	 * @since  1.2.0
	 * @return array
	 */
	private function get_builder_config() {

		$post     = get_post();
		$settings = Maqsy_Settings::get_settings();
		// AI generation spends the site's AI credits and prefills the site's
		// private instructions, and analytics include leads: admins only.
		$is_admin = current_user_can( 'manage_options' );

		// Keywords other published quizzes already use, so the builder can flag clashes.
		$used_keywords = array();
		foreach ( Maqsy_Quizzes::get_all_published() as $published ) {
			if ( ! $post || $published['id'] !== $post->ID ) {
				foreach ( $published['quiz']['keywords'] as $keyword ) {
					$used_keywords[ $keyword ] = $published['title'];
				}
			}
		}

		return array(
			'postId'          => $post ? $post->ID : 0,
			'status'          => $post ? $post->post_status : 'auto-draft',
			'aiConfigured'    => ! empty( $settings['api_key'] ),
			'chatEnabled'     => ! empty( $settings['enabled'] ) && ! empty( $settings['api_key'] ),
			'settingsUrl'     => admin_url( 'edit.php?post_type=maqsy&page=' . Maqsy_Settings::PAGE_SLUG ),
			'canGenerate'     => $is_admin,
			'analyticsUrl'    => $post && $is_admin ? admin_url( 'edit.php?post_type=maqsy&page=' . self::ANALYTICS_SLUG . '&quiz=' . $post->ID ) : '',
			'businessContext' => $is_admin ? $settings['custom_instructions'] : '',
			'questionTypes'   => Maqsy_Quiz_Schema::get_question_types(),
			'leadFields'      => Maqsy_Quiz_Schema::get_lead_fields(),
			'usedKeywords'    => (object) $used_keywords,
			'limits'          => array(
				'questions'       => Maqsy_Quiz_Schema::MAX_QUESTIONS,
				'options'         => Maqsy_Quiz_Schema::MAX_OPTIONS,
				'outcomes'        => Maqsy_Quiz_Schema::MAX_OUTCOMES,
				'recommendations' => Maqsy_Quiz_Schema::MAX_RECOMMENDATIONS,
				'rules'           => Maqsy_Quiz_Schema::MAX_RULES,
			),
		);
	}

	/**
	 * All quizzes as { id, title, status } for the analytics quiz picker.
	 *
	 * @since  1.2.0
	 * @return array[]
	 */
	private function get_quiz_choices() {

		$posts = get_posts(
			array(
				'post_type'   => Maqsy_Quizzes::POST_TYPE,
				'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'numberposts' => 200, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_numberposts -- bounded admin listing.
				'orderby'     => 'title',
				'order'       => 'ASC',
			)
		);

		$choices = array();
		foreach ( $posts as $post ) {
			$choices[] = array(
				'id'     => $post->ID,
				'title'  => $post->post_title ? $post->post_title : __( '(no title)', 'maqsy-faq-ai-chat' ),
				'status' => $post->post_status,
			);
		}

		return $choices;
	}

	/**
	 * Render the analytics page shell; the React app fills it in.
	 *
	 * @since 1.2.0
	 */
	public function render_analytics_page() {

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap maqsy-analytics-wrap">
			<h1><?php esc_html_e( 'Quiz Analytics', 'maqsy-faq-ai-chat' ); ?></h1>
			<div id="maqsy-quiz-analytics"><p class="hide-if-js"><?php esc_html_e( 'The analytics dashboard requires JavaScript.', 'maqsy-faq-ai-chat' ); ?></p></div>
		</div>
		<?php
	}

	/**
	 * Add list-table columns.
	 *
	 * @since  1.2.0
	 * @param  array $columns Existing columns.
	 * @return array
	 */
	public function list_columns( $columns ) {

		$date = isset( $columns['date'] ) ? $columns['date'] : null;
		unset( $columns['date'] );

		$columns['maqsy_questions']  = __( 'Questions', 'maqsy-faq-ai-chat' );
		$columns['maqsy_starts']     = __( 'Starts (30 days)', 'maqsy-faq-ai-chat' );
		$columns['maqsy_completion'] = __( 'Completion rate', 'maqsy-faq-ai-chat' );
		$columns['maqsy_leads']      = __( 'Leads', 'maqsy-faq-ai-chat' );
		$columns['maqsy_embed']      = __( 'Shortcode', 'maqsy-faq-ai-chat' );
		if ( $date ) {
			$columns['date'] = $date;
		}

		return $columns;
	}

	/**
	 * Render a list-table column.
	 *
	 * @since 1.2.0
	 * @param string $column  The column key.
	 * @param int    $post_id The quiz ID.
	 */
	public function list_column( $column, $post_id ) {

		if ( 0 !== strpos( $column, 'maqsy_' ) ) {
			return;
		}

		if ( null === $this->list_counts ) {
			global $wp_query;
			$ids               = $wp_query && $wp_query->posts ? wp_list_pluck( $wp_query->posts, 'ID' ) : array( $post_id );
			$this->list_counts = Maqsy_Quiz_Analytics::get_counts_for_quizzes( $ids, Maqsy_Quiz_Analytics::range( 30 ) );
		}
		$counts = isset( $this->list_counts[ $post_id ] ) ? $this->list_counts[ $post_id ] : array(
			'starts'      => 0,
			'completions' => 0,
			'leads'       => 0,
		);

		switch ( $column ) {
			case 'maqsy_questions':
				$quiz = Maqsy_Quizzes::get_definition( $post_id );
				echo esc_html( number_format_i18n( count( $quiz['questions'] ) ) );
				if ( ! $quiz['questions'] ) {
					echo ' <span class="maqsy-quiz-warning">' . esc_html__( '— add questions to publish', 'maqsy-faq-ai-chat' ) . '</span>';
				}
				break;
			case 'maqsy_starts':
				echo esc_html( number_format_i18n( $counts['starts'] ) );
				break;
			case 'maqsy_completion':
				echo $counts['starts'] ? esc_html( number_format_i18n( 100 * $counts['completions'] / $counts['starts'], 0 ) . '%' ) : '—';
				break;
			case 'maqsy_leads':
				echo esc_html( number_format_i18n( $counts['leads'] ) );
				break;
			case 'maqsy_embed':
				printf( '<code class="maqsy-quiz-code">[maqsy_quiz id="%d"]</code>', (int) $post_id );
				break;
		}
	}

	/**
	 * Add an "Analytics" row action.
	 *
	 * @since  1.2.0
	 * @param  array   $actions Row actions.
	 * @param  WP_Post $post    The quiz.
	 * @return array
	 */
	public function row_actions( $actions, $post ) {

		if ( Maqsy_Quizzes::POST_TYPE === $post->post_type && current_user_can( 'manage_options' ) ) {
			$actions['maqsy_analytics'] = sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( admin_url( 'edit.php?post_type=maqsy&page=' . self::ANALYTICS_SLUG . '&quiz=' . $post->ID ) ),
				esc_html__( 'Analytics', 'maqsy-faq-ai-chat' )
			);
		}

		return $actions;
	}

	/**
	 * Keep the FAQs menu open (and "Quizzes" highlighted) on quiz screens.
	 *
	 * @since  1.2.0
	 * @param  string $parent_file The parent menu file.
	 * @return string
	 */
	public function parent_file( $parent_file ) {

		$screen = get_current_screen();
		if ( $screen && Maqsy_Quizzes::POST_TYPE === $screen->post_type ) {
			global $submenu_file;
			$submenu_file = 'edit.php?post_type=' . Maqsy_Quizzes::POST_TYPE; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			return 'edit.php?post_type=maqsy';
		}

		return $parent_file;
	}

	/**
	 * Stream sessions (or only leads) as CSV.
	 *
	 * @since 1.2.0
	 */
	public function export_csv() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to export quiz data.', 'maqsy-faq-ai-chat' ), 403 );
		}
		check_admin_referer( 'maqsy_quiz_export' );

		$quiz_id = isset( $_GET['quiz'] ) ? absint( $_GET['quiz'] ) : 0;
		$days    = isset( $_GET['days'] ) ? absint( $_GET['days'] ) : 0;
		$leads   = ! empty( $_GET['leads'] );

		$result = Maqsy_Quiz_Analytics::get_sessions( $quiz_id, $days, $leads, 1, 0 );

		$header = array(
			__( 'Started (UTC)', 'maqsy-faq-ai-chat' ),
			__( 'Completed (UTC)', 'maqsy-faq-ai-chat' ),
			__( 'Quiz', 'maqsy-faq-ai-chat' ),
			__( 'Status', 'maqsy-faq-ai-chat' ),
			__( 'Source', 'maqsy-faq-ai-chat' ),
			__( 'Name', 'maqsy-faq-ai-chat' ),
			__( 'Email', 'maqsy-faq-ai-chat' ),
			__( 'Phone', 'maqsy-faq-ai-chat' ),
			__( 'Outcome', 'maqsy-faq-ai-chat' ),
			__( 'CTA clicked', 'maqsy-faq-ai-chat' ),
			__( 'Page', 'maqsy-faq-ai-chat' ),
		);

		// One column per question for a single quiz; a combined column otherwise.
		$questions = array();
		if ( $quiz_id ) {
			foreach ( Maqsy_Quizzes::get_definition( $quiz_id )['questions'] as $question ) {
				$questions[] = $question['id'];
				$header[]    = $question['text'];
			}
		} else {
			$header[] = __( 'Responses', 'maqsy-faq-ai-chat' );
		}

		$filename = sprintf( 'maqsy-quiz-%s-%s.csv', $leads ? 'leads' : 'responses', gmdate( 'Y-m-d' ) );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- UTF-8 BOM so Excel detects the encoding.
		fputcsv( $out, array_map( array( __CLASS__, 'csv_cell' ), $header ), ',', '"', '\\' );

		foreach ( $result['items'] as $item ) {
			$row = array(
				gmdate( 'Y-m-d H:i:s', strtotime( $item['started_at'] ) ),
				$item['completed_at'] ? gmdate( 'Y-m-d H:i:s', strtotime( $item['completed_at'] ) ) : '',
				$item['quiz_title'],
				$item['status'],
				$item['source'],
				$item['name'],
				$item['email'],
				$item['phone'],
				$item['outcome'],
				$item['cta_clicked'] ? 'yes' : 'no',
				$item['page_url'],
			);
			if ( $quiz_id ) {
				// By ID, not text: two questions can share the same text.
				$by_question = array();
				foreach ( $item['responses'] as $response ) {
					$by_question[ $response['id'] ] = $response['skipped'] ? '(skipped)' : $response['answer'];
				}
				foreach ( $questions as $question_id ) {
					$row[] = isset( $by_question[ $question_id ] ) ? $by_question[ $question_id ] : '';
				}
			} else {
				$row[] = implode(
					"\n",
					array_map(
						static function ( $response ) {
							return $response['question'] . ': ' . ( $response['skipped'] ? '(skipped)' : $response['answer'] );
						},
						$item['responses']
					)
				);
			}
			fputcsv( $out, array_map( array( __CLASS__, 'csv_cell' ), $row ), ',', '"', '\\' );
		}

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/**
	 * Neutralize spreadsheet formula injection in a visitor-supplied cell.
	 *
	 * @since  1.2.0
	 * @param  mixed $value The cell value.
	 * @return string
	 */
	public static function csv_cell( $value ) {

		$value = (string) $value;

		return '' !== $value && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ? "'" . $value : $value;
	}
}
