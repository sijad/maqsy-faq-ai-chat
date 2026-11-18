const defaultConfig = require("@wordpress/scripts/config/webpack.config");
const path = require("path");

module.exports = {
	...defaultConfig,
	entry: {
		...defaultConfig.entry(),
		admin: path.resolve(process.cwd(), "src/admin", "index.js"),
		public: path.resolve(process.cwd(), "src/public", "index.js"),
		"quiz-builder": path.resolve(process.cwd(), "src/quiz-builder", "index.js"),
		"quiz-analytics": path.resolve(process.cwd(), "src/quiz-analytics", "index.js"),
	},
};
