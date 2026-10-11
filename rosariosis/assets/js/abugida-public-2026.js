(function(){
	'use strict';

	function qa(selector, root){
		return Array.prototype.slice.call((root || document).querySelectorAll(selector));
	}

	function clean(node){
		var clone=node.cloneNode(true);
		qa('script,style',clone).forEach(function(el){el.remove();});
		return clone.innerHTML.trim();
	}

	function show(message,type){
		var success=type==='success';
		var backdrop=document.createElement('div');
		backdrop.className='abg-public-modal-backdrop';
		backdrop.innerHTML=
			'<section class="abg-public-modal abg-public-modal--'+(success?'success':'error')+'" role="dialog" aria-modal="true" aria-labelledby="abg-public-modal-title">'+
			'<div class="abg-public-modal__head">'+
			'<div class="abg-public-modal__icon" aria-hidden="true">'+(success?'&#10003;':'&times;')+'</div>'+
			'<div><h2 id="abg-public-modal-title">'+(success?'Success':'Please review')+'</h2>'+
			'<p>'+(success?'Your request was completed successfully.':'There is something that needs your attention.')+'</p></div>'+
			'</div>'+
			'<div class="abg-public-modal__message">'+message+'</div>'+
			'<div class="abg-public-modal__actions"><button type="button">OK</button></div>'+
			'</section>';

		var previous=document.activeElement;
		document.body.classList.add('abg-public-modal-open');
		document.body.appendChild(backdrop);
		var dialog=backdrop.querySelector('.abg-public-modal');
		var button=backdrop.querySelector('button');

		function close(){
			document.removeEventListener('keydown',onKey,true);
			backdrop.remove();
			document.body.classList.remove('abg-public-modal-open');
			if(previous&&typeof previous.focus==='function') previous.focus();
		}

		function onKey(event){
			if(event.key==='Escape'){event.preventDefault();close();return;}
			if(event.key==='Tab'){
				var focusable=qa('button,[href],input,select,textarea,[tabindex]:not([tabindex="-1"])',dialog)
					.filter(function(el){return !el.disabled&&el.offsetParent!==null;});
				if(!focusable.length)return;
				var first=focusable[0],last=focusable[focusable.length-1];
				if(event.shiftKey&&document.activeElement===first){event.preventDefault();last.focus();}
				else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first.focus();}
			}
		}

		button.addEventListener('click',close);
		backdrop.addEventListener('click',function(event){if(event.target===backdrop)close();});
		document.addEventListener('keydown',onKey,true);
		window.setTimeout(function(){button.focus();},20);
	}

	function enhance(){
		qa('.alert.error, .error').forEach(function(node){
			if(node.dataset.abgPublicEnhanced==='1'||node.closest('.abg-public-modal'))return;
			node.dataset.abgPublicEnhanced='1';
			var html=clean(node);
			if(!html)return;
			node.hidden=true;
			show(html,'error');
		});
		qa('.alert.ok, .notice').forEach(function(node){
			if(node.dataset.abgPublicEnhanced==='1'||node.closest('.abg-public-modal'))return;
			node.dataset.abgPublicEnhanced='1';
			var html=clean(node);
			if(!html)return;
			node.hidden=true;
			show(html,'success');
		});
	}

	if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',enhance);
	else enhance();
})();