/* Event Setup admin — small progressive enhancements. No frameworks. */
(function () {
  'use strict';

  // Confirm dialogs on destructive forms/buttons.
  document.addEventListener('submit', function (e) {
    var form = e.target;
    var btn = e.submitter;
    var msg = (btn && btn.getAttribute('data-confirm')) || form.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) { e.preventDefault(); return; }
    // Prevent double submits.
    if (btn && !btn.disabled) setTimeout(function () { btn.disabled = true; }, 0);
  });

  // Selects that save on change (status pickers, filters).
  document.querySelectorAll('select[data-autosubmit]').forEach(function (sel) {
    sel.addEventListener('change', function () { sel.form && sel.form.submit(); });
  });

  // Key/value rows on the details tab.
  document.querySelectorAll('[data-add-row]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var box = document.getElementById(btn.getAttribute('data-add-row'));
      var last = box && box.querySelector('.kv__row:last-child');
      if (!last) return;
      var clone = last.cloneNode(true);
      clone.querySelectorAll('input').forEach(function (i) { i.value = ''; });
      box.appendChild(clone);
      clone.querySelector('input').focus();
    });
  });
  document.addEventListener('click', function (e) {
    var rm = e.target.closest('[data-remove-row]');
    if (!rm) return;
    var row = rm.closest('.kv__row');
    var box = row && row.parentNode;
    if (box && box.querySelectorAll('.kv__row').length > 1) row.remove();
    else if (row) row.querySelectorAll('input').forEach(function (i) { i.value = ''; });
  });

  // Show chosen file names next to file inputs, and warn early on oversize images.
  document.querySelectorAll('input[type=file]').forEach(function (inp) {
    inp.addEventListener('change', function () {
      var files = Array.prototype.slice.call(inp.files || []);
      var label = inp.closest('.field') ? inp.closest('.field').querySelector('.field__help') : null;
      var note = inp.parentNode.querySelector('.filenote');
      if (!note) { note = document.createElement('span'); note.className = 'filenote'; inp.parentNode.appendChild(note); }
      note.textContent = files.length ? files.length + ' file' + (files.length === 1 ? '' : 's') + ' chosen: ' + files.map(function (f) { return f.name; }).join(', ') : '';
      void label;
    });
  });

  // Auto-fill the slug from the title while the slug is still the default.
  var title = document.querySelector('input[name=title]');
  var slug = document.querySelector('input[name=slug]');
  if (title && slug && /^new-event(-\d+)?$/.test(slug.value)) {
    title.addEventListener('input', function () {
      slug.value = title.value.toLowerCase().replace(/&/g, 'and').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    });
  }

  // Flash messages fade after a while.
  document.querySelectorAll('.flash--ok').forEach(function (f) {
    setTimeout(function () { f.classList.add('is-gone'); }, 6000);
  });
}());
