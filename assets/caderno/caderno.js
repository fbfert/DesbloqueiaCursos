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
  // `graus`: inclinação final (padrão -14, a do carimbo redondo).
  function carimbar(el, atrasoMs, graus) {
    if (!el) return;
    var d = atrasoMs || 0, g = graus == null ? -14 : graus;
    animar(el, [
      { opacity: 0, transform: 'scale(2.3) rotate(' + (g - 18) + 'deg)' },
      { opacity: .92, transform: 'scale(1) rotate(' + g + 'deg)', offset: .78 },
      { opacity: .92, transform: 'scale(1.04) rotate(' + g + 'deg)', offset: .88 },
      { opacity: .92, transform: 'scale(1) rotate(' + g + 'deg)' }
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

  // Cena da pagina. Com `sempre`, roda tambem em movimento reduzido: e o caso de
  // modulos com funcao (folha de filtros, busca), que so cortam a animacao.
  // Se o init falhar, volta tudo ao estado sem JS (.js sai: conteudo inline).
  function pagina(nome, init, sempre) {
    if ((reduzido && !sempre) || !document.body || document.body.dataset.pagina !== nome) return;
    try { init(); } catch (e) { html.classList.remove('anima', 'js'); }
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
    // A cena espera a trilha "inteira" na tela. A barra inferior do celular cobre o pe
    // da tela, entao a area util desconta a altura dela. Em tela baixa (celular deitado)
    // a trilha nao cabe: basta ocupar 3/4 da area util. Se a pessoa rolar alem
    // da trilha, a cena roda do mesmo jeito. Sem rolagem, um relogio garante a cena:
    // trilha quase inteira (3/4) em 1,5 s; qualquer parte na tela em 4,5 s. Trilha toda
    // abaixo da dobra continua esperando a rolagem: a trilha nunca fica oculta na tela.
    var bnav = document.querySelector('.bnav'), base = 0, pedido = 0, feito = false, relogio = 0;
    var medir = function () {
      var util = innerHeight - (bnav ? bnav.offsetHeight : 0), r = trilha.getBoundingClientRect();
      if (!base) base = r.top >= 0 && r.bottom <= util ? 650 : 150; // ja visivel: espera o titulo assentar
      return { r: r, util: util, visivel: Math.min(r.bottom, util) - Math.max(r.top, 0) };
    };
    var iniciar = function () {
      feito = true;
      clearTimeout(relogio);
      removeEventListener('scroll', agendar);
      removeEventListener('resize', agendar);
      cena(trilha, base);
    };
    var verificar = function () {
      pedido = 0;
      if (feito) return;
      var m = medir();
      if (m.r.bottom < 0 || m.visivel >= Math.min(m.r.height * 0.9, m.util * 0.75)) iniciar();
    };
    var forcar = function (fracao) {
      if (feito) return false;
      var m = medir();
      if (m.visivel > 0 && m.visivel >= m.r.height * fracao) { iniciar(); return true; }
      return false;
    };
    var agendar = function () { if (!pedido) pedido = requestAnimationFrame(verificar); };
    addEventListener('scroll', agendar, { passive: true });
    addEventListener('resize', agendar);
    relogio = setTimeout(function () {
      if (!forcar(0.75)) relogio = setTimeout(function () { forcar(0); }, 3000);
    }, 1500);
    verificar();
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

/* Catalogo: folha de filtros (foco preso, Esc fecha, foco volta ao botao). Busca
   instantanea e troca de divisoria no navegador so quando todos os cursos ja estao
   na pagina (o servidor marca data-busca-local / data-categoria-local); fora disso,
   o form e os links GET seguem para o servidor. FLIP na troca, exceto leve/reduzido.
   Roda tambem em movimento reduzido (sempre=true): so a animacao e cortada. */
Caderno.pagina('catalogo', function () {
  var C = window.Caderno, html = document.documentElement;
  var form = document.getElementById('cat-form');
  if (!form) return;

  // ---------- folha que sobe ----------
  var botao = form.querySelector('[data-abrir-filtros]'), folha = form.querySelector('[data-filtros]');
  var papel = folha && folha.querySelector('.papel'), relogio = 0;
  // So vira dialogo se html.js ainda estiver ai. Se este script chegou depois da trava
  // de 2,5 s, os filtros ja estao inline (botao oculto): ficam como regiao comum do
  // form, sem role/aria-modal (que esconderia o resto da pagina do leitor de tela).
  if (botao && papel && html.classList.contains('js')) {
    papel.setAttribute('role', 'dialog');
    papel.setAttribute('aria-modal', 'true');
    papel.setAttribute('aria-labelledby', 'cat-filtros-tit');
    papel.setAttribute('tabindex', '-1');
    var focaveis = function () {
      var l = papel.querySelectorAll('button,[href],input,select,textarea'), r = [];
      for (var i = 0; i < l.length; i++) if (!l[i].disabled && l[i].getClientRects().length) r.push(l[i]);
      return r;
    };
    var teclas = function (e) {
      if (e.key === 'Escape' || e.keyCode === 27) { e.preventDefault(); fechar(); return; }
      if (e.key !== 'Tab' && e.keyCode !== 9) return;
      var f = focaveis(), a = document.activeElement;
      if (!f.length) return;
      if (e.shiftKey && (a === f[0] || a === papel)) { e.preventDefault(); f[f.length - 1].focus(); }
      else if (!e.shiftKey && a === f[f.length - 1]) { e.preventDefault(); f[0].focus(); }
      else if (!papel.contains(a)) { e.preventDefault(); f[0].focus(); }
    };
    var abrir = function () {
      clearTimeout(relogio);
      folha.classList.add('aberta');
      html.classList.add('folha-aberta');
      botao.setAttribute('aria-expanded', 'true');
      void papel.offsetWidth; // estado inicial calculado: a transicao de subida acontece
      folha.classList.add('visivel');
      papel.focus();
      document.addEventListener('keydown', teclas);
    };
    var fechar = function () {
      if (!folha.classList.contains('visivel')) return;
      folha.classList.remove('visivel');
      botao.setAttribute('aria-expanded', 'false');
      document.removeEventListener('keydown', teclas);
      relogio = setTimeout(function () { folha.classList.remove('aberta'); html.classList.remove('folha-aberta'); }, C.reduzido ? 0 : 240);
      botao.focus();
    };
    botao.addEventListener('click', abrir);
    folha.addEventListener('click', function (e) {
      var t = e.target;
      while (t && t !== folha) { if (t.hasAttribute && t.hasAttribute('data-fechar')) { fechar(); return; } t = t.parentNode; }
    });
    // Voltar pelo historico (bfcache) nunca restaura a folha aberta.
    addEventListener('pageshow', function (e) {
      if (!e.persisted) return;
      clearTimeout(relogio);
      document.removeEventListener('keydown', teclas);
      folha.classList.remove('visivel', 'aberta');
      html.classList.remove('folha-aberta');
      botao.setAttribute('aria-expanded', 'false');
    });
  }

  // ---------- busca e divisorias no navegador ----------
  var campo = document.getElementById('cat-busca'), campoCat = form.querySelector('[data-campo-categoria]');
  var abas = form.querySelector('[data-abas]'), grade = document.getElementById('cat-grade');
  var vazio = document.getElementById('cat-vazio'), resumo = document.getElementById('cat-resumo'), titulo = document.getElementById('cat-titulo');
  var buscaLocal = form.getAttribute('data-busca-local') === '1', catLocal = form.getAttribute('data-categoria-local') === '1';

  // No celular as divisorias rolam: a ativa comeca a vista.
  var ativa = abas && abas.querySelector('[aria-current]');
  if (ativa && abas.scrollWidth > abas.clientWidth) {
    var ra = ativa.getBoundingClientRect(), rb = abas.getBoundingClientRect();
    if (ra.right > rb.right) abas.scrollLeft += ra.left - rb.left - 24;
  }

  var norm = function (s) {
    s = String(s || '').toLowerCase();
    return s.normalize ? s.normalize('NFD').replace(/[\u0300-\u036f]/g, '') : s;
  };
  var url = function (base, mudar) {
    try {
      var u = new URL(base, location.href);
      for (var k in mudar) { if (mudar[k]) u.searchParams.set(k, mudar[k]); else u.searchParams.delete(k); }
      u.searchParams.delete('pagina');
      return u.pathname + u.search;
    } catch (e) { return null; }
  };
  // Divisoria que vai ao servidor leva junto a busca digitada e ainda nao enviada.
  var levarBusca = function (a) {
    var v = campo ? campo.value.trim() : '';
    if (!campo || v === campo.defaultValue.trim()) return;
    var novo = url(a.href, { busca: v });
    if (novo) a.href = novo;
  };

  var itens = grade ? grade.children : [], textos = [], estado = { cat: campoCat ? campoCat.value : '', q: '' };
  // Busca ja feita no servidor (lista ja filtrada): fica fixa; so a busca local filtra aqui.
  var qServidor = campo ? campo.defaultValue.trim() : '';
  for (var i = 0; i < itens.length; i++) textos[i] = norm(itens[i].getAttribute('data-texto'));
  var nomeDe = function (slug) {
    var a = slug && abas ? abas.querySelector('.aba[data-cat="' + slug.replace(/["\\]/g, '') + '"]') : null;
    return a ? a.getAttribute('data-nome') : '';
  };

  var aplicar = function (animar) {
    var q = norm(estado.q), antes = [], n = 0, k = 0, j, it, a, b;
    var mover = animar && !C.reduzido && !C.leve;
    if (mover) for (j = 0; j < itens.length; j++) antes[j] = itens[j].hidden ? null : itens[j].getBoundingClientRect();
    for (j = 0; j < itens.length; j++) {
      it = itens[j];
      it.hidden = !((!estado.cat || it.getAttribute('data-cat') === estado.cat) && (!q || textos[j].indexOf(q) > -1));
      if (!it.hidden) n++;
    }
    grade.hidden = n === 0;
    if (vazio) vazio.hidden = n > 0;
    if (abas) abas.classList.toggle('sem-totais', !!estado.q); // totais da categoria nao refletem a busca
    var nome = nomeDe(estado.cat);
    resumo.textContent = n + (n === 1 ? ' curso' : ' cursos') + (nome ? ' em ' + nome : '') + (estado.q || qServidor ? ' para \u201c' + (estado.q || qServidor) + '\u201d' : '');
    if (!mover) return;
    for (j = 0; j < itens.length; j++) {
      it = itens[j];
      if (it.hidden) continue;
      b = it.getBoundingClientRect(); a = antes[j];
      if (b.top > innerHeight && (!a || a.top > innerHeight)) continue; // fora da tela: sem custo
      if (a) {
        if (a.left !== b.left || a.top !== b.top) C.animar(it, [{ transform: 'translate(' + (a.left - b.left) + 'px,' + (a.top - b.top) + 'px)' }, { transform: 'none' }], { duration: 420, fill: 'none' });
      } else {
        C.animar(it, [{ opacity: 0, transform: 'translateY(16px)' }, { opacity: 1, transform: 'none' }], { duration: 380, delay: Math.min(k++, 8) * 40, fill: 'backwards' });
      }
    }
  };
  var sincronizar = function () {
    var novo = url(location.href, { busca: buscaLocal ? estado.q : qServidor, categoria: estado.cat });
    if (novo && history.replaceState) history.replaceState(history.state, '', novo);
  };

  if (buscaLocal && grade && campo) {
    var espera = 0;
    campo.addEventListener('input', function () {
      clearTimeout(espera);
      espera = setTimeout(function () { estado.q = campo.value.trim(); aplicar(true); sincronizar(); }, 180);
    });
  }

  if (abas) abas.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('.aba');
    if (!a || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey || e.button) return;
    if (!catLocal || !grade || !campoCat) { levarBusca(a); return; }
    e.preventDefault();
    estado.cat = a.getAttribute('data-cat') || '';
    if (buscaLocal && campo) estado.q = campo.value.trim();
    campoCat.value = estado.cat;
    var l = abas.querySelectorAll('.aba');
    for (var j = 0; j < l.length; j++) l[j].removeAttribute('aria-current');
    a.setAttribute('aria-current', 'page');
    var nome = nomeDe(estado.cat), t = nome ? 'Cursos de ' + nome : 'Todos os cursos';
    if (titulo) titulo.textContent = (nome || t) + '.';
    document.title = t + ' \u2014 Desbloqueia Cursos';
    aplicar(true);
    sincronizar();
  });
}, true);

/* Categorias: a estante completa sobe da prancha, como na home. Modo leve: sem cena. */
Caderno.pagina('categorias', function () {
  var C = window.Caderno;
  if (C.leve) return;
  C.aoVer(document.querySelector('[data-cena=estante]'), function (el) {
    var ls = el.querySelectorAll('.lomb');
    for (var i = 0; i < ls.length; i++) {
      C.animar(ls[i], [{ transform: 'translateY(110%)', opacity: 0 }, { transform: 'none', opacity: 1 }], { duration: 700, delay: i * 70, fill: 'backwards' });
    }
  });
});

/* Curso: troca de turma sem recarregar (melhoria do link GET ?turma_id=, como na V2)
   e a tinta da trilha de modulos desenhada conforme a rolagem. Os modulos ficam
   sempre legiveis; so o traco e os checks esperam. Reduzido/leve: trilha ja
   desenhada. Roda tambem em movimento reduzido (sempre=true) por causa da turma. */
Caderno.pagina('curso', function () {
  var C = window.Caderno;
  var raiz = document.getElementById('curso');
  if (!raiz) return;

  // ---------- turma escolhida ----------
  var lista = raiz.querySelector('.turmas');
  if (lista) lista.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('.turma-ficha');
    if (!a || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey || e.button) return;
    var cta = a.getAttribute('data-cta-href');
    if (!cta) return;
    e.preventDefault();
    var fs = lista.querySelectorAll('.turma-ficha'), i, nome = a.getAttribute('data-turma-nome') || '', inicio = a.getAttribute('data-turma-inicio') || '';
    for (i = 0; i < fs.length; i++) {
      var sel = fs[i] === a, rot = fs[i].querySelector('[data-rotulo-escolha]');
      fs[i].classList.toggle('escolhida', sel);
      if (sel) fs[i].setAttribute('aria-current', 'true'); else fs[i].removeAttribute('aria-current');
      if (rot) rot.textContent = sel ? 'Turma escolhida' : 'Escolher esta turma';
    }
    var l = raiz.querySelectorAll('[data-cta]');
    for (i = 0; i < l.length; i++) l[i].setAttribute('href', cta);
    l = raiz.querySelectorAll('[data-turma-nome]:not(a)');
    for (i = 0; i < l.length; i++) l[i].textContent = nome;
    var di = raiz.querySelector('[data-turma-inicio]:not(a)'), dl = raiz.querySelector('[data-turma-inicio-linha]');
    if (di) di.textContent = inicio;
    if (dl) dl.hidden = !inicio;
    if (history.replaceState) {
      try { var u = new URL(a.href, location.href); u.hash = ''; history.replaceState(history.state, '', u.pathname + u.search + '#turmas'); } catch (er) {}
    }
  });

  // ---------- trilha de modulos ----------
  var trilha = raiz.querySelector('[data-trilha-mod]');
  if (!trilha || C.reduzido || C.leve) return;
  var mods = trilha.querySelectorAll('.mod'), feitos = 0, progresso = [], pedido = 0;
  if (!mods.length) return;
  trilha.classList.add('rolando');
  var bnav = document.querySelector('.bnav'), barra = document.querySelector('.barra-compra');
  var desenhar = function () {
    pedido = 0;
    var cobre = (bnav && bnav.offsetHeight ? bnav.offsetHeight : 0) + (barra && barra.offsetHeight ? barra.offsetHeight : 0);
    var fim = document.documentElement.scrollHeight - innerHeight - 4 <= (window.pageYOffset || 0);
    var linha = (innerHeight - cobre) * 0.72; // a caneta corre um pouco acima do pe da area util
    for (var i = 0; i < mods.length; i++) {
      var m = mods[i], r = m.getBoundingClientRect(), tr = m.querySelector('.mod-traco');
      if (!m.classList.contains('feito') && (fim || r.top + 20 < linha)) {
        m.classList.add('feito');
        C.tracar(m.querySelector('.ok'), { duration: 260, delay: 120, easing: 'cubic-bezier(.3,0,.3,1)' });
        feitos++;
      }
      if (tr) {
        var p = fim ? 1 : Math.max(0, Math.min(1, (linha - r.top - 48) / Math.max(1, r.height - 52)));
        if (p > (progresso[i] || 0)) { progresso[i] = p; tr.style.clipPath = 'inset(0 0 ' + ((1 - p) * 100).toFixed(2) + '% 0)'; }
      }
    }
    if (feitos === mods.length && (!progresso.length || fim || progresso[mods.length - 2] >= 1)) {
      removeEventListener('scroll', agendar);
      removeEventListener('resize', agendar);
    }
  };
  var agendar = function () { if (!pedido) pedido = requestAnimationFrame(desenhar); };
  addEventListener('scroll', agendar, { passive: true });
  addEventListener('resize', agendar);
  desenhar();
}, true);

/* Autenticacao: mostrar/ocultar senha e mascara de CPF (a mesma da V2) em
   qualquer tela do tema. Funcao, nao animacao: roda tambem em movimento reduzido. */
(function () {
  var i, l = document.querySelectorAll('[data-ver-senha]');
  for (i = 0; i < l.length; i++) l[i].addEventListener('click', function () {
    var inp = document.getElementById(this.getAttribute('data-ver-senha'));
    if (!inp) return;
    var mostrar = inp.type === 'password';
    inp.type = mostrar ? 'text' : 'password';
    this.setAttribute('aria-pressed', mostrar ? 'true' : 'false');
  });
  // Ao enviar, a senha volta a ser campo de senha (gerenciadores de senha e historico).
  l = document.querySelectorAll('.auth-form');
  for (i = 0; i < l.length; i++) l[i].addEventListener('submit', function () {
    var bs = this.querySelectorAll('[data-ver-senha][aria-pressed=true]');
    for (var j = 0; j < bs.length; j++) bs[j].click();
  });
  var mascara = function (v) {
    var d = v.replace(/\D/g, '').slice(0, 11);
    if (d.length <= 3) return d;
    if (d.length <= 6) return d.slice(0, 3) + '.' + d.slice(3);
    if (d.length <= 9) return d.slice(0, 3) + '.' + d.slice(3, 6) + '.' + d.slice(6);
    return d.slice(0, 3) + '.' + d.slice(3, 6) + '.' + d.slice(6, 9) + '-' + d.slice(9);
  };
  l = document.querySelectorAll('[data-mask-cpf]');
  for (i = 0; i < l.length; i++) {
    l[i].value = mascara(l[i].value);
    l[i].addEventListener('input', function () { this.value = mascara(this.value); });
  }
})();

/* Cadastro: forca da senha como marca-texto que preenche (mesmos criterios da
   V2), com o nivel em texto num aria-live. */
Caderno.pagina('cadastro', function () {
  var caixa = document.querySelector('[data-forca]');
  var inp = caixa && document.getElementById(caixa.getAttribute('data-forca'));
  if (!inp) return;
  var trilho = caixa.querySelector('.forca-trilho'), texto = caixa.querySelector('.forca-texto');
  var NOMES = ['', 'Fraca', 'Média', 'Boa', 'Forte'], atual = -1;
  var medir = function () {
    var v = inp.value, s = 0;
    if (v) {
      if (v.length >= 8) s++;
      if (/[a-z]/.test(v) && /[A-Z]/.test(v)) s++;
      if (/\d/.test(v)) s++;
      if (/[^A-Za-z0-9]/.test(v)) s++;
      if (v.length < 8) s = Math.min(s, 1);
      s = Math.max(1, s);
    }
    if (s === atual) return;
    atual = s;
    caixa.setAttribute('data-nivel', s);
    trilho.style.setProperty('--n', s);
    texto.textContent = s ? 'Força: ' + NOMES[s] : '';
  };
  inp.addEventListener('input', medir);
  medir();
}, true);

/* Checkout: risco a caneta da etapa recem-concluida, carimbo na tela de enviado,
   copiar a chave PIX com retorno em texto, area de envio grampeada (arrastar e
   soltar + nome do arquivo), envio unico dos formularios (sem pedido duplicado
   no clique duplo) e foco no aviso de erro. Tudo e melhoria: sem JS, os forms
   nativos funcionam. Roda tambem em movimento reduzido (sempre=true): so a
   animacao e cortada. */
Caderno.pagina('checkout', function () {
  var C = window.Caderno, html = document.documentElement, i;
  var mover = !C.reduzido && html.classList.contains('anima');

  // ---------- aviso de erro devolvido pelo servidor recebe o foco ----------
  var avisos = document.getElementById('ck-avisos');
  if (avisos && avisos.querySelector('[data-ck-erro]')) { try { avisos.focus(); } catch (e) {} }

  // ---------- checklist: a etapa anterior e riscada ao chegar nesta ----------
  var lista = document.querySelector('[data-checklist]');
  if (lista) {
    if (mover) {
      setTimeout(function () {
        C.tracar(lista.querySelector('.recente .ck-ok'), { duration: 260, easing: 'ease-in' });
        lista.classList.add('riscada');
      }, 140);
    } else lista.classList.add('riscada');
  }

  // ---------- recibo: aberto ao lado no desktop, recolhido no celular ----------
  if (matchMedia('(min-width:900px)').matches) {
    var dobras = document.querySelectorAll('[data-recibo-dobra]');
    for (i = 0; i < dobras.length; i++) dobras[i].open = true;
  }

  // ---------- carimbo "comprovante em analise" (ornamento, aria-hidden) ----------
  var folha = document.querySelector('[data-carimbar]');
  if (folha) {
    if (mover) C.carimbar(folha.querySelector('.carimbo'), 450, -7);
    folha.classList.add('em-cena'); // os quadros iniciais (fill both) seguram o estado oculto
  }

  // ---------- quantidade so para terceiros/lote ----------
  var tipos = document.querySelectorAll('[data-ck-tipo]'), qtd = document.querySelector('[data-ck-qtd]');
  if (tipos.length && qtd) {
    var sincronizar = function () {
      var v = '';
      for (var j = 0; j < tipos.length; j++) if (tipos[j].checked) v = tipos[j].value;
      qtd.hidden = v === 'propria' && !qtd.classList.contains('erro');
      // Campo oculto invalido travaria o envio sem aviso: volta a 1 (o servidor
      // usa 1 na compra propria) e reaparece valido em terceiros/lote.
      var n = qtd.querySelector('input');
      if (qtd.hidden && n && !n.checkValidity()) n.value = 1;
    };
    for (i = 0; i < tipos.length; i++) tipos[i].addEventListener('change', sincronizar);
    sincronizar();
  }

  // ---------- copiar a chave PIX ----------
  var bloco = document.querySelector('[data-pix-key-block]');
  var botao = bloco && bloco.querySelector('[data-pix-copy]');
  var chave = bloco && bloco.querySelector('[data-pix-key-input]');
  var retorno = bloco && bloco.querySelector('[data-pix-feedback]');
  if (botao && chave && retorno) {
    var relogio = 0;
    var dizer = function (t) {
      clearTimeout(relogio);
      retorno.textContent = t;
      relogio = setTimeout(function () { retorno.textContent = ''; }, 6000);
    };
    var copiou = function () { dizer('Chave copiada. Agora cole no app do seu banco.'); };
    var manual = function () {
      chave.focus(); chave.select();
      dizer('Não deu para copiar sozinho. A chave está selecionada: copie pelo menu do aparelho.');
    };
    var antigo = function () {
      try { chave.focus(); chave.select(); if (document.execCommand('copy')) { copiou(); return; } } catch (e) {}
      manual();
    };
    botao.hidden = false;
    botao.addEventListener('click', function () {
      if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(chave.value).then(copiou, antigo);
      else antigo();
    });
  }

  // ---------- area de envio grampeada ----------
  var area = document.querySelector('[data-grampeado]');
  var arquivo = area && area.querySelector('input[type=file]');
  var nome = area && area.querySelector('[data-arquivo-nome]');
  if (arquivo && nome) {
    var tamanho = function (b) {
      return b >= 1048576 ? (b / 1048576).toFixed(1).replace('.', ',') + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB';
    };
    var mostrar = function (grampear) {
      var f = arquivo.files && arquivo.files[0];
      area.classList.toggle('com-arquivo', !!f);
      nome.textContent = f ? 'Arquivo grampeado: ' + f.name + ' (' + tamanho(f.size) + ')' : '';
      if (f && grampear && mover) {
        C.animar(area.querySelector('.grampeado-grampo'), [
          { transform: 'translateY(-10px) rotate(-7deg)' }, { transform: 'translateY(2px) rotate(-7deg)', offset: .7 }, { transform: 'rotate(-7deg)' }
        ], { duration: 260, fill: 'none' });
      }
    };
    var marcar = function (e) { e.preventDefault(); area.classList.add('arrastando'); };
    var soltar = function (e) {
      if (e.type === 'dragleave' && e.relatedTarget && area.contains(e.relatedTarget)) return;
      area.classList.remove('arrastando');
    };
    arquivo.addEventListener('change', function () { mostrar(true); });
    area.addEventListener('dragenter', marcar);
    area.addEventListener('dragover', marcar);
    area.addEventListener('dragleave', soltar);
    area.addEventListener('drop', function (e) {
      e.preventDefault();
      soltar(e);
      var fs = e.dataTransfer && e.dataTransfer.files;
      if (!fs || !fs.length) return;
      try {
        if (fs.length > 1 && window.DataTransfer) { var dt = new DataTransfer(); dt.items.add(fs[0]); fs = dt.files; }
        arquivo.files = fs;
      } catch (er) {
        nome.textContent = 'Este aparelho não aceita soltar o arquivo aqui. Toque em escolher arquivo.';
        return;
      }
      mostrar(true);
    });
    mostrar(false); // arquivo ja escolhido (voltar do historico)
  }

  // ---------- envio unico: o segundo clique/Enter nao gera outro pedido ----------
  var forms = document.querySelectorAll('form[data-ck-envio]');
  for (i = 0; i < forms.length; i++) forms[i].addEventListener('submit', function (e) {
    var f = this, b = f.querySelector('button[type=submit]');
    if (f.getAttribute('data-enviando') === '1') { e.preventDefault(); return; }
    f.setAttribute('data-enviando', '1');
    if (!b) return;
    b.setAttribute('data-html', b.innerHTML);
    // no proximo tick: desabilitar antes cancelaria o envio nativo
    setTimeout(function () {
      var rot = b.getAttribute('data-loading-label');
      if (rot) b.textContent = rot;
      b.disabled = true;
    }, 0);
  });
  // Voltar pelo historico (bfcache) devolve os botoes prontos para uso.
  addEventListener('pageshow', function (e) {
    if (!e.persisted) return;
    for (var k = 0; k < forms.length; k++) {
      var b = forms[k].querySelector('button[type=submit]');
      forms[k].removeAttribute('data-enviando');
      if (b && b.hasAttribute('data-html')) { b.innerHTML = b.getAttribute('data-html'); b.disabled = false; }
    }
  });
}, true);
