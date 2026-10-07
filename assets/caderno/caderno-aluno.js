/* Tema caderno: comportamento das páginas do aluno (conta, aula, quiz, atividade).
   Carregado com defer depois de caderno.js, só quando o layout recebe $cadernoAluno.

   Como estender (tarefas 2 a 4): registre um módulo por página com
       Caderno.pagina('aula', function () { ... });
   O nome é o <body data-pagina> da página. O módulo só mexe no que encontra no
   DOM; sem JS a página continua funcionando (envio nativo dos formulários).
   Use o 3º argumento `true` quando o módulo tem função (contador, autosave,
   cronômetro) e deve rodar também com movimento reduzido: Caderno.pagina cortaria
   a execução nesse caso. Ids e names dos formulários são contrato com o servidor
   e com os endpoints JSON da V1; não renomear. Sem alert()/confirm(). */
(function () {
  'use strict';

  /* Envio protegido: botões com [data-loading-label] trocam o texto, marcam
     aria-busy e são desabilitados logo após o submit. O disable roda em
     setTimeout(0) para o valor do botão ainda ir no POST. Nenhum
     preventDefault genérico; se outro código cancelou o envio, nada muda. */
  function protegerEnvio(form) {
    form.addEventListener('submit', function (ev) {
      if (ev.defaultPrevented) return;
      // form.elements inclui botões ligados por form= fora do form (barra de estudo).
      var botoes = [].filter.call(form.elements, function (el) { return el.hasAttribute('data-loading-label'); });
      if (!botoes.length) return;
      var i, b;
      for (i = 0; i < botoes.length; i++) {
        b = botoes[i];
        if (b.getAttribute('aria-busy') === 'true') continue;
        b.setAttribute('aria-busy', 'true');
        if (b.getAttribute('data-html-original') === null) b.setAttribute('data-html-original', b.innerHTML);
        b.textContent = b.getAttribute('data-loading-label');
      }
      setTimeout(function () {
        for (var j = 0; j < botoes.length; j++) botoes[j].disabled = true;
      }, 0);
    });
  }

  function iniciarEnvioProtegido() {
    var forms = document.querySelectorAll('form');
    for (var i = 0; i < forms.length; i++) {
      if (forms[i].querySelector('[data-loading-label]')) protegerEnvio(forms[i]);
    }
  }

  // Voltar pelo histórico (bfcache) restaura a página com o botão travado: reabilita.
  window.addEventListener('pageshow', function (ev) {
    if (!ev.persisted) return;
    var bs = document.querySelectorAll('[data-loading-label][aria-busy="true"]');
    for (var i = 0; i < bs.length; i++) {
      bs[i].disabled = false;
      bs[i].removeAttribute('aria-busy');
      var t = bs[i].getAttribute('data-html-original');
      if (t !== null) bs[i].innerHTML = t;
    }
  });

  // Aviso pós-redirect ([data-aviso-foco] com texto): recebe o foco, sem rolar a página.
  function iniciar() {
    iniciarEnvioProtegido();
    var av = document.querySelector('[data-aviso-foco]');
    if (av && av.textContent.trim()) try { av.focus({ preventScroll: true }); } catch (e) {}
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar);
  else iniciar();

  /* Módulos por página entram abaixo (tarefas 2.2, 3.2, 3.3 e 4.2). */
})();

/* Área do aluno: a caneta traça a trilha de progresso de cada curso quando ele
   entra na tela, e os carimbos de certificado caem; a aba aberta rola à vista. Sem JS, com
   movimento reduzido ou se esta cena não rodar, tudo já aparece no estado final
   (os estados ocultos existem só sob html.anima, e a trava do layout os desfaz). */
Caderno.pagina('aluno', function () {
  var C = window.Caderno, i;
  // No celular as divisórias rolam (vale com movimento reduzido): a aba aberta começa
  // à vista, medida depois das fontes (a largura das abas muda quando elas chegam), e
  // a borda que ainda tem abas escondidas esmaece (.mais-dir/.mais-esq), como dica.
  var abas = document.querySelector('.al-abas'), ativa = abas && abas.querySelector('[aria-selected=true]');
  if (abas) {
    var bordas = function () {
      var resto = abas.scrollWidth - abas.clientWidth - abas.scrollLeft;
      abas.classList.toggle('mais-dir', resto > 18); // o respiro final da faixa (gutter) não conta
      abas.classList.toggle('mais-esq', abas.scrollLeft > 2);
    };
    var mostrar = function () {
      if (ativa && abas.scrollWidth > abas.clientWidth) {
        var ra = ativa.getBoundingClientRect(), rb = abas.getBoundingClientRect();
        if (ra.right > rb.right + 1) abas.scrollLeft += ra.right - rb.right + 40;
      }
      bordas();
    };
    abas.addEventListener('scroll', bordas, { passive: true });
    addEventListener('resize', bordas);
    mostrar();
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(mostrar);
  }
  if (C.reduzido || !document.documentElement.classList.contains('anima')) return;
  var cena = function (el) { el.classList.add('em-cena'); };
  var ps = document.querySelectorAll('[data-progresso]');
  for (i = 0; i < ps.length; i++) C.aoVer(ps[i], function (el) {
    var p = parseFloat(el.style.getPropertyValue('--p')) || 0;
    if (!C.leve && p > 0) C.animar(el.querySelector('.al-prog-tinta'), [{ strokeDashoffset: String(p) }, { strokeDashoffset: '0' }],
      { duration: 500 + p * 9, delay: 180, easing: 'cubic-bezier(.45,0,.25,1)' });
    cena(el);
  }, { threshold: 0.6 });
  var cs = document.querySelectorAll('[data-carimbar]');
  for (i = 0; i < cs.length; i++) C.aoVer(cs[i], function (el) {
    if (!C.leve) C.carimbar(el.querySelector('.carimbo'), 350, el.classList.contains('al-curso') ? -8 : -14);
    cena(el);
  }, { threshold: 0.5 });
}, true);

