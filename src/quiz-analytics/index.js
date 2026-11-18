import { createRoot } from '@wordpress/element';
import domReady from '@wordpress/dom-ready';

import Dashboard from './Dashboard';
import './style.css';

domReady(() => {
	const mount = document.getElementById('maqsy-quiz-analytics');
	if (mount) {
		createRoot(mount).render(<Dashboard />);
	}
});
