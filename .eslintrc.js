module.exports = {
	extends: [ 'plugin:@wordpress/eslint-plugin/recommended' ],
	env: {
		browser: true,
	},
	globals: {
		jQuery: 'readonly',
		tinymce: 'readonly',
		wp: 'readonly',
	},
};
