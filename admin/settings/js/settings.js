jQuery(document).ready(function($) {
	const tabs = document.querySelectorAll('.nav-tab');
	const contents = document.querySelectorAll('.shipbubble-tab-content');

	tabs.forEach(tab => {
		tab.addEventListener('click', function(e) {
			e.preventDefault();

			// Remove active state from all tabs and hide all content
			tabs.forEach(t => t.classList.remove('nav-tab-active'));
			contents.forEach(c => c.style.display = 'none');

			// Add active state to clicked tab and show corresponding content
			this.classList.add('nav-tab-active');
			const target = document.querySelector(this.getAttribute('href'));
			if (target) target.style.display = 'block';
		});
	});
});