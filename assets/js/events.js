/* =====================================================================
   CLUB MEDIA — ISET GAFSA  |  Page Événements & galeries : filtres
   Amélioration progressive : le formulaire GET fonctionne sans JavaScript.
   ===================================================================== */
(function () {
  'use strict';
  var forms = document.querySelectorAll('form[data-autofilter]');
  Array.prototype.forEach.call(forms, function (form) {
    // Envoi automatique à chaque changement de liste / date
    Array.prototype.forEach.call(form.querySelectorAll('select, input[type=date]'), function (el) {
      el.addEventListener('change', function () { form.submit(); });
    });
    // Recherche : envoi après une courte pause de frappe
    var search = form.querySelector('input[type=search]');
    if (search) {
      var timer = null;
      search.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(function () { form.submit(); }, 650);
      });
      // Replace le curseur à la fin après rechargement si la recherche avait le focus
      if (window.location.search.indexOf('q=') !== -1 && sessionStorage && sessionStorage.getItem('cm_focus_' + form.id) === '1') {
        search.focus();
        var v = search.value; search.value = ''; search.value = v;
      }
      search.addEventListener('focus', function () { try { sessionStorage.setItem('cm_focus_' + form.id, '1'); } catch (e) {} });
      search.addEventListener('blur', function () { try { sessionStorage.removeItem('cm_focus_' + form.id); } catch (e) {} });
    }
  });
})();
