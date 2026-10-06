/* Tema caderno: nucleo de movimento com APIs nativas (Web Animations + IntersectionObserver).
   Cada pagina registra sua cena com Caderno.pagina('nome', init). Sem bibliotecas. */
(function () {
  'use strict';
  var html = document.documentElement;
  var reduzido = matchMedia('(prefers-reduced-motion: reduce)').matches;
  var leve = !!((navigator.connection && navigator.connection.saveData) || (navigator.hardwareConcurrency || 8) <= 2);
  var EASE = 'cubic-bezier(.2,.8,.2,1)';

  function animar(el, keyframes, opcoes) {
    if (reduzido || !el || !el.animate) return null;
    return el.animate(keyframes, Object.assign({ fill: 'both', easing: EASE }, opcoes));
  }

  function tracar(path, opcoes) {
    if (reduzido || !path || !path.getTotalLength) return null;
    var L = path.getTotalLength();
    path.style.strokeDasharray = L;
    return animar(path, [{ strokeDashoffset: L }, { strokeDashoffset: 0 }], Object.assign({ easing: 'linear' }, opcoes));
  }

  function contar(el, duracaoMs) {
    if (!el) return;
    if (!el.dataset.n) return;
    var alvo = +el.dataset.n || 0;
    if (reduzido) { el.textContent = alvo.toLocaleString('pt-BR'); return; }
    var t0 = performance.now();
    (function passo(t) {
      var p = Math.min(1, (t - t0) / duracaoMs), e = 1 - Math.pow(1 - p, 3);
      el.textContent = Math.round(alvo * e).toLocaleString('pt-BR');
      if (p < 1) requestAnimationFrame(passo);
    })(t0);
  }

  // Carimbo cai de cima com quique; no modo normal o papel (pai) treme.
  function carimbar(el, atrasoMs) {
    if (!el) return;
    var d = atrasoMs || 0;
    animar(el, [
      { opacity: 0, transform: 'scale(2.3) rotate(-32deg)' },
      { opacity: .92, transform: 'scale(1) rotate(-14deg)', offset: .78 },
      { opacity: .92, transform: 'scale(1.04) rotate(-14deg)', offset: .88 },
      { opacity: .92, transform: 'scale(1) rotate(-14deg)' }
    ], { duration: 420, delay: d, easing: 'cubic-bezier(.55,0,.9,.4)' });
    if (!leve) animar(el.parentNode, [
      { transform: 'none' }, { transform: 'translate(-3px,2px)' }, { transform: 'translate(2px,-1px)' },
      { transform: 'translate(-1px,1px)' }, { transform: 'none' }
    ], { duration: 220, delay: d + 330, easing: 'linear', fill: 'none' });
  }

  // Observa uma vez so: chama callback(el) na primeira vez que entra na tela.
  function aoVer(el, callback, opcoes) {
    if (!el) return;
    if (!window.IntersectionObserver) { callback(el); return; }
    var io = new IntersectionObserver(function (es) {
      for (var i = 0; i < es.length; i++) {
        if (!es[i].isIntersecting) continue;
        io.unobserve(es[i].target);
        callback(es[i].target);
      }
    }, opcoes);
    io.observe(el);
  }

  function revelar() {
    var itens = document.querySelectorAll('.rv');
    for (var i = 0; i < itens.length; i++) {
      if (leve) { itens[i].style.transition = 'none'; itens[i].classList.add('visto'); } // leve: ja visivel, sem animacao
      else aoVer(itens[i], function (el) { el.classList.add('visto'); });
    }
  }

  function pagina(nome, init) {
    if (reduzido || !document.body || document.body.dataset.pagina !== nome) return;
    try { init(); } catch (e) { html.classList.remove('anima'); }
  }

  window.Caderno = {
    animar: animar, tracar: tracar, contar: contar, carimbar: carimbar,
    aoVer: aoVer, pagina: pagina, leve: leve, reduzido: reduzido
  };

  // variavel animavel usada em marca-texto e barras
  if (!reduzido && window.CSS && CSS.registerProperty) {
    try { CSS.registerProperty({ name: '--mt', syntax: '<number>', inherits: false, initialValue: '1' }); } catch (e) {}
  }

  if (reduzido) { html.classList.remove('anima'); clearTimeout(window.CADERNO_TRAVA); return; }
  revelar();
  clearTimeout(window.CADERNO_TRAVA); // iniciou com sucesso: a trava de 2,5 s do layout nao e mais necessaria
})();
