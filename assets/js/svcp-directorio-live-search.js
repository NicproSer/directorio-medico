(function(){
  'use strict';
  function normalize(value){return (value||'').toString().normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().trim();}
  document.addEventListener('DOMContentLoaded',function(){
    var form=document.querySelector('.svcp-directorio-buscador-principal');
    var input=document.getElementById('svcp-doctor-search');
    var select=document.getElementById('svcp-region-filter');
    var list=document.querySelector('.svcp-lista-doctores');
    if(!form||!input||!select||!list)return;
    var cards=Array.prototype.slice.call(list.querySelectorAll('[data-svcp-doctor-card]'));
    var summary=document.querySelector('.svcp-resultados-resumen');
    var originalCount=cards.length;
    var empty=document.createElement('div');
    empty.className='svcp-sin-resultados-box';
    empty.innerHTML='<h2>No encontramos resultados</h2><p>Prueba con otro nombre o selecciona otra región.</p>';
    empty.hidden=true;
    list.parentNode.insertBefore(empty,list.nextSibling);
    var status=document.createElement('p');status.className='svcp-live-search-status';status.setAttribute('aria-live','polite');
    if(summary)summary.parentNode.insertBefore(status,summary.nextSibling);
    function filter(){
      var q=normalize(input.value),region=normalize(select.value),visible=0;
      cards.forEach(function(card){
        var matchText=!q||normalize(card.getAttribute('data-search')).indexOf(q)!==-1;
        var matchRegion=!region||normalize(card.getAttribute('data-region'))===region||normalize(card.getAttribute('data-region'))===normalize(select.options[select.selectedIndex].text);
        var show=matchText&&matchRegion;card.hidden=!show;if(show)visible++;
      });
      empty.hidden=visible!==0;list.hidden=visible===0;
      status.textContent=visible+' '+(visible===1?'médico encontrado':'médicos encontrados');
      if(summary)summary.hidden=true;
      var pagination=document.querySelector('.svcp-paginacion');if(pagination)pagination.hidden=true;
    }
    input.addEventListener('input',filter);
    select.addEventListener('change',filter);
    form.addEventListener('submit',function(event){event.preventDefault();filter();});
    document.querySelectorAll('.svcp-region-card').forEach(function(link){link.addEventListener('click',function(event){var url=new URL(link.href);var slug=url.searchParams.get('region');var option=Array.prototype.find.call(select.options,function(o){return o.value===slug||normalize(o.text)===normalize(link.querySelector('.svcp-region-card-name')&&link.querySelector('.svcp-region-card-name').textContent);});if(option){event.preventDefault();select.value=option.value;filter();document.querySelector('.svcp-directorio-buscador-principal').scrollIntoView({behavior:'smooth',block:'center'});}});});
    filter();
  });
})();
