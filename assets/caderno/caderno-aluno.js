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
