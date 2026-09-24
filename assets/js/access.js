(() => {
  'use strict';
  const token = document.querySelector('meta[name="csrf-token"]')?.content;
  const role = document.querySelector('meta[name="user-role"]')?.content;
  if (!token) return;
  const originalFetch = window.fetch;
  window.fetch = function(input, init = {}) {
    const url = new URL(typeof input === 'string' || input instanceof URL ? input : input.url, location.href);
    if (url.origin === location.origin) {
      const headers = new Headers(init.headers || (input instanceof Request ? input.headers : undefined));
      headers.set('X-CSRF-Token', token);
      init = {...init, headers};
    }
    return originalFetch.call(this, input, init);
  };
  const open = XMLHttpRequest.prototype.open, send = XMLHttpRequest.prototype.send;
  XMLHttpRequest.prototype.open = function(method, url, ...args) {
    this.uiatSameOrigin = new URL(url, location.href).origin === location.origin;
    return open.call(this, method, url, ...args);
  };
  XMLHttpRequest.prototype.send = function(body) {
    if(this.uiatSameOrigin)this.setRequestHeader('X-CSRF-Token', token);
    return send.call(this, body);
  };
  function prepare() {
    document.querySelectorAll('form[method="post" i]').forEach(form => {
      if (new URL(form.action || location.href, location.href).origin !== location.origin) return;
      if (!form.querySelector('[name="_csrf"]')) {
        const field = document.createElement('input');field.type='hidden';field.name='_csrf';field.value=token;form.append(field);
      }
    });
    if(role !== 'admin')document.querySelectorAll('a[href]').forEach(link => {
      if (/eliminar\.php|delete\.php/.test(link.getAttribute('href'))) link.hidden=true;
    });
    if(role==='adjunto')document.querySelectorAll('a[href*="accidente_nuevo.php"]').forEach(link=>link.hidden=true);
    if(!['admin','jefe_emi','adjunto'].includes(role))document.querySelectorAll('a[href]').forEach(link => {
      if(/(?:_nuevo|_editar)\.php/.test(link.getAttribute('href')))link.hidden=true;
    });
  }
  document.addEventListener('DOMContentLoaded', () => {prepare();const observer=new MutationObserver(prepare);observer.observe(document.body,{childList:true,subtree:true});});
})();
