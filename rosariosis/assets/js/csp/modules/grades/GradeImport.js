(function () {
	function initAbugidaGradeImport() {
		var form = document.getElementById('abugida-grade-import-form');
		var grade = document.getElementById('abugida-grade-id');
		var action = document.getElementById('abugida-grade-import-action');

		if (!form || !grade || !action || form.dataset.abugidaGradeImportReady === '1') {
			return;
		}

		form.dataset.abugidaGradeImportReady = '1';

		grade.addEventListener('change', function () {
			action.value = 'load_context';
			form.submit();
		});

		form.addEventListener('submit', function (event) {
			var submitter = event.submitter;

			if (submitter && submitter.getAttribute('data-grade-import-action')) {
				action.value = submitter.getAttribute('data-grade-import-action');
			}
		});
	}

	// Full page navigation.
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initAbugidaGradeImport);
	} else {
		initAbugidaGradeImport();
	}

	// RosarioSIS sidebar navigation injects module HTML after DOMContentLoaded,
	// so run once immediately when this script is loaded through AJAX as well.
	initAbugidaGradeImport();
})();
