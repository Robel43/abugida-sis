(function () {
 function initClassRank() {
  var form = document.getElementById('abugida-class-rank-form');
  var action = document.getElementById('abugida-class-rank-action');
  if (!form || !action || form.dataset.classRankReady === '1') return;
  form.dataset.classRankReady = '1';
  // The SIS AJAX layer builds FormData without including the clicked button.
  form.addEventListener('submit', function (event) {
   action.value = event.submitter ? event.submitter.getAttribute('data-rank-action') || 'view' : 'view';
  });
 }
 if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initClassRank);
 initClassRank();
})();

