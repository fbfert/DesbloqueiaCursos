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

/* Home: trilha da abertura (adiada ate estar inteira na tela), numeros, estante e ranking. */
Caderno.pagina('home', function () {
  var C = window.Caderno, leve = C.leve;
  var hero = document.querySelector('.hero');
  if (!hero) return;
  void hero.offsetWidth; // estilo inicial calculado antes da troca: a transicao do marca-texto acontece
  hero.classList.add('aberta');

  C.aoVer(hero.querySelector('.numeros'), function (el) {
    var ns = el.querySelectorAll('[data-n]');
    for (var i = 0; i < ns.length; i++) C.contar(ns[i], leve ? 700 : 1300);
  });

  var trilha = hero.querySelector('.trilha');
  if (trilha) {
    // a barra inferior do celular cobre o pe da tela: "inteira" desconta a altura dela
    var bnav = document.querySelector('.bnav'), pe = bnav ? bnav.offsetHeight : 0;
    var r = trilha.getBoundingClientRect();
    var base = r.top >= 0 && r.bottom <= innerHeight - pe ? 650 : 150; // ja visivel: espera o titulo assentar
    C.aoVer(trilha, function (t) { cena(t, base); }, { threshold: 0.9, rootMargin: '0px 0px -' + pe + 'px 0px' });
  }

  function cena(t, base) {
    var aros = t.querySelectorAll('.aro'), oks = t.querySelectorAll('.ok'), rots = t.querySelectorAll('.rotulo');
    var dur = leve ? 1400 : 2400, passos = leve ? [560, 1080] : [900, 1700];
    C.tracar(aros[0], { duration: 380, delay: base });
    C.animar(rots[0], [{ opacity: 0 }, { opacity: 1 }], { duration: 300, delay: base + 150 });
    C.tracar(oks[0], { duration: 220, delay: base + 420, easing: 'ease-in' });
    C.tracar(t.querySelector('.cam'), { duration: dur, delay: base + 380 });
    for (var k = 0; k < 2; k++) {
      var d = base + 380 + passos[k];
      C.tracar(aros[k + 1], { duration: 340, delay: d - 160 });
      C.animar(rots[k + 1], [{ opacity: 0, transform: 'translateY(4px)' }, { opacity: 1, transform: 'none' }], { duration: 320, delay: d });
      C.tracar(oks[k + 1], { duration: 220, delay: d + 240, easing: 'ease-in' });
    }
    var fim = base + 380 + dur;
    C.animar(t.querySelector('.anot'), [{ opacity: 0 }, { opacity: 1 }], { duration: 400, delay: fim - 500 });
    C.carimbar(t.querySelector('.carimbo'), fim + 80);
    t.classList.add('em-cena'); // os quadros iniciais (fill both) ja seguram o estado oculto
  }

  if (leve) return; // modo leve: so a cena principal

  C.aoVer(document.querySelector('[data-cena=estante]'), function (el) {
    var ls = el.querySelectorAll('.lomb');
    for (var i = 0; i < ls.length; i++) {
      C.animar(ls[i], [{ transform: 'translateY(110%)', opacity: 0 }, { transform: 'none', opacity: 1 }], { duration: 700, delay: i * 70, fill: 'backwards' });
    }
  });

  C.aoVer(document.querySelector('[data-cena=ranking]'), function (el) {
    var bs = el.querySelectorAll('.barra');
    for (var i = 0; i < bs.length; i++) {
      C.animar(bs[i], [{ transform: 'scaleX(0)' }, { transform: 'scaleX(1)' }], { duration: 800, delay: 150 + i * 90, easing: 'cubic-bezier(.6,0,.3,1)', fill: 'backwards' });
    }
  });
});
