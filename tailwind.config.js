module.exports = {
	content: [ './chat-runtime/src/chat-app/**/*.{ts,tsx}' ],
	darkMode: 'class',
	corePlugins: {
		preflight: true,
	},
	theme: {
		extend: {},
	},
	plugins: [ require( 'tailwindcss-animate' ) ],
};
