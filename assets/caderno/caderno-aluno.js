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
      var botoes = form.querySelectorAll('[data-loading-label]');
      if (!botoes.length) return;
      var i, b;
      for (i = 0; i < botoes.length; i++) {
        b = botoes[i];
        if (b.getAttribute('aria-busy') === 'true') continue;
        b.setAttribute('aria-busy', 'true');
        if (b.getAttribute('data-texto-original') === null) b.setAttribute('data-texto-original', b.textContent);
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
      var t = bs[i].getAttribute('data-texto-original');
      if (t !== null) bs[i].textContent = t;
    }
  });

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciarEnvioProtegido);
  else iniciarEnvioProtegido();

  /* Módulos por página entram abaixo (tarefas 2.2, 3.2, 3.3 e 4.2). */
})();

/* Área do aluno: a caneta traça a trilha de progresso de cada curso quando ele
   entra na tela, e os carimbos de certificado caem; a aba aberta rola à vista. Sem JS, com
   movimento reduzido ou se esta cena não rodar, tudo já aparece no estado final
   (os estados ocultos existem só sob html.anima, e a trava do layout os desfaz). */
Caderno.pagina('aluno', function () {
  var C = window.Caderno, i;
  // No celular as divisórias rolam: a aba aberta começa à vista (vale com movimento reduzido).
  var abas = document.querySelector('.al-abas'), ativa = abas && abas.querySelector('[aria-selected=true]');
  if (ativa && abas.scrollWidth > abas.clientWidth) {
    var ra = ativa.getBoundingClientRect(), rb = abas.getBoundingClientRect();
    if (ra.right > rb.right) abas.scrollLeft += ra.left - rb.left - 24;
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

  var dizer = function (t) { if (status) { status.textContent = t; status.hidden = !t; } };
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
  var carregar = function (sigla, escolhida) {
    var n = ++vez;
    dizer('');
    if (!sigla || !IBGE[sigla]) { opcoes([], ''); return; }
    if (cache[sigla]) { opcoes(cache[sigla], escolhida); return; }
    usar(select);
    select.disabled = true;
    select.innerHTML = '';
    select.add(new Option('Carregando cidades…', ''));
    fetch('https://servicodados.ibge.gov.br/api/v1/localidades/estados/' + IBGE[sigla] + '/municipios?orderBy=nome')
      .then(function (r) { if (!r.ok) throw new Error('ibge'); return r.json(); })
      .then(function (d) {
        var lista = [];
        for (var k = 0; k < d.length; k++) if (d[k] && d[k].nome) lista.push(d[k].nome);
        if (!lista.length) throw new Error('vazio');
        cache[sigla] = lista;
        if (n === vez) opcoes(lista, escolhida);
      })
      .catch(function () { if (n === vez) falhou(escolhida); });
  };

  uf.addEventListener('change', function () { carregar(uf.value, ''); });
  carregar(form.getAttribute('data-estado') || uf.value, form.getAttribute('data-cidade') || '');

  // Ao enviar, a senha volta a ser campo de senha (gerenciadores de senha e histórico).
  form.addEventListener('submit', function () {
    var bs = form.querySelectorAll('[data-ver-senha][aria-pressed=true]');
    for (var k = 0; k < bs.length; k++) bs[k].click();
  });
}, true);
