/**
 * Checkout rapido — tela unica.
 *
 * Responsabilidades:
 *   1. mascarar e validar WhatsApp e CPF no cliente (conveniencia; a
 *      validacao que vale e a do servidor);
 *   2. enviar o formulario por fetch, sem trocar de pagina;
 *   3. exibir QR Code e copia-e-cola;
 *   4. consultar o status do pedido e redirecionar quando o SERVIDOR
 *      confirmar o pagamento. O cliente nunca decide que pagou.
 */
(function () {
  'use strict';

  var script = document.currentScript || document.querySelector('script[data-polling]');
  var POLLING_MS = Math.max(2, parseInt((script && script.dataset.polling) || '3', 10)) * 1000;

  var form = document.getElementById('cr-form');
  if (!form) { return; }

  var etapaForm = document.getElementById('cr-etapa-form');
  var etapaPix = document.getElementById('cr-etapa-pix');
  var etapaPago = document.getElementById('cr-etapa-pago');

  var botao = document.getElementById('cr-enviar');
  var botaoTexto = botao.querySelector('.cr-botao-texto');
  var spinner = botao.querySelector('.cr-spinner');

  var campos = {
    email: document.getElementById('cr-email'),
    whatsapp: document.getElementById('cr-whatsapp'),
    cpf: document.getElementById('cr-cpf')
  };
  var erros = {
    email: document.getElementById('cr-email-erro'),
    whatsapp: document.getElementById('cr-whatsapp-erro'),
    cpf: document.getElementById('cr-cpf-erro')
  };
  var erroGeral = document.getElementById('cr-erro-geral');

  var pedido = null;
  var timerPolling = null;
  var timerContador = null;

  // ------------------------------------------------------------------
  // Mascaras
  // ------------------------------------------------------------------

  function mascaraTelefone(valor) {
    var d = valor.replace(/\D/g, '').slice(0, 11);
    if (d.length <= 2) { return d.length ? '(' + d : ''; }
    if (d.length <= 6) { return '(' + d.slice(0, 2) + ') ' + d.slice(2); }
    if (d.length <= 10) { return '(' + d.slice(0, 2) + ') ' + d.slice(2, 6) + '-' + d.slice(6); }
    return '(' + d.slice(0, 2) + ') ' + d.slice(2, 7) + '-' + d.slice(7);
  }

  function mascaraCpf(valor) {
    var d = valor.replace(/\D/g, '').slice(0, 11);
    if (d.length <= 3) { return d; }
    if (d.length <= 6) { return d.slice(0, 3) + '.' + d.slice(3); }
    if (d.length <= 9) { return d.slice(0, 3) + '.' + d.slice(3, 6) + '.' + d.slice(6); }
    return d.slice(0, 3) + '.' + d.slice(3, 6) + '.' + d.slice(6, 9) + '-' + d.slice(9);
  }

  campos.whatsapp.addEventListener('input', function () {
    this.value = mascaraTelefone(this.value);
    limparErro('whatsapp');
  });

  campos.cpf.addEventListener('input', function () {
    this.value = mascaraCpf(this.value);
    limparErro('cpf');
  });

  campos.email.addEventListener('input', function () { limparErro('email'); });

  // ------------------------------------------------------------------
  // Validacao no cliente (espelha app/Support/Whatsapp.php e Validator::cpf)
  // ------------------------------------------------------------------

  var DDDS = [
    11,12,13,14,15,16,17,18,19,21,22,24,27,28,31,32,33,34,35,37,38,
    41,42,43,44,45,46,47,48,49,51,53,54,55,61,62,63,64,65,66,67,68,69,
    71,73,74,75,77,79,81,82,83,84,85,86,87,88,89,91,92,93,94,95,96,97,98,99
  ];

  function emailValido(valor) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(String(valor).trim());
  }

  function whatsappValido(valor) {
    var d = String(valor).replace(/\D/g, '');
    if (d.length === 13 && d.indexOf('55') === 0) { d = d.slice(2); }
    if (d.length !== 10 && d.length !== 11) { return false; }
    if (DDDS.indexOf(parseInt(d.slice(0, 2), 10)) === -1) { return false; }
    var numero = d.slice(2);
    if (numero.length === 8) { return '6789'.indexOf(numero[0]) !== -1; }
    return numero.length === 9 && numero[0] === '9';
  }

  function cpfValido(valor) {
    var d = String(valor).replace(/\D/g, '');
    if (d.length !== 11 || /^(\d)\1{10}$/.test(d)) { return false; }
    var soma = 0, i, resto;
    for (i = 0; i < 9; i++) { soma += parseInt(d[i], 10) * (10 - i); }
    resto = (soma * 10) % 11;
    if (resto === 10) { resto = 0; }
    if (resto !== parseInt(d[9], 10)) { return false; }
    soma = 0;
    for (i = 0; i < 10; i++) { soma += parseInt(d[i], 10) * (11 - i); }
    resto = (soma * 10) % 11;
    if (resto === 10) { resto = 0; }
    return resto === parseInt(d[10], 10);
  }

  function mostrarErro(campo, mensagem) {
    if (!erros[campo]) { return; }
    erros[campo].textContent = mensagem;
    erros[campo].hidden = false;
    campos[campo].setAttribute('aria-invalid', 'true');
    campos[campo].classList.add('cr-invalido');
  }

  function limparErro(campo) {
    if (!erros[campo]) { return; }
    erros[campo].hidden = true;
    campos[campo].removeAttribute('aria-invalid');
    campos[campo].classList.remove('cr-invalido');
  }

  function limparTodosOsErros() {
    Object.keys(erros).forEach(limparErro);
    erroGeral.hidden = true;
  }

  function validarNoCliente() {
    var ok = true;

    if (!emailValido(campos.email.value)) {
      mostrarErro('email', 'Esse e-mail não parece válido. Confira e tente de novo.');
      ok = false;
    }
    if (!whatsappValido(campos.whatsapp.value)) {
      mostrarErro('whatsapp', 'Informe um celular brasileiro com DDD, no formato (11) 98888-7777.');
      ok = false;
    }
    if (!cpfValido(campos.cpf.value)) {
      mostrarErro('cpf', 'Esse CPF não é válido. Confira os números.');
      ok = false;
    }

    if (!ok) {
      var primeiro = form.querySelector('.cr-invalido');
      if (primeiro) { primeiro.focus(); }
    }

    return ok;
  }

  // ------------------------------------------------------------------
  // Envio
  // ------------------------------------------------------------------

  function ocupado(estado) {
    botao.disabled = estado;
    spinner.hidden = !estado;
    botaoTexto.textContent = estado ? 'Gerando seu Pix…' : 'Gerar Pix e garantir minha vaga';
  }

  form.addEventListener('submit', function (evento) {
    evento.preventDefault();
    limparTodosOsErros();

    if (!validarNoCliente()) { return; }

    ocupado(true);

    var dados = new FormData(form);

    fetch('/comprar/pix', {
      method: 'POST',
      body: dados,
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
      .then(function (resposta) {
        return resposta.json().then(function (json) {
          return { status: resposta.status, corpo: json };
        });
      })
      .then(function (r) {
        ocupado(false);

        if (r.corpo && r.corpo.ja_matriculado) {
          // Nao redirecionamos para a area do aluno: a pessoa nao esta
          // autenticada, e preencher o formulario nao autentica ninguem.
          erroGeral.innerHTML = '';
          erroGeral.appendChild(document.createTextNode(
            r.corpo.message || 'Você já tem este curso.'
          ));
          erroGeral.appendChild(document.createTextNode(' '));
          var entrar = document.createElement('a');
          entrar.href = r.corpo.login_url || '/login';
          entrar.textContent = 'Entrar na minha conta';
          erroGeral.appendChild(entrar);
          erroGeral.hidden = false;
          erroGeral.scrollIntoView({ behavior: 'smooth', block: 'center' });
          return;
        }

        if (!r.corpo || r.corpo.ok !== true) {
          if (r.corpo && r.corpo.errors) {
            Object.keys(r.corpo.errors).forEach(function (campo) {
              if (erros[campo]) {
                mostrarErro(campo, r.corpo.errors[campo]);
              } else {
                erroGeral.textContent = r.corpo.errors[campo];
                erroGeral.hidden = false;
              }
            });
          } else {
            erroGeral.textContent = (r.corpo && r.corpo.message) ||
              'Não foi possível gerar seu Pix agora. Tente novamente em instantes.';
            erroGeral.hidden = false;
          }
          return;
        }

        if (r.corpo.gratuito) {
          etapaForm.hidden = true;
          etapaPago.hidden = false;
          return;
        }

        pedido = r.corpo;
        irParaPix(r.corpo);
      })
      .catch(function () {
        ocupado(false);
        erroGeral.textContent = 'Sua conexão falhou. Tente enviar de novo.';
        erroGeral.hidden = false;
      });
  });

  // ------------------------------------------------------------------
  // Etapa do Pix
  // ------------------------------------------------------------------

  function irParaPix(dados) {
    etapaForm.hidden = true;
    etapaPix.hidden = false;
    etapaPix.scrollIntoView({ behavior: 'smooth', block: 'start' });

    if (dados.valor) {
      document.getElementById('cr-pix-valor').textContent =
        'R$ ' + Number(dados.valor).toFixed(2).replace('.', ',');
    }

    if (dados.pix && dados.pix.qr_base64) {
      renderizarPix(dados.pix);
    } else {
      // A cobranca Pix ainda nao esta plugada nesta rota.
      document.getElementById('cr-qr-placeholder').textContent =
        dados.message || 'Seu pedido foi aberto. O Pix aparecerá aqui.';
    }

    iniciarPolling();
  }

  function renderizarPix(pix) {
    var qr = document.getElementById('cr-qr');
    var placeholder = document.getElementById('cr-qr-placeholder');

    if (pix.qr_base64) {
      qr.src = pix.qr_base64.indexOf('data:') === 0
        ? pix.qr_base64
        : 'data:image/png;base64,' + pix.qr_base64;
      qr.hidden = false;
      placeholder.hidden = true;
    }

    if (pix.copia_cola) {
      document.getElementById('cr-copiacola').value = pix.copia_cola;
    }

    if (pix.expira_em) {
      iniciarContador(pix.expira_em);
    }
  }

  document.getElementById('cr-copiar').addEventListener('click', function () {
    var campo = document.getElementById('cr-copiacola');
    if (!campo.value) { return; }

    campo.select();
    campo.setSelectionRange(0, 99999);

    var avisar = function () {
      var aviso = document.getElementById('cr-copiado');
      aviso.hidden = false;
      window.setTimeout(function () { aviso.hidden = true; }, 2500);
    };

    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(campo.value).then(avisar, function () {
        document.execCommand('copy');
        avisar();
      });
      return;
    }

    document.execCommand('copy');
    avisar();
  });

  function iniciarContador(expiraEm) {
    var alvo = new Date(String(expiraEm).replace(' ', 'T')).getTime();
    if (isNaN(alvo)) { return; }

    var validade = document.getElementById('cr-validade');
    var contador = document.getElementById('cr-contador');
    validade.hidden = false;

    if (timerContador) { window.clearInterval(timerContador); }

    var tique = function () {
      var restante = Math.max(0, Math.floor((alvo - Date.now()) / 1000));
      var m = Math.floor(restante / 60);
      var s = restante % 60;
      contador.textContent = (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;

      if (restante <= 0) {
        window.clearInterval(timerContador);
        marcarExpirado();
      }
    };

    tique();
    timerContador = window.setInterval(tique, 1000);
  }

  function marcarExpirado() {
    pararPolling();
    document.getElementById('cr-aguardando').hidden = true;
    document.getElementById('cr-expirado').hidden = false;
  }

  document.getElementById('cr-novo-pix').addEventListener('click', function () {
    document.getElementById('cr-expirado').hidden = true;
    document.getElementById('cr-aguardando').hidden = false;
    document.getElementById('cr-qr').hidden = true;
    document.getElementById('cr-qr-placeholder').hidden = false;
    document.getElementById('cr-qr-placeholder').textContent = 'Gerando um novo Pix…';
    form.dispatchEvent(new Event('submit', { cancelable: true }));
  });

  // ------------------------------------------------------------------
  // Polling — a fonte da verdade e o servidor
  // ------------------------------------------------------------------

  function iniciarPolling() {
    pararPolling();
    timerPolling = window.setInterval(consultarStatus, POLLING_MS);
  }

  function pararPolling() {
    if (timerPolling) {
      window.clearInterval(timerPolling);
      timerPolling = null;
    }
  }

  function consultarStatus() {
    if (!pedido || !pedido.pedido_id) { return; }
    if (document.hidden) { return; }

    var url = '/comprar/status?pedido_id=' + encodeURIComponent(pedido.pedido_id) +
      '&codigo=' + encodeURIComponent(pedido.pedido_codigo);

    fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.json(); })
      .then(function (json) {
        if (!json || json.ok !== true) { return; }

        if (json.pago) {
          pararPolling();
          mostrarPago(json.destino);
          return;
        }

        if (json.expirado) { marcarExpirado(); }
      })
      .catch(function () { /* rede instavel: a proxima tentativa resolve */ });
  }

  function mostrarPago(destino) {
    if (timerContador) { window.clearInterval(timerContador); }

    etapaPix.hidden = true;
    etapaPago.hidden = false;
    etapaPago.scrollIntoView({ behavior: 'smooth', block: 'start' });

    // Redirecionar direto para a area do aluno so funciona para quem tem
    // sessao aberta. Quem comprou por aqui nao tem senha nem sessao: cairia
    // na tela de login. O acesso e entregue pelo link enviado por e-mail.
    var ir = document.getElementById('cr-ir-curso');
    if (destino && temSessao()) {
      ir.href = destino;
      window.setTimeout(function () { window.location.href = destino; }, 2500);
    } else {
      ir.href = '/login';
      ir.textContent = 'Entrar na minha conta';
    }
  }

  /** Ha sessao de aluno aberta neste navegador? */
  function temSessao() {
    return document.body.dataset.autenticado === '1';
  }

  // Voltar para a aba retoma a consulta na hora, sem esperar o intervalo.
  document.addEventListener('visibilitychange', function () {
    if (!document.hidden && timerPolling) { consultarStatus(); }
  });
})();
