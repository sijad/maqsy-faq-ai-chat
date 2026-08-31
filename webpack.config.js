const defaultConfig = require("@wordpress/scripts/config/webpack.config");
const path = require("path");

module.exports = {
	...defaultConfig,
	entry: {
		...defaultConfig.entry(),
		admin: path.resolve(process.cwd(), "src/admin", "index.js"),
		public: path.resolve(process.cwd(), "src/public", "index.js"),
	},
};
