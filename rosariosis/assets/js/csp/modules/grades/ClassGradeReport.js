(function () {
	function initAbugidaClassGradeReport() {
		var form = document.getElementById('abugida-class-grade-report-form');
		var grade = document.getElementById('abugida-class-grade-report-grade');

		if (!form || !grade || form.dataset.abugidaClassReportReady === '1') {
			return;
		}

		form.dataset.abugidaClassReportReady = '1';

		grade.addEventListener('change', function () {
			var course = form.querySelector('select[name="course_period_id"]');

			if (course) {
				course.value = '';
			}

			form.submit();
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initAbugidaClassGradeReport);
	} else {
		initAbugidaClassGradeReport();
	}

	initAbugidaClassGradeReport();
})();