/* Minha conta: cidades pela UF na API do IBGE (cache por UF), com a UF e a
   cidade atuais lidas de data-* do form. Se a busca falhar, o select vira um
   campo de texto com o mesmo id/name (a cidade continua indo no POST). A máscara
   de CPF e o olho da senha vêm de caderno.js. Função: roda com movimento reduzido. */
Caderno.pagina('conta', function () {
  var form = document.getElementById('v2-conta-form');
  var uf = document.getElementById('conta-estado'), select = document.getElementById('conta-cidade');
  if (!form || !uf || !select) return;
  var status = form.querySelector('[data-cidade-status]'), texto = null, cache = {}, vez = 0;
  var IBGE = { AC: 12, AL: 27, AP: 16, AM: 13, BA: 29, CE: 23, DF: 53, ES: 32, GO: 52, MA: 21, MT: 51, MS: 50, MG: 31, PA: 15, PB: 25,
    PR: 41, PE: 26, PI: 22, RJ: 33, RN: 24, RS: 43, RO: 11, RR: 14, SC: 42, SP: 35, SE: 28, TO: 17 };

  // Região viva sempre presente (vazia quando não há aviso): só o texto muda.
  var dizer = function (t) { if (status && status.textContent !== t) status.textContent = t; };
  // Põe `el` no lugar do controle atual de cidade, levando o estado de erro.
  var usar = function (el) {
    var atual = document.getElementById('conta-cidade');
    if (!atual || atual === el) return;
    var a = ['aria-invalid', 'aria-describedby'];
    for (var k = 0; k < a.length; k++) {
      if (atual.hasAttribute(a[k])) el.setAttribute(a[k], atual.getAttribute(a[k])); else el.removeAttribute(a[k]);
    }
    atual.parentNode.replaceChild(el, atual);
  };
  var opcoes = function (lista, escolhida) {
    usar(select);
    select.innerHTML = '';
    if (!lista.length) { select.disabled = true; select.add(new Option('Selecione o estado primeiro', '')); return; }
    select.disabled = false;
    select.add(new Option('Selecione a cidade', ''));
    var achou = false;
    for (var k = 0; k < lista.length; k++) {
      var sel = !!escolhida && lista[k] === escolhida;
      if (sel) achou = true;
      select.add(new Option(lista[k], lista[k], sel, sel));
    }
    // Cidade gravada com outra grafia: continua escolhida, não se perde ao salvar.
    if (escolhida && !achou) select.add(new Option(escolhida, escolhida, true, true));
  };
  var falhou = function (escolhida) {
    if (!texto) {
      texto = document.createElement('input');
      texto.type = 'text'; texto.id = 'conta-cidade'; texto.name = 'cidade'; texto.autocomplete = 'address-level2';
    }
    texto.value = escolhida || '';
    usar(texto);
    dizer('Não foi possível carregar a lista de cidades. Digite o nome da sua cidade.');
  };
  // Durante a busca o select continua habilitado com a cidade atual escolhida: um
  // envio nesse meio-tempo leva a cidade gravada (select desabilitado não vai no POST).
  // Sem resposta em 8 s, cai no campo de texto. Resposta de uma UF anterior é ignorada.
  var carregar = function (sigla, escolhida) {
    var n = ++vez;
    dizer('');
    if (!sigla || !IBGE[sigla]) { opcoes([], ''); return; }
    if (cache[sigla]) { opcoes(cache[sigla], escolhida); return; }
    usar(select);
    select.disabled = false;
    select.innerHTML = '';
    if (escolhida) select.add(new Option(escolhida, escolhida, true, true));
    else select.add(new Option('Carregando cidades…', ''));
    dizer('Carregando cidades…');
    if (!window.fetch) { falhou(escolhida); return; }
    var fim = false, ctl = window.AbortController ? new AbortController() : null;
    var desistir = function () {
      if (fim) return;
      fim = true;
      clearTimeout(relogio);
      if (n === vez) falhou(escolhida);
    };
    var relogio = setTimeout(function () { if (ctl) ctl.abort(); desistir(); }, 8000);
    try {
      fetch('https://servicodados.ibge.gov.br/api/v1/localidades/estados/' + IBGE[sigla] + '/municipios?orderBy=nome', ctl ? { signal: ctl.signal } : {})
        .then(function (r) { if (!r.ok) throw new Error('ibge'); return r.json(); })
        .then(function (d) {
          var lista = [];
          for (var k = 0; k < d.length; k++) if (d[k] && d[k].nome) lista.push(d[k].nome);
          if (!lista.length) throw new Error('vazio');
          cache[sigla] = lista;
          if (fim) return;
          fim = true;
          clearTimeout(relogio);
          if (n === vez) { dizer(''); opcoes(lista, escolhida); }
        })
        .catch(desistir);
    } catch (e) { desistir(); }
  };

  uf.addEventListener('change', function () { carregar(uf.value, ''); });
  carregar(form.getAttribute('data-estado') || uf.value, form.getAttribute('data-cidade') || '');

  // Ao enviar, a senha volta a ser campo de senha (gerenciadores de senha e histórico).
  form.addEventListener('submit', function () {
    var bs = form.querySelectorAll('[data-ver-senha][aria-pressed=true]');
    for (var k = 0; k < bs.length; k++) bs[k].click();
  });
}, true);

/* Aula: sumário aberto na coluna lateral (>= 900 px) e recolhido no celular;
   conclusão automática de texto/HTML (form[data-autoconcluir] → requestSubmit
   após 150 ms, como a V2; o servidor é idempotente e o form some depois de
   concluído); logo após concluir, a caneta traça o ✓. Função: roda com
   movimento reduzido (o ✓ já aparece desenhado; tracar não anima). */
Caderno.pagina('aula', function () {
  var C = window.Caderno, s = document.querySelector('[data-sumario]'), i;
  if (s && window.matchMedia) {
    var mq = matchMedia('(min-width:900px)');
    if (mq.matches) s.open = true;
    var segue = function () { s.open = mq.matches; };
    if (mq.addEventListener) mq.addEventListener('change', segue); else if (mq.addListener) mq.addListener(segue);
  }
  var auto = document.querySelector('form[data-autoconcluir]');
  if (auto) setTimeout(function () { if (auto.requestSubmit) auto.requestSubmit(); else auto.submit(); }, 150);
  var oks = document.querySelectorAll('[data-ok]');
  for (i = 0; i < oks.length; i++) C.tracar(oks[i], { duration: 420, delay: 250, easing: 'cubic-bezier(.3,0,.3,1)' });
}, true);

