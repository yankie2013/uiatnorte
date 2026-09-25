(() => {
 const key = 'listado-general:scroll';
 const context = () => { const u = new URL(location.href); u.searchParams.delete('ok'); u.searchParams.sort(); return u.pathname + u.search; };
 let saved;
 try { saved = JSON.parse(sessionStorage.getItem(key)); sessionStorage.removeItem(key); } catch (_) {}
 if (saved && saved.context === context() && Date.now() - saved.time < 120000) {
  const restore = () => {
   const form = [...document.querySelectorAll('.js-responsible-form')].find(f => f.elements.expediente_id.value === saved.id);
   const row = form?.closest('tr');
   const y = row ? scrollY + row.getBoundingClientRect().top - saved.top : saved.y;
   window.scrollTo({left: saved.x, top: y, behavior: 'instant'});
   const table = document.querySelector('.table-scroll');
   if (table) table.scrollLeft = saved.tableX;
  };
  restore();
  if (document.readyState !== 'complete') window.addEventListener('load', restore, {once:true});
 }
 document.querySelectorAll('.js-responsible-form').forEach(form => {
  let submitting = false;
  form.addEventListener('submit', event => {
   if (submitting) { event.preventDefault(); return; }
   try { sessionStorage.setItem(key, JSON.stringify({context:context(), time:Date.now(), id:form.elements.expediente_id.value, top:form.closest('tr').getBoundingClientRect().top, x:scrollX, y:scrollY, tableX:document.querySelector('.table-scroll')?.scrollLeft || 0})); } catch (_) {}
   submitting = true;
  });
 });
})();
