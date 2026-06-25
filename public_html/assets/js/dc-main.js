/* ============================================================
   DESBLOQUEIA CURSOS — JS V4
   Vanilla JS, sem dependências
   ============================================================ */

document.addEventListener('DOMContentLoaded', function () {

  // 1. Chips de categoria — toggle ativo
  document.querySelectorAll('.dc-chip').forEach(function (chip) {
    chip.addEventListener('click', function () {
      var row = chip.closest('.dc-chips-row');
      if (row) row.querySelectorAll('.dc-chip').forEach(function (c) { c.classList.remove('on'); });
      chip.classList.toggle('on');
    });
  });

  // 2. Tabs internas (Meus Cursos, LMS etc.)
  document.querySelectorAll('[data-dc-tab]').forEach(function (tab) {
    tab.addEventListener('click', function () {
      var group = tab.dataset.dcTabGroup;
      var target = tab.dataset.dcTab;
      // desativa todos do grupo
      document.querySelectorAll('[data-dc-tab-group="' + group + '"]')
        .forEach(function (t) { t.classList.remove('on'); });
      document.querySelectorAll('[data-dc-panel-group="' + group + '"]')
        .forEach(function (p) { p.style.display = 'none'; });
      // ativa o clicado
      tab.classList.add('on');
      var panel = document.querySelector('[data-dc-panel="' + target + '"]');
      if (panel) panel.style.display = 'block';
    });
  });

  // 3. Accordion de módulos (LMS)
  document.querySelectorAll('.dc-mod-head').forEach(function (head) {
    head.addEventListener('click', function () {
      var items = head.nextElementSibling;
      if (!items || !items.classList.contains('dc-mod-items')) return;
      var open = items.classList.toggle('open');
      var chev = head.querySelector('.dc-mod-chevron');
      if (chev) chev.style.transform = open ? 'rotate(180deg)' : '';
    });
  });

  // 4. Toggle senha (mostrar/ocultar)
  document.querySelectorAll('[data-dc-toggle-pass]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var target = document.querySelector(btn.dataset.dcTogglePass);
      if (!target) return;
      var isPass = target.type === 'password';
      target.type = isPass ? 'text' : 'password';
      var icon = btn.querySelector('i');
      if (icon) {
        icon.className = isPass ? 'ti ti-eye-off' : 'ti ti-eye';
      }
    });
  });

  // 5. Bottom sheet de filtros
  var filterBtn = document.querySelector('[data-dc-open-filters]');
  var filterSheet = document.querySelector('.dc-filter-sheet');
  var filterOverlay = document.querySelector('.dc-filter-overlay');
  function closeFilters() {
    if (filterSheet) filterSheet.classList.remove('open');
    if (filterOverlay) filterOverlay.classList.remove('open');
  }
  if (filterBtn && filterSheet) {
    filterBtn.addEventListener('click', function () {
      filterSheet.classList.add('open');
      if (filterOverlay) filterOverlay.classList.add('open');
    });
  }
  if (filterOverlay) filterOverlay.addEventListener('click', closeFilters);
  document.querySelectorAll('[data-dc-close-filters]').forEach(function (btn) {
    btn.addEventListener('click', closeFilters);
  });

  // 6. Upload de comprovante — preview
  var uploadInput = document.querySelector('#dc-comprovante-input');
  var uploadArea = document.querySelector('#dc-upload-area');
  var uploadPreview = document.querySelector('#dc-upload-preview');
  var uploadName = document.querySelector('#dc-upload-name');
  var uploadSize = document.querySelector('#dc-upload-size');
  var uploadRemove = document.querySelector('#dc-upload-remove');
  var submitBtn = document.querySelector('#dc-submit-comprovante');

  if (uploadInput) {
    uploadInput.addEventListener('change', function () {
      var file = uploadInput.files[0];
      if (!file) return;
      if (uploadArea) uploadArea.style.display = 'none';
      if (uploadPreview) uploadPreview.style.display = 'flex';
      if (uploadName) uploadName.textContent = file.name;
      if (uploadSize) uploadSize.textContent = (file.size / 1024).toFixed(0) + ' KB';
      if (submitBtn) submitBtn.disabled = false;
    });
    if (uploadArea) {
      uploadArea.addEventListener('click', function () { uploadInput.click(); });
    }
  }
  if (uploadRemove) {
    uploadRemove.addEventListener('click', function () {
      if (uploadInput) uploadInput.value = '';
      if (uploadArea) uploadArea.style.display = 'flex';
      if (uploadPreview) uploadPreview.style.display = 'none';
      if (submitBtn) submitBtn.disabled = true;
    });
  }

  // 7. Stepper do checkout — marcar passo ativo via data-step
  var stepperSteps = document.querySelectorAll('[data-dc-step]');
  var currentStep = document.querySelector('[data-dc-current-step]');
  if (stepperSteps.length && currentStep) {
    var current = parseInt(currentStep.dataset.dcCurrentStep, 10);
    stepperSteps.forEach(function (step) {
      var n = parseInt(step.dataset.dcStep, 10);
      step.classList.remove('done', 'active', 'pending');
      if (n < current) step.classList.add('done');
      else if (n === current) step.classList.add('active');
      else step.classList.add('pending');
    });
  }

  // 8. Copiar chave PIX
  var copyBtn = document.querySelector('[data-dc-copy-pix]');
  if (copyBtn) {
    copyBtn.addEventListener('click', function () {
      var chave = copyBtn.dataset.dcCopyPix;
      if (navigator.clipboard) {
        navigator.clipboard.writeText(chave).then(function () {
          copyBtn.textContent = 'Copiado!';
          setTimeout(function () { copyBtn.innerHTML = '<i class="ti ti-copy"></i> Copiar chave PIX'; }, 2000);
        });
      }
    });
  }

});
