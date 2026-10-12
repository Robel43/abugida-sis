/*
 * Abugida SIS 2026 UI behavior.
 * Keeps RosarioSIS business logic intact while modernizing navigation and feedback.
 */
(function () {
	'use strict';

	var initialized = false;
	var modalQueue = [];
	var modalActive = false;

	function q(selector, root) {
		return (root || document).querySelector(selector);
	}

	function qa(selector, root) {
		return Array.prototype.slice.call((root || document).querySelectorAll(selector));
	}

	function ensureShellHelpers() {
		if (!q('.abg-menu-backdrop')) {
			var backdrop = document.createElement('div');
			backdrop.className = 'abg-menu-backdrop';
			backdrop.setAttribute('aria-hidden', 'true');
			document.body.appendChild(backdrop);
			backdrop.addEventListener('click', closeMenu);
		}

		var menuButton = q('#BottomButtonMenu');
		if (menuButton) {
			menuButton.setAttribute('aria-controls', 'menu');
			menuButton.setAttribute('aria-expanded', document.body.classList.contains('abg-menu-open') ? 'true' : 'false');

			if (menuButton.dataset.abgMenuBound !== '1') {
				menuButton.dataset.abgMenuBound = '1';
				menuButton.addEventListener('click', function (event) {
					if (window.matchMedia('(max-width: 874px)').matches) {
						event.preventDefault();
						event.stopPropagation();
						toggleMenu();
					}
				}, true);
			}
		}

		var menu = q('#menu');
		if (menu) {
			menu.setAttribute('aria-label', 'Primary navigation');

			if (menu.dataset.abgMenuBound !== '1') {
				menu.dataset.abgMenuBound = '1';
				menu.addEventListener('click', function (event) {
					if (window.matchMedia('(max-width: 874px)').matches && event.target.closest('a')) {
						closeMenu();
					}
				});
			}
		}
	}

	function normalizeResponsiveShell() {
		var menu = q('#menu');
		var mobile = window.matchMedia('(max-width: 874px)').matches;

		if (!menu) return;

		/* FlatSIS fixedmenu may insert a width/height spacer directly after #menu.
		   Abugida already reserves sidebar space with #body margin, so the spacer is
		   unnecessary at every viewport width and can cause transient desktop gaps. */
		if (menu.classList.contains('fixedmenu-fixed') && menu.nextElementSibling) {
			var spacer = menu.nextElementSibling;
			if (!spacer.id && !spacer.className) {
				spacer.style.display = 'none';
				spacer.setAttribute('data-abg-fixedmenu-spacer', '1');
			}
		}

		if (mobile) {
			/* Legacy fixedmenu leaves desktop inline geometry behind after live resize. */
			menu.style.removeProperty('bottom');
			menu.style.removeProperty('height');
			menu.style.removeProperty('width');
			menu.style.removeProperty('margin-left');
			menu.style.removeProperty('margin-right');
		} else {
			menu.classList.remove('slide');
		}
	}

	function toggleMenu() {
		normalizeResponsiveShell();
		document.body.classList.toggle('abg-menu-open');
		var opened = document.body.classList.contains('abg-menu-open');
		var menu = q('#menu');
		if (menu) menu.classList.toggle('slide', opened);
		var button = q('#BottomButtonMenu');
		if (button) {
			button.setAttribute('aria-expanded', opened ? 'true' : 'false');
		}
		if (opened) {
			var first = q('#menu a, #menu select');
			if (first) {
				window.setTimeout(function () { first.focus(); }, 30);
			}
		}
	}

	function closeMenu() {
		document.body.classList.remove('abg-menu-open');
		var menu = q('#menu');
		if (menu) menu.classList.remove('slide');
		var button = q('#BottomButtonMenu');
		if (button) {
			button.setAttribute('aria-expanded', 'false');
		}
	}

	function ensureToastRegion() {
		var region = q('.abg-toast-region');
		if (!region) {
			region = document.createElement('div');
			region.className = 'abg-toast-region';
			region.setAttribute('aria-live', 'polite');
			region.setAttribute('aria-atomic', 'false');
			document.body.appendChild(region);
		}
		return region;
	}

	function cleanMessageHTML(node) {
		var clone = node.cloneNode(true);
		qa('img.button, script, style', clone).forEach(function (el) { el.remove(); });
		return clone.innerHTML.trim();
	}

	function showToast(message, type) {
		var region = ensureToastRegion();
		var toast = document.createElement('div');
		toast.className = 'abg-toast abg-toast--' + (type || 'success');
		toast.setAttribute('role', 'status');
		toast.innerHTML =
			'<div class="abg-toast__icon" aria-hidden="true">&#10003;</div>' +
			'<div><div class="abg-toast__title">' + (type === 'info' ? 'Update' : 'Success') + '</div>' +
			'<div class="abg-toast__message">' + message + '</div></div>' +
			'<button type="button" class="abg-toast__close" aria-label="Dismiss">&times;</button>';

		var close = function () {
			if (!toast.parentNode) return;
			toast.style.opacity = '0';
			toast.style.transform = 'translateY(-5px)';
			window.setTimeout(function () { toast.remove(); }, 160);
		};

		q('.abg-toast__close', toast).addEventListener('click', close);
		region.appendChild(toast);
		window.setTimeout(close, 5200);
	}

	function queueModal(message, type) {
		modalQueue.push({ message: message, type: type || 'error' });
		openNextModal();
	}

	function openNextModal() {
		if (modalActive || !modalQueue.length) return;
		modalActive = true;

		var item = modalQueue.shift();
		var isWarning = item.type === 'warning';
		var isSuccess = item.type === 'success';
		var modalType = isSuccess ? 'success' : (isWarning ? 'warning' : 'error');
		var backdrop = document.createElement('div');
		backdrop.className = 'abg-modal-backdrop';
		backdrop.innerHTML =
			'<section class="abg-modal abg-modal--' + modalType + '" role="dialog" aria-modal="true" aria-labelledby="abg-modal-title">' +
			'<div class="abg-modal__head">' +
			'<div class="abg-modal__icon" aria-hidden="true">' + (isSuccess ? '&#10003;' : (isWarning ? '!' : '&times;')) + '</div>' +
			'<div><div id="abg-modal-title" class="abg-modal__title">' + (isSuccess ? 'Success' : (isWarning ? 'Please review' : 'Something went wrong')) + '</div>' +
			'<div class="abg-modal__subtitle">' + (isSuccess ? 'The action completed successfully.' : (isWarning ? 'Check the information below before continuing.' : 'The requested action could not be completed.')) + '</div></div>' +
			'</div>' +
			'<div class="abg-modal__message">' + item.message + '</div>' +
			'<div class="abg-modal__actions"><button type="button" class="abg-modal__button">OK</button></div>' +
			'</section>';

		document.body.classList.add('abg-modal-open');
		document.body.appendChild(backdrop);

		var dialog = q('.abg-modal', backdrop);
		var button = q('.abg-modal__button', backdrop);
		var previousFocus = document.activeElement;

		function close() {
			document.removeEventListener('keydown', onKeyDown, true);
			backdrop.remove();
			document.body.classList.remove('abg-modal-open');
			modalActive = false;
			if (previousFocus && typeof previousFocus.focus === 'function') {
				previousFocus.focus();
			}
			openNextModal();
		}

		function onKeyDown(event) {
			if (event.key === 'Escape') {
				event.preventDefault();
				close();
			}
			if (event.key === 'Tab') {
				var focusable = qa('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])', dialog)
					.filter(function (el) { return !el.disabled && el.offsetParent !== null; });
				if (!focusable.length) return;
				var first = focusable[0];
				var last = focusable[focusable.length - 1];
				if (event.shiftKey && document.activeElement === first) {
					event.preventDefault();
					last.focus();
				} else if (!event.shiftKey && document.activeElement === last) {
					event.preventDefault();
					first.focus();
				}
			}
		}

		button.addEventListener('click', close);
		backdrop.addEventListener('click', function (event) {
			if (event.target === backdrop) close();
		});
		document.addEventListener('keydown', onKeyDown, true);
		window.setTimeout(function () { button.focus(); }, 20);
	}

	function enhanceMessages(root) {
		var scope = root && root.querySelectorAll ? root : document;
		var selector = '#body .abg-feedback-error, #body .abg-feedback-warning, #body .abg-feedback-success, #body .error, #body .warning, #body .note, #body .updated';
		var nodes = qa(selector, scope);

		if (root && root.nodeType === 1 && root.matches &&
			root.matches('.abg-feedback-error, .abg-feedback-warning, .abg-feedback-success, .error, .warning, .note, .updated') &&
			root.closest('#body')) {
			nodes.unshift(root);
		}

		nodes.forEach(function (node) {
			if (node.dataset.abgEnhanced === '1') return;
			if (node.closest('.abg-modal, .abg-toast')) return;

			node.dataset.abgEnhanced = '1';
			var html = cleanMessageHTML(node);
			if (!html) return;

			if (node.classList.contains('abg-feedback-warning') || node.classList.contains('warning')) {
				node.hidden = true;
				queueModal(html, 'warning');
			} else if (node.classList.contains('abg-feedback-success') ||
				node.classList.contains('note') ||
				node.classList.contains('updated')) {
				node.hidden = true;
				queueModal(html, 'success');
			} else if (node.classList.contains('abg-feedback-error') || node.classList.contains('error')) {
				node.hidden = true;
				queueModal(html, 'error');
			}
		});
	}

	function wrapWideTables(root) {
		var scope = root && root.querySelectorAll ? root : document;
		var tables = qa('#body table', scope);

		if (root && root.nodeType === 1 && root.matches && root.matches('table') && root.closest('#body')) {
			tables.unshift(root);
		}

		tables.forEach(function (table) {
			if (table.dataset.abgResponsive === '1') return;
			if (table.closest('.abg-table-scroll')) return;

			table.dataset.abgResponsive = '1';
			var wrapper = document.createElement('div');
			wrapper.className = 'abg-table-scroll';
			wrapper.style.maxWidth = '100%';
			wrapper.style.overflowX = 'auto';
			wrapper.style.webkitOverflowScrolling = 'touch';
			table.parentNode.insertBefore(wrapper, table);
			wrapper.appendChild(table);
		});
	}

	function refresh(root) {
		ensureShellHelpers();
		enhanceMessages(root || document);
		wrapWideTables(root || document);
	}

	function initialize() {
		if (initialized) return;
		initialized = true;
		normalizeResponsiveShell();
		refresh(document);

		var observer = new MutationObserver(function (mutations) {
			mutations.forEach(function (mutation) {
				mutation.addedNodes.forEach(function (node) {
					if (node.nodeType === 1) refresh(node);
				});
			});
		});

		observer.observe(document.body, { childList: true, subtree: true });

		window.addEventListener('resize', function () {
			normalizeResponsiveShell();
			if (!window.matchMedia('(max-width: 874px)').matches) closeMenu();
		});

		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape' && document.body.classList.contains('abg-menu-open')) {
				closeMenu();
				var button = q('#BottomButtonMenu');
				if (button) button.focus();
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initialize);
	} else {
		initialize();
	}
})();
