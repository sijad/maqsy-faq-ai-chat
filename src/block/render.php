<?php
/**
 * Server-side render for the maqsy/faq block.
 *
 * @since 1.0.0
 * @package Maqsy
 *
 * @var array $attributes Block attributes: postId, template.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $attributes['postId'] ) ) {
	return;
}

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- render_faq() escapes all dynamic content internally.
echo Maqsy_Public::render_faq(
	$attributes['postId'],
	array(
		'template' => isset( $attributes['template'] ) ? $attributes['template'] : '',
	)
);
// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