/* Atividade: contador de caracteres e no máximo 5 imagens (mensagem em texto,
   sem alert; o servidor valida de novo). Função: roda com movimento reduzido. */
Caderno.pagina('atividade', function () {
  var ta = document.getElementById('v2-atv-resposta'), qt = document.querySelector('[data-char-count]');
  if (ta && qt) {
    var contar = function () { qt.textContent = ta.value.length.toLocaleString('pt-BR'); };
    contar();
    ta.addEventListener('input', contar);
  }
  var arq = document.getElementById('v2-atv-imagens'), erro = document.getElementById('v2-atv-imagens-erro');
  if (arq && erro) arq.addEventListener('change', function () {
    var demais = arq.files && arq.files.length > 5;
    erro.textContent = demais ? 'Selecione no máximo 5 imagens. Escolha de novo.' : '';
    erro.hidden = !demais;
    if (demais) arq.value = '';
  });
}, true);

/* Quiz ("folha de prova"): salvamento automático, relógio de prova e navegação,
   com os mesmos endpoints, payloads e ids da V2 (initQuizAutosaveV2,
   initQuizCronometroV2, initQuizWizardV2, initQuizProvaV2). Sem alert/confirm:
   avisos em texto e o painel inline "Enviar mesmo assim". Os fieldsets só ganham
   [hidden] (nunca disabled) e continuam no form: o envio final leva todas as
   respostas. Se a navegação falhar, tudo volta a aparecer em sequência (como sem
   JS). Função: roda com movimento reduzido. */
