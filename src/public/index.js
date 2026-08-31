import './style.css';

document.addEventListener('DOMContentLoaded', function () {
	// Only the accordion template's questions are buttons — the "simple"
	// template has no expand/collapse behaviour to wire up.
	document
		.querySelectorAll('.maqsy.accordion button.maqsy-question')
		.forEach(function (button) {
			button.addEventListener('click', function (e) {
				e.preventDefault();

				const item = button.closest('.maqsy-item');
				const answer = item.querySelector('.maqsy-answer');
				const isActive = item.classList.toggle('active');

				button.setAttribute(
					'aria-expanded',
					isActive ? 'true' : 'false'
				);

				if (isActive) {
					// Reveal before measuring scrollHeight/animating, and before assistive tech or keyboard focus can reach the content.
					answer.inert = false;
					answer.removeAttribute('aria-hidden');
					answer.style.maxHeight = answer.scrollHeight + 'px';
				} else {
					answer.style.maxHeight = '';
					answer.inert = true;
					answer.setAttribute('aria-hidden', 'true');
				}
			});
		});
});
