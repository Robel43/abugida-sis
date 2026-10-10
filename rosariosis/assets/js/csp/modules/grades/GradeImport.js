document.addEventListener('DOMContentLoaded', function () {
	var form = document.getElementById('abugida-grade-import-form');
	var grade = document.getElementById('abugida-grade-id');
	var action = document.getElementById('abugida-grade-import-action');

	if (!form || !grade || !action) {
		return;
	}

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
});