Caderno.pagina('quiz', function () {
  var C = window.Caderno, d = document, i;
  var $ = function (id) { return d.getElementById(id); };
  var res = d.querySelector('.quiz-res[data-carimbar]');
  if (res && d.documentElement.classList.contains('anima')) C.aoVer(res, function (el) {
    if (!C.leve) C.carimbar(el.querySelector('.carimbo'), 250, -12);
    el.classList.add('em-cena');
  }, { threshold: 0.5 });

  var form = $('v2-quiz-answer-form');
  if (!form) return;
  var feedback = $('v2-quiz-feedback'), ativoNav = false;
  var instr = d.querySelector('details.quiz-instr-dobra');
  if (instr && window.matchMedia && matchMedia('(min-width:900px)').matches) instr.open = true;
  var campo = function (n) { var c = form.querySelector('input[name="' + n + '"]'); return c ? c.value : ''; };
  var num = function (n) { return parseInt(campo(n), 10) || 0; };
  var vazio = function (o) { for (var k in o) if (Object.prototype.hasOwnProperty.call(o, k)) return false; return true; };
  // POST JSON (contrato V1). modo 1: keepalive (saindo da página; só até ~60 KB, o
  // limite do keepalive); 2: com X-CSRF-TOKEN. Falhas saem classificadas em e.tipo:
  // 'rede' e 'servidor' (5xx) são temporárias; 'sessao' (caiu no login ou 401),
  // 'pagina' (CSRF/redirect/403/419/resposta que não é JSON) são permanentes.
  var falha = function (tipo) { var e = new Error(tipo); e.tipo = tipo; return e; };
  var json = function (url, corpo, modo) {
    var h = { 'Content-Type': 'application/json', Accept: 'application/json' }, op, txt = JSON.stringify(corpo);
    if (modo === 2) h['X-CSRF-TOKEN'] = campo('_token');
    op = { method: 'POST', credentials: 'same-origin', headers: h, body: txt };
    if (modo === 1 && txt.length < 60000) op.keepalive = true;
    return fetch(url, op).then(function (r) {
      if (r.redirected) throw falha(/\/login/.test(r.url) ? 'sessao' : 'pagina');
      if (r.status === 401) throw falha('sessao');
      if (r.status >= 500) throw falha('servidor');
      if (!r.ok || !/json/.test(r.headers.get('Content-Type') || '')) throw falha('pagina');
      return r.json()['catch'](function () { throw falha('pagina'); });
    }, function () { throw falha('rede'); });
  };
  // Motivo do bloqueio do salvamento (sessão/página/tentativa); o relógio também para de consultar.
  var bloqueio = null;
  var focar = function (el) { try { el.focus({ preventScroll: true }); } catch (e) {} };
  var rolar = function (el) { if (el) el.scrollIntoView({ block: 'start', behavior: C.reduzido ? 'auto' : 'smooth' }); };
  var respondida = function (s) {
    if (s.getAttribute('data-tipo') === 'discursiva') { var a = s.querySelector('textarea'); return !!(a && a.value.trim().length >= 3); }
    return !!s.querySelector('input[type=radio]:checked');
  };
  // Destrava os botões "Enviando…" quando um envio é barrado aqui (o envio protegido
  // pode tê-los marcado antes deste handler em navegadores mais antigos).
  var liberar = function () {
    setTimeout(function () {
      [].forEach.call(form.elements, function (b) {
        if (b.getAttribute('aria-busy') !== 'true') return;
        b.disabled = false; b.removeAttribute('aria-busy');
        var t = b.getAttribute('data-html-original'); if (t !== null) b.innerHTML = t;
      });
    }, 0);
  };

  // Contador da discursiva (mesma contagem do maxlength).
  var contar = function (t) { var q = t.parentNode.querySelector('[data-quiz-conta]'); if (q) q.textContent = t.value.length.toLocaleString('pt-BR'); };
  var tas = form.querySelectorAll('textarea');
  for (i = 0; i < tas.length; i++) contar(tas[i]);
  form.addEventListener('input', function (e) { if (e.target.tagName === 'TEXTAREA') contar(e.target); });

  /* Salvamento automático: só o que mudou, em POST /aluno/cursos/quiz/rascunho
     (JSON da V2). Radio 600 ms, discursiva 2 s depois da última tecla; ao sair da
     aba ou fechar, envia na hora com keepalive. A fila guarda só QUAIS questões
     mudaram (com um número de sequência); o valor é lido da tela na hora do envio,
     então um lote antigo que falha nunca reenvia valor velho, e um lote antigo que
     chega depois de um mais novo provoca novo envio do valor atual.
     Falha de rede/5xx: tenta de novo (3 s, 6 s, … até 30 s). Sessão expirada,
     página desatualizada (CSRF) ou tentativa já enviada: para na hora (nada de
     laço), avisa em texto e oferece "Tentar salvar de novo", que busca um token
     novo e reenvia tudo o que está na tela. {expirada} recarrega.
     flush() → Promise: usado antes de trocar de questão e ao zerar o relógio. */
  var aoBloquear = null;
  var flush = (function () {
    var tid = num('tentativa_id'), aviso = $('v2-quiz-autosave');
    if (!tid || !aviso || !window.fetch || !window.Promise) return function () { return { then: function (f) { return f && f(); } }; };
    var sujas = {}, seq = {}, salvo = {}, timer = null, voo = 0, falhas = 0, final = false, emCurso = Promise.resolve(), caixa = null;
    var MSG = {
      sessao: 'Sua sessão expirou. Suas últimas respostas podem não ter sido salvas — entre de novo em outra aba e depois toque em “Tentar salvar de novo”.',
      pagina: 'Esta página ficou desatualizada e suas últimas respostas podem não ter sido salvas. Toque em “Tentar salvar de novo”; se não resolver, recarregue a página.',
      enviada: 'Esta tentativa já foi enviada — recarregue a página.'
    };
    var dizer = function (t, erro) { if (aviso.textContent !== t) aviso.textContent = t; aviso.classList.toggle('erro', !!erro); };
    var dois = function (n) { return (n < 10 ? '0' : '') + n; };
    var agendar = function (ms) { clearTimeout(timer); timer = setTimeout(function () { timer = null; enviar(false); }, ms); };
    // chave 'r<pid>' (radio) ou 'd<pid>' (discursiva)
    var valor = function (k) {
      var c = k.charAt(0) === 'r' ? form.querySelector('input[name="respostas[' + k.slice(1) + ']"]:checked')
        : form.querySelector('textarea[name="discursivas[' + k.slice(1) + ']"]');
      return c ? c.value : null;
    };
    var tudo = function () {
      [].forEach.call(form.querySelectorAll('input[type=radio]:checked,textarea'), function (c) {
        var m = /^(respostas|discursivas)\[(\d+)\]$/.exec(c.name || '');
        if (m) sujas[m[1].charAt(0) === 'r' ? 'r' + m[2] : 'd' + m[2]] = 1;
      });
    };
    function bloquear(tipo, msg) {
      if (bloqueio === 'tentativa') return;
      bloqueio = tipo; clearTimeout(timer); timer = null;
      dizer('Respostas não salvas', true);
      if (!caixa) {
        caixa = d.createElement('div');
        caixa.className = 'postit erro largo quiz-salvo-erro';
        caixa.setAttribute('role', 'alert');
        aviso.parentNode.insertBefore(caixa, aviso.nextSibling);
      }
      caixa.innerHTML = '';
      var p = d.createElement('p'), bt = d.createElement('button');
      p.textContent = msg || MSG[tipo] || MSG.pagina;
      bt.type = 'button'; bt.className = 'link';
      bt.textContent = tipo === 'tentativa' ? 'Recarregar a página' : 'Tentar salvar de novo';
      bt.addEventListener('click', function () { if (tipo === 'tentativa') location.reload(); else retomar(bt); });
      caixa.appendChild(p); caixa.appendChild(bt);
      caixa.hidden = false;
      caixa.scrollIntoView({ block: 'center', behavior: C.reduzido ? 'auto' : 'smooth' });
    }
    aoBloquear = function (tipo) { if (!bloqueio) bloquear(tipo); };
    // Busca a própria página (GET, sem efeito) para pegar o token da sessão atual.
    function retomar(bt) {
      bt.disabled = true;
      fetch(location.href, { credentials: 'same-origin' }).then(function (r) {
        if (r.redirected && /\/login/.test(r.url)) throw falha('sessao');
        return r.text();
      }).then(function (html) {
        var m = /name="_token" value="([0-9a-zA-Z]+)"/.exec(html);
        if (!m) throw falha('pagina');
        [].forEach.call(d.querySelectorAll('input[name="_token"]'), function (c) { c.value = m[1]; });
        bloqueio = null; falhas = 0; caixa.hidden = true;
        tudo(); enviar(false);
      })['catch'](function (e) {
        bt.disabled = false;
        bloqueio = null;
        bloquear(e && e.tipo === 'sessao' ? 'sessao' : 'pagina');
      });
    }
    function enviar(aoSair) {
      if (final || bloqueio || vazio(sujas)) return emCurso;
      if (voo && !aoSair) return emCurso; // ao terminar, o que sobrou na fila sai em seguida
      var lote = {}, corpo = { respostas: {}, discursivas: {} }, falhou = false, k, v;
      for (k in sujas) {
        lote[k] = seq[k] || 0;
        v = valor(k);
        if (v !== null) corpo[k.charAt(0) === 'r' ? 'respostas' : 'discursivas'][k.slice(1)] = v;
      }
      sujas = {};
      clearTimeout(timer); timer = null;
      voo++;
      aviso.classList.add('salvando');
      emCurso = json('/aluno/cursos/quiz/rascunho', {
        tentativa_id: tid, item_id: num('item_id'), inscricao_id: num('inscricao_id'), curso_id: num('curso_id'), turma_id: num('turma_id'),
        respostas: corpo.respostas, discursivas: corpo.discursivas, _token: campo('_token')
      }, aoSair ? 1 : 0).then(function (r) {
        if (r && r.ok) {
          falhas = 0;
          // Um lote mais novo já tinha gravado e este (antigo) chegou depois: regrava o atual.
          for (k in lote) { if (k in salvo && salvo[k] > lote[k]) sujas[k] = 1; else salvo[k] = lote[k]; }
          var h = new Date();
          if (!bloqueio) dizer('Salvo às ' + dois(h.getHours()) + ':' + dois(h.getMinutes()));
          return;
        }
        if (r && r.expirada) { final = true; location.reload(); return; }
        falhou = true;
        if (r && r.message) {
          bloquear('tentativa', /enviada|encerrada/i.test(r.message) ? MSG.enviada : r.message + ' Recarregue a página.');
          return;
        }
        throw falha('pagina');
      })['catch'](function (e) {
        falhou = true;
        // Volta para a fila só o que nenhum envio mais novo já gravou; o valor será lido da tela.
        for (k in lote) if (!(k in salvo && salvo[k] >= lote[k])) sujas[k] = 1;
        if (e && (e.tipo === 'sessao' || e.tipo === 'pagina')) { bloquear(e.tipo); return; }
        falhas++;
        dizer('Não foi possível salvar — tentando de novo', true);
        agendar(Math.min(30000, 3000 * Math.pow(2, falhas - 1)));
      }).then(function () {
        voo--;
        if (!voo) aviso.classList.remove('salvando');
        if (!falhou && !bloqueio && !timer && !vazio(sujas)) agendar(300);
      });
      return emCurso;
    }
    var mudou = function (k, ms) { seq[k] = (seq[k] || 0) + 1; sujas[k] = 1; if (!bloqueio) agendar(ms); };
    form.addEventListener('change', function (e) {
      var m = e.target.type === 'radio' && /^respostas\[(\d+)\]$/.exec(e.target.name || '');
      if (m) mudou('r' + m[1], 600);
    });
    form.addEventListener('input', function (e) {
      var m = e.target.tagName === 'TEXTAREA' && /^discursivas\[(\d+)\]$/.exec(e.target.name || '');
      if (m) mudou('d' + m[1], 2000);
    });
    d.addEventListener('visibilitychange', function () { if (d.hidden) enviar(true); });
    addEventListener('pagehide', function () { enviar(true); });
    // Voltou pelo histórico: o salvamento continua de onde estava.
    addEventListener('pageshow', function (e) { if (e.persisted) { final = false; if (!vazio(sujas) && !bloqueio) agendar(300); } });
    // Envio final de verdade (não barrado pela revisão): grava o pendente sem segurar o
    // POST (keepalive) — se o servidor recusar o envio, o rascunho já está salvo.
    form.addEventListener('submit', function (e) {
      if (e.defaultPrevented) return;
      enviar(true);
      final = true; clearTimeout(timer);
    });
    return function () { clearTimeout(timer); timer = null; return voo ? emCurso.then(function () { return enviar(false); }) : enviar(false); };
  })();

  /* Relógio de prova: conta a partir de data-restante contra o relógio do aparelho
     (aba em segundo plano não atrasa), confere POST /aluno/cursos/quiz/tempo a
     cada 60 s e ao voltar à aba ({tentativa_id,_token} + X-CSRF-TOKEN); erro de
     sessão/página para as consultas (sem laço). Até 5 min: visual de alerta e um
     único aviso em texto; a 5 s, grava o pendente. Zerou: salva, pergunta ao
     servidor e só recarrega se ele confirmar o fim (senão, ressincroniza). */
  try { (function () {
    var box = $('v2-quiz-cronometro'), valor = $('v2-quiz-cronometro-valor'), aviso = $('quiz-tempo-aviso');
    if (!box || !valor || !window.fetch) return;
    var fim = Date.now() + (parseInt(box.getAttribute('data-restante'), 10) || 0) * 1000;
    var tid = parseInt(box.getAttribute('data-tentativa'), 10) || 0, acabou = false, avisou = false, antes = false, parado = false, tick;
    var conferir = function () { return json('/aluno/cursos/quiz/tempo', { tentativa_id: tid, _token: campo('_token') }, 2); };
    var dois = function (n) { return (n < 10 ? '0' : '') + n; };
    // Quantas vezes já recarregou por fim de tempo nesta sessão (sessionStorage; sem ele, window.name).
    var vezes = function () {
      var k = 'quizTempo' + tid, n = 0, re = new RegExp(k + '=(\\d+);?');
      try { n = +sessionStorage.getItem(k) || 0; sessionStorage.setItem(k, n + 1); return n; } catch (e) {}
      var m = re.exec(window.name || '');
      n = m ? +m[1] : 0;
      window.name = (window.name || '').replace(re, '') + k + '=' + (n + 1) + ';';
      return n;
    };
    function recarregar() {
      acabou = true; clearInterval(tick);
      if (aviso) aviso.textContent = '';
      var n = vezes(), p = d.createElement('p');
      p.className = 'postit erro largo';
      p.setAttribute('role', 'alert');
      // Já recarregou duas vezes e o servidor não encerrou: não entra em laço.
      p.textContent = n < 2 ? 'O tempo da prova terminou. A página vai recarregar para mostrar a situação da sua prova.'
        : 'O tempo da prova terminou. Recarregue a página para ver a situação da sua prova.';
      if (feedback) { feedback.innerHTML = ''; feedback.appendChild(p); focar(feedback); rolar(feedback); }
      if (n < 2) setTimeout(function () { location.reload(); }, 2500);
    }
    function mostrar() {
      var s = Math.max(0, Math.ceil((fim - Date.now()) / 1000));
      var t = dois(Math.floor(s / 3600)) + ':' + dois(Math.floor(s % 3600 / 60)) + ':' + dois(s % 60);
      if (valor.textContent !== t) valor.textContent = t;
      if (s <= 300) {
        box.classList.add('acabando');
        if (!avisou && s > 0 && aviso) {
          avisou = true;
          aviso.textContent = s > 60 ? 'Atenção: faltam ' + Math.ceil(s / 60) + ' minutos para o fim da prova.' : 'Atenção: falta menos de 1 minuto para o fim da prova.';
        }
      } else box.classList.remove('acabando');
      if (s <= 5 && !antes) { antes = true; flush(); }
      if (s <= 0 && !acabou) {
        acabou = true; clearInterval(tick);
        flush().then(conferir).then(function (r) {
          // O servidor ainda dá tempo (relógio do aparelho adiantado): segue contando.
          if (r && r.ok && !r.expirada && r.tempo && r.tempo.segundos_restantes > 0) {
            fim = Date.now() + r.tempo.segundos_restantes * 1000;
            acabou = false; antes = false; tick = setInterval(mostrar, 1000); mostrar();
            return;
          }
          recarregar();
        }, recarregar);
      }
    }
    function sincronizar() {
      if (acabou || bloqueio || parado) return;
      conferir().then(function (r) {
        if (!r || !r.ok || acabou) return;
        if (r.encerrada || r.expirada) { recarregar(); return; }
        if (r.tempo && typeof r.tempo.segundos_restantes === 'number') { fim = Date.now() + r.tempo.segundos_restantes * 1000; mostrar(); }
      })['catch'](function (e) {
        if (e && (e.tipo === 'sessao' || e.tipo === 'pagina')) { if (aoBloquear) aoBloquear(e.tipo); else parado = true; }
      });
    }
    mostrar();
    tick = setInterval(mostrar, 1000);
    setInterval(sincronizar, 60000);
    d.addEventListener('visibilitychange', function () { if (!d.hidden) sincronizar(); });
    addEventListener('pageshow', function (e) { if (e.persisted) sincronizar(); });
  })(); } catch (e) {}

  var wrap = $('v2-quiz-perguntas'), barra = $('quiz-barra'), avisoP = $('quiz-aviso-passo');
  var steps = wrap ? [].slice.call(wrap.querySelectorAll('.v2-quiz-pergunta')) : [];
  var bAnt = $('quiz-be-ant'), bProx = $('quiz-be-prox'), submit = $('v2-quiz-submit-btn'), anuncio = $('quiz-anuncio');
  // Progresso para leitor de tela só quando a questão muda ou a revisão abre.
  var anunciar = function (t) { if (anuncio) { anuncio.textContent = ''; setTimeout(function () { anuncio.textContent = t; }, 60); } };
  // Nova pergunta: foco no fieldset (o leitor de tela lê o enunciado) e rola se preciso.
  var ir = function (s, topo, t) {
    focar(s);
    if (t) anunciar(t);
    var r = s.getBoundingClientRect();
    if (r.top < 60 || r.top > innerHeight * 0.55) rolar(topo || s);
  };
  // Volta ao estado sem JS: tudo em sequência, envio direto.
  var semNav = function () {
    ativoNav = false;
    steps.forEach(function (s) { s.hidden = false; });
    var ids = ['v2-quiz-progress', 'v2-quiz-prova-nav', 'v2-quiz-prova-revisao', 'v2-quiz-prev-btn', 'v2-quiz-next-btn', 'v2-quiz-prova-prev-btn', 'v2-quiz-prova-next-btn', 'v2-quiz-prova-revisar-btn', 'quiz-barra', 'quiz-aviso-passo'];
    for (var k = 0; k < ids.length; k++) if ($(ids[k])) $(ids[k]).hidden = true;
    var hs = form.querySelectorAll('.v2-quiz-bloco-titulo, [data-flag-pergunta]');
    for (k = 0; k < hs.length; k++) hs[k].hidden = !hs[k].classList.contains('v2-quiz-bloco-titulo');
    if (submit) submit.hidden = false;
  };

  try {
    if (steps.length > 1 && wrap.getAttribute('data-modo-prova') !== '1') (function () {
      /* Quiz curto: uma pergunta por vez, começando na primeira sem resposta;
         "Próxima" exige resposta (aviso em texto); números já visitados navegam. */
      var prog = $('v2-quiz-progress'), fill = $('v2-quiz-progress-fill'), txt = $('v2-quiz-progress-text'), nums = $('v2-quiz-progress-dots');
      var prev = $('v2-quiz-prev-btn'), next = $('v2-quiz-next-btn'), pos = $('quiz-be-pos');
      if (!prog || !prev || !next || !nums) return;
      var n = steps.length, cur = n - 1, max, bs = [];
      for (var k = 0; k < n; k++) if (!respondida(steps[k])) { cur = k; break; }
      max = cur;
      steps.forEach(function (s, k) {
        var b = d.createElement('button');
        b.type = 'button'; b.className = 'passo'; b.textContent = k + 1;
        b.setAttribute('aria-label', 'Ir para a pergunta ' + (k + 1));
        b.addEventListener('click', function () { if (k <= max) { cur = k; render(); ir(steps[k], prog, txt.textContent); } });
        nums.appendChild(b); bs.push(b);
      });
      function render() {
        steps.forEach(function (s, k) { s.hidden = k !== cur; });
        fill.style.width = ((cur + 1) / n * 100) + '%';
        txt.textContent = 'Pergunta ' + (cur + 1) + ' de ' + n;
        if (pos) pos.textContent = (cur + 1) + ' de ' + n;
        bs.forEach(function (b, k) {
          b.disabled = k > max;
          if (k === cur) b.setAttribute('aria-current', 'step'); else b.removeAttribute('aria-current');
          b.classList.toggle('feito', k !== cur && respondida(steps[k]));
        });
        var ult = cur === n - 1;
        prev.hidden = cur === 0; next.hidden = ult;
        if (submit) submit.hidden = !ult;
        if (bAnt) bAnt.disabled = cur === 0;
        if (bProx) bProx.disabled = ult;
        if (avisoP) avisoP.hidden = true;
      }
      function avancar() {
        if (!respondida(steps[cur])) {
          if (avisoP) {
            avisoP.textContent = ''; avisoP.hidden = false;
            setTimeout(function () { avisoP.textContent = 'Selecione uma alternativa antes de continuar.'; }, 30);
            avisoP.scrollIntoView({ block: 'nearest' });
          }
          return;
        }
        if (cur < n - 1) { cur++; if (cur > max) max = cur; render(); ir(steps[cur], prog, txt.textContent); }
      }
      var voltar = function () { if (cur > 0) { cur--; render(); ir(steps[cur], prog, txt.textContent); } };
      prev.addEventListener('click', voltar);
      next.addEventListener('click', avancar);
      if (bAnt) bAnt.addEventListener('click', voltar);
      if (bProx) bProx.addEventListener('click', avancar);
      form.addEventListener('change', function () { if (avisoP) avisoP.hidden = true; });
      // Enter num radio dispara o envio implícito: fora da última pergunta, vale como "Próxima".
      form.addEventListener('submit', function (e) {
        if (!ativoNav || cur >= n - 1) return;
        e.preventDefault(); liberar(); avancar();
      }, true);
      prog.hidden = false;
      if (barra) barra.hidden = false;
      render();
      ativoNav = true;
    })();

    if (steps.length > 1 && wrap.getAttribute('data-modo-prova') === '1') (function () {
      /* Prova: uma questão por vez com navegação livre, índice por blocos, marcar
         para revisão (POST /aluno/cursos/quiz/revisao, sem esperar resposta) e
         revisão antes do envio. Discursiva vazia barra o envio e abre a revisão;
         objetivas pendentes pedem confirmação no painel inline. */
      var nav = $('v2-quiz-prova-nav'), painel = $('v2-quiz-prova-revisao');
      if (!nav || !painel) return;
      var elBloco = $('v2-quiz-prova-bloco'), elPos = $('v2-quiz-prova-pos'), elFill = $('v2-quiz-prova-fill'), elResumo = $('v2-quiz-prova-resumo');
      var indice = $('v2-quiz-prova-indice'), indiceBtn = $('v2-quiz-prova-indice-btn'), prev = $('v2-quiz-prova-prev-btn'), next = $('v2-quiz-prova-next-btn');
      var revisar = $('v2-quiz-prova-revisar-btn'), voltarBtn = $('v2-quiz-prova-voltar-btn'), resumoRev = $('v2-quiz-prova-revisao-resumo'), listas = $('v2-quiz-prova-revisao-listas');
      var conf = $('quiz-confirmar'), confTxt = $('quiz-confirmar-txt'), confEnviar = $('quiz-confirmar-enviar'), confRevisar = $('quiz-confirmar-revisar');
      var bIndice = $('quiz-be-indice'), bRevisar = $('quiz-be-revisar'), bVoltar = $('quiz-be-voltar');
      var n = steps.length, atual = 0, emRevisao = false, confirmado = false, idx = [];
      var hs = wrap.querySelectorAll('.v2-quiz-bloco-titulo');
      var marcada = function (s) { return s.getAttribute('data-revisao') === '1'; };
      var plural = function (q, um, varios) { return q + ' ' + (q === 1 ? um : varios); };
      var el = function (tag, cls, txt) { var e = d.createElement(tag); if (cls) e.className = cls; if (txt != null) e.textContent = txt; return e; };
      function conta() {
        var r = 0, m = 0, falta = false;
        steps.forEach(function (s) {
          if (respondida(s)) r++; else if (s.getAttribute('data-tipo') === 'discursiva') falta = true;
          if (marcada(s)) m++;
        });
        return { r: r, p: n - r, m: m, falta: falta };
      }
      function pintar() {
        idx.forEach(function (b, k) {
          var ok = respondida(steps[k]), mk = marcada(steps[k]);
          b.classList.toggle('feito', ok); b.classList.toggle('marcada', mk);
          if (k === atual && !emRevisao) b.setAttribute('aria-current', 'step'); else b.removeAttribute('aria-current');
          b.setAttribute('aria-label', 'Questão ' + (k + 1) + (ok ? ', respondida' : ', pendente') + (mk ? ', marcada para revisão' : ''));
        });
      }
      function fecharConf() { confirmado = false; if (conf) conf.hidden = true; }
      function render() {
        steps.forEach(function (s, k) { s.hidden = emRevisao || k !== atual; });
        var c = conta(), t = c.r + ' de ' + n + ' respondidas' + (c.p ? ' · ' + plural(c.p, 'pendente', 'pendentes') : '') + (c.m ? ' · ' + plural(c.m, 'marcada', 'marcadas') : '');
        if (elResumo && elResumo.textContent !== t) elResumo.textContent = t;
        if (elFill) elFill.style.width = (c.r / n * 100) + '%';
        if (!emRevisao) {
          if (elBloco) elBloco.textContent = steps[atual].getAttribute('data-bloco-titulo') || '';
          if (elPos) elPos.textContent = 'Questão ' + (atual + 1) + ' de ' + n;
        }
        if (prev) prev.hidden = emRevisao || atual === 0;
        if (next) next.hidden = emRevisao || atual >= n - 1;
        if (revisar) revisar.hidden = emRevisao;
        if (submit) submit.hidden = true;
        nav.hidden = emRevisao; painel.hidden = !emRevisao;
        [bAnt, bIndice, bRevisar, bProx].forEach(function (b) { if (b) b.hidden = emRevisao; });
        if (bVoltar) bVoltar.hidden = !emRevisao;
        if (bAnt) bAnt.disabled = atual === 0;
        if (bProx) bProx.disabled = atual >= n - 1;
        pintar();
      }
      function irPara(k) {
        if (k < 0 || k >= n) return;
        flush();
        atual = k; emRevisao = false; fecharConf();
        if (indice) indice.hidden = true;
        if (indiceBtn) indiceBtn.setAttribute('aria-expanded', 'false');
        render();
        rolar(nav);
        focar(steps[k]);
        anunciar(elPos.textContent + '. ' + elResumo.textContent + '.');
      }
      function abrirRevisao() {
        flush();
        emRevisao = true; fecharConf();
        var c = conta(), p = el('p', 'prova-revisao-conta');
        resumoRev.innerHTML = ''; listas.innerHTML = '';
        p.appendChild(el('strong', '', c.r + ' de ' + n));
        p.appendChild(d.createTextNode(' questões respondidas.'));
        resumoRev.appendChild(p);
        if (c.falta) {
          p = el('p', 'postit erro largo', 'A questão discursiva é obrigatória para o envio. Responda antes de enviar a prova.');
          p.setAttribute('role', 'alert'); resumoRev.appendChild(p);
        } else if (c.p) {
          p = el('p', 'postit largo', 'Você ainda tem ' + plural(c.p, 'questão sem resposta', 'questões sem resposta') + '. Questão em branco conta como erro.');
          p.setAttribute('role', 'status'); resumoRev.appendChild(p);
        }
        var pend = [], marc = [];
        steps.forEach(function (s, k) { if (!respondida(s)) pend.push(k); if (marcada(s)) marc.push(k); });
        [['Sem resposta', pend], ['Marcadas para revisão', marc]].forEach(function (par) {
          if (!par[1].length) return;
          listas.appendChild(el('h3', 'prova-lista-tit', par[0] + ' (' + par[1].length + ')'));
          var ul = el('ul', 'prova-grade');
          par[1].forEach(function (k) {
            var li = el('li'), bt = el('button', 'idx' + (par[1] === marc ? ' marcada' : ''), String(k + 1));
            bt.type = 'button';
            bt.setAttribute('aria-label', 'Ir para a questão ' + (k + 1));
            bt.addEventListener('click', function () { irPara(k); });
            li.appendChild(bt); ul.appendChild(li);
          });
          listas.appendChild(ul);
        });
        if (!pend.length && !marc.length) {
          p = el('p', 'aula-ok-msg', 'Tudo respondido e nada marcado para revisão.');
          p.setAttribute('role', 'status'); listas.appendChild(p);
        }
        render();
        rolar(painel);
        focar(painel);
        anunciar('Revisão: ' + c.r + ' de ' + n + ' respondidas.');
      }
      function pedirConf(q) {
        confTxt.textContent = 'Você tem ' + plural(q, 'questão', 'questões') + ' sem resposta. Enviar mesmo assim?';
        conf.hidden = false;
        conf.scrollIntoView({ block: 'nearest' });
        focar(conf);
      }
      // índice por blocos
      var blocoCorrente = null, grade = null;
      steps.forEach(function (s, k) {
        var cod = s.getAttribute('data-bloco') || '';
        if (cod !== blocoCorrente || !grade) {
          blocoCorrente = cod;
          indice.appendChild(el('p', 'prova-indice-tit', s.getAttribute('data-bloco-titulo') || 'Questões'));
          grade = el('div', 'prova-grade');
          indice.appendChild(grade);
        }
        var b = el('button', 'idx', String(k + 1));
        b.type = 'button';
        b.addEventListener('click', function () { irPara(k); });
        grade.appendChild(b); idx.push(b);
      });
      indice.appendChild(el('p', 'prova-legenda', 'Cheia: respondida · Vazia: pendente · Bandeira: marcada para revisão'));
      // marcar para revisão (o estado fica no servidor e sobrevive à recarga)
      steps.forEach(function (s) {
        var bt = s.querySelector('[data-flag-pergunta]');
        if (!bt) return;
        bt.hidden = false;
        bt.addEventListener('click', function () {
          var novo = !marcada(s), t = bt.querySelector('.v2-quiz-flag__texto');
          s.setAttribute('data-revisao', novo ? '1' : '0');
          bt.setAttribute('aria-pressed', novo ? 'true' : 'false');
          if (t) t.textContent = novo ? 'Marcada para revisão' : 'Marcar para revisão';
          render();
          json('/aluno/cursos/quiz/revisao', {
            tentativa_id: num('tentativa_id'), pergunta_id: parseInt(s.getAttribute('data-pergunta-id'), 10) || 0, marcada: novo ? 1 : 0, _token: campo('_token')
          })['catch'](function () {});
        });
      });
      form.addEventListener('change', function () { render(); });
      // Discursiva: só redesenha quando ela passa a contar (ou deixa de contar) como respondida.
      var jaResp = steps.map(respondida);
      form.addEventListener('input', function (e) {
        if (e.target.tagName !== 'TEXTAREA') return;
        var r = respondida(steps[atual]);
        if (r !== jaResp[atual]) { jaResp[atual] = r; render(); }
      });
      var abrirIndice = function (abrir) {
        indice.hidden = !abrir;
        indiceBtn.setAttribute('aria-expanded', abrir ? 'true' : 'false');
        if (bIndice) bIndice.setAttribute('aria-expanded', abrir ? 'true' : 'false');
      };
      var voltarProva = function () { irPara(atual); };
      if (prev) prev.addEventListener('click', function () { irPara(atual - 1); });
      if (next) next.addEventListener('click', function () { irPara(atual + 1); });
      if (bAnt) bAnt.addEventListener('click', function () { irPara(atual - 1); });
      if (bProx) bProx.addEventListener('click', function () { irPara(atual + 1); });
      if (revisar) revisar.addEventListener('click', abrirRevisao);
      if (bRevisar) bRevisar.addEventListener('click', abrirRevisao);
      if (voltarBtn) voltarBtn.addEventListener('click', voltarProva);
      if (bVoltar) bVoltar.addEventListener('click', voltarProva);
      indiceBtn.addEventListener('click', function () { abrirIndice(indice.hidden); });
      if (bIndice) bIndice.addEventListener('click', function () { abrirIndice(true); rolar(nav); focar(idx[atual]); });
      if (confEnviar) confEnviar.addEventListener('click', function () { confirmado = true; });
      if (confRevisar) confRevisar.addEventListener('click', function () {
        for (var k = 0; k < n; k++) if (!respondida(steps[k])) { irPara(k); return; }
        voltarProva();
      });
      // Barra o envio antes do envio protegido (fase de captura no próprio form):
      // fora da revisão (ex.: Enter num radio) ou com discursiva vazia, abre a revisão.
      form.addEventListener('submit', function (e) {
        if (!ativoNav) return;
        var c = conta();
        if (!emRevisao || c.falta) { e.preventDefault(); liberar(); abrirRevisao(); return; }
        if (c.p > 0 && !confirmado) { e.preventDefault(); liberar(); pedirConf(c.p); }
      }, true);
      for (var k = 0; k < hs.length; k++) hs[k].hidden = true;
      // Abre na primeira pendente: quem volta reencontra a prova onde parou.
      for (k = 0; k < n; k++) if (!respondida(steps[k])) { atual = k; break; }
      nav.hidden = false;
      if (barra) barra.hidden = false;
      render();
      ativoNav = true;
    })();
  } catch (e) { semNav(); }
}, true);
