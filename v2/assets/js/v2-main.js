/* ============================================================
   DESBLOQUEIA CURSOS — V2 (demonstração estática)
   Vanilla JS. Nenhuma chamada de rede. preventDefault em forms.
   ============================================================ */
(function () {
  "use strict";

  var CURSOS = window.V2_CURSOS || [];
  var CATS = window.V2_CATEGORIAS || {};
  var BRL = function (v) { return "R$ " + Number(v).toFixed(2).replace(".", ","); };
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };

  function esc(s) {
    return String(s == null ? "" : s).replace(/[&<>"']/g, function (m) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[m];
    });
  }

  function toast(msg) {
    var t = $("#v2-toast");
    if (!t) { t = document.createElement("div"); t.id = "v2-toast"; t.className = "v2-toast"; (document.querySelector(".v2-app") || document.body).appendChild(t); }
    t.innerHTML = '<i class="ti ti-circle-check"></i><span>' + esc(msg) + "</span>";
    t.classList.add("is-on");
    clearTimeout(t._t);
    t._t = setTimeout(function () { t.classList.remove("is-on"); }, 2600);
  }

  /* localStorage (estado da demonstração) ---------------------- */
  var STORE = "v2_demo_state";
  function loadState() { try { return JSON.parse(localStorage.getItem(STORE)) || {}; } catch (e) { return {}; } }
  function saveState(s) { try { localStorage.setItem(STORE, JSON.stringify(s)); } catch (e) {} }

  /* Markup de um card de curso -------------------------------- */
  function catStyle(cat) {
    var c = CATS[cat] || { icon: "ti-book", g1: "#f5f0ff", g2: "#ede8f5", cor: "#6B7280" };
    return c;
  }
  function cardHTML(curso) {
    var c = catStyle(curso.categoria);
    var badges = "";
    if (curso.destaque) badges += '<span class="v2-badge v2-badge-destaque"><i class="ti ti-flame"></i> Destaque</span>';
    if (curso.novo) badges += '<span class="v2-badge v2-badge-novo">Novo</span>';
    return '' +
      '<a class="v2-card" href="/v2/curso/?id=' + encodeURIComponent(curso.id) + '" aria-label="' + esc(curso.titulo) + '">' +
        '<div class="v2-thumb" style="background:linear-gradient(135deg,' + c.g1 + ',' + c.g2 + ');">' +
          (badges ? '<div class="v2-thumb-badges">' + badges + "</div>" : "") +
          '<i class="ti ' + c.icon + '" style="color:' + c.cor + ';"></i>' +
        "</div>" +
        '<div class="v2-card-body">' +
          '<span class="v2-card-cat">' + esc(curso.categoria) + "</span>" +
          '<h3 class="v2-card-title">' + esc(curso.titulo) + "</h3>" +
          '<div class="v2-card-meta">' +
            '<span><i class="ti ti-clock"></i>' + curso.cargaHoraria + "h</span>" +
            '<span><i class="ti ti-device-desktop"></i>' + esc(curso.modalidade) + "</span>" +
          "</div>" +
          '<div class="v2-card-rate"><i class="ti ti-star-filled"></i>' + curso.nota.toFixed(1) +
            ' <span class="v2-muted v2-sm">(' + curso.avaliacoes + ")</span></div>" +
          '<div class="v2-card-foot">' +
            '<span class="v2-price">' + BRL(curso.preco) + "</span>" +
            '<span class="v2-btn v2-btn-outline v2-btn-sm">Ver curso</span>' +
          "</div>" +
        "</div>" +
      "</a>";
  }

  /* ---------------- HOME: render destaques/top/categorias ---- */
  function renderHome() {
    var soCursos = CURSOS.filter(function (c) { return c.tipo !== "evento"; });
    var dest = $("#v2-destaques");
    if (dest) dest.innerHTML = soCursos.filter(function (c) { return c.destaque; }).map(cardHTML).join("");
    var top = $("#v2-top");
    if (top) top.innerHTML = soCursos.slice().sort(function (a, b) { return b.alunos - a.alunos; }).slice(0, 6).map(cardHTML).join("");
    var cats = $("#v2-cats");
    var CDE = window.V2_CATEGORIA_DE || {};
    if (cats) {
      cats.innerHTML = Object.keys(CATS).map(function (nome) {
        var c = CATS[nome];
        var q = soCursos.filter(function (x) { return x.categoria === nome; }).length;
        var cid = CDE[nome] || "";
        return '<a class="v2-cat" href="/v2/categorias/' + (cid ? "?id=" + encodeURIComponent(cid) : "") + '">' +
          '<span class="v2-cat-ic" style="background:linear-gradient(135deg,' + c.g1 + ',' + c.g2 + ');"><i class="ti ' + c.icon + '" style="color:' + c.cor + ';"></i></span>' +
          '<span class="v2-cat-nome">' + esc(nome) + "</span>" +
          '<span class="v2-cat-q">' + q + (q === 1 ? " curso" : " cursos") + "</span></a>";
      }).join("");
    }
  }

  /* ---------------- CATÁLOGO ---------------------------------- */
  function renderCatalogo() {
    var grid = $("#v2-catalog-grid");
    if (!grid) return;
    var state = { q: "", cat: "todos", modalidade: "", preco: "", ordem: "relevancia", page: 1, perPage: 6 };

    // chips de categoria
    var chipsBox = $("#v2-cat-chips");
    if (chipsBox) {
      var chips = '<button class="v2-chip is-on" data-cat="todos">Todos</button>';
      Object.keys(CATS).forEach(function (nome) { chips += '<button class="v2-chip" data-cat="' + esc(nome) + '">' + esc(nome) + "</button>"; });
      chipsBox.innerHTML = chips;
    }

    // pré-seleção via querystring ?cat=
    var qs = new URLSearchParams(location.search);
    if (qs.get("cat") && CATS[qs.get("cat")]) state.cat = qs.get("cat");

    function matches(c) {
      if (state.q) {
        var hay = (c.titulo + " " + c.categoria + " " + c.descricaoCurta).toLowerCase();
        if (hay.indexOf(state.q.toLowerCase()) === -1) return false;
      }
      if (state.cat !== "todos" && c.categoria !== state.cat) return false;
      if (state.modalidade && c.modalidade.toLowerCase() !== state.modalidade) return false;
      if (state.preco === "ate150" && c.preco > 150) return false;
      if (state.preco === "150a200" && (c.preco < 150 || c.preco > 200)) return false;
      if (state.preco === "200mais" && c.preco < 200) return false;
      return true;
    }
    function sortFn(a, b) {
      if (state.ordem === "preco-asc") return a.preco - b.preco;
      if (state.ordem === "preco-desc") return b.preco - a.preco;
      if (state.ordem === "nota") return b.nota - a.nota;
      if (state.ordem === "alunos") return b.alunos - a.alunos;
      return (b.destaque - a.destaque) || (b.alunos - a.alunos);
    }

    function render() {
      var list = CURSOS.filter(matches).sort(sortFn);
      var count = $("#v2-result-count");
      if (count) count.textContent = list.length + (list.length === 1 ? " curso encontrado" : " cursos encontrados");

      // sincroniza chips
      $$(".v2-chip", chipsBox).forEach(function (ch) { ch.classList.toggle("is-on", ch.getAttribute("data-cat") === state.cat); });

      if (!list.length) {
        grid.innerHTML = "";
        $("#v2-empty").style.display = "block";
        $("#v2-pagination").innerHTML = "";
        return;
      }
      $("#v2-empty").style.display = "none";

      var pages = Math.ceil(list.length / state.perPage);
      if (state.page > pages) state.page = 1;
      var start = (state.page - 1) * state.perPage;
      grid.innerHTML = list.slice(start, start + state.perPage).map(cardHTML).join("");

      var pag = "";
      if (pages > 1) {
        for (var i = 1; i <= pages; i++) pag += '<button class="v2-page' + (i === state.page ? " is-on" : "") + '" data-page="' + i + '">' + i + "</button>";
      }
      $("#v2-pagination").innerHTML = pag;
    }

    // eventos
    var search = $("#v2-search");
    if (search) { search.value = ""; search.addEventListener("input", function () { state.q = this.value; state.page = 1; render(); }); }
    if (chipsBox) chipsBox.addEventListener("click", function (e) { var b = e.target.closest(".v2-chip"); if (!b) return; state.cat = b.getAttribute("data-cat"); state.page = 1; render(); });
    var ordem = $("#v2-ordem"); if (ordem) ordem.addEventListener("change", function () { state.ordem = this.value; render(); });

    $$('input[name="v2-modalidade"]').forEach(function (r) { r.addEventListener("change", function () { state.modalidade = this.value; state.page = 1; render(); }); });
    $$('input[name="v2-preco"]').forEach(function (r) { r.addEventListener("change", function () { state.preco = this.value; state.page = 1; render(); }); });

    $("#v2-pagination").addEventListener("click", function (e) { var b = e.target.closest(".v2-page"); if (!b) return; state.page = parseInt(b.getAttribute("data-page"), 10); render(); window.scrollTo({ top: 0, behavior: "smooth" }); });

    $$("[data-clear-filters]").forEach(function (clear) {
      clear.addEventListener("click", function () {
        state.q = ""; state.cat = "todos"; state.modalidade = ""; state.preco = ""; state.ordem = "relevancia"; state.page = 1;
        if (search) search.value = "";
        $$('input[name="v2-modalidade"],input[name="v2-preco"]').forEach(function (r) { r.checked = r.value === ""; });
        if (ordem) ordem.value = "relevancia";
        render();
      });
    });

    render();
  }

  /* ---------------- CATÁLOGO V2 (/v2/catalogo/) -------------- */
  /* Página dedicada de catálogo: busca, chips de categoria, filtros
     combinados (modalidade, preço, carga, destaques), ordenação,
     paginação, estado vazio e persistência via query string.
     100% client-side — nenhuma chamada de rede. */
  function nf(n) { try { return Number(n).toLocaleString("pt-BR"); } catch (e) { return String(n); } }

  function catalogoCardHTML(curso) {
    var c = catStyle(curso.categoria);
    var badges = "";
    if (curso.destaque) badges += '<span class="v2-badge v2-badge-destaque"><i class="ti ti-flame"></i> Destaque</span>';
    if (curso.novo) badges += '<span class="v2-badge v2-badge-novo">Novo</span>';
    var href = "/v2/curso/?id=" + encodeURIComponent(curso.id);
    return '' +
      '<a class="v2-card" href="' + esc(href) + '" aria-label="' + esc(curso.titulo) + '">' +
        '<div class="v2-thumb" style="background:linear-gradient(135deg,' + c.g1 + ',' + c.g2 + ');">' +
          (badges ? '<div class="v2-thumb-badges">' + badges + "</div>" : "") +
          '<i class="ti ' + c.icon + '" style="color:' + c.cor + ';"></i>' +
        "</div>" +
        '<div class="v2-card-body">' +
          '<span class="v2-card-cat">' + esc(curso.categoria) + "</span>" +
          '<h3 class="v2-card-title">' + esc(curso.titulo) + "</h3>" +
          '<div class="v2-card-meta">' +
            '<span><i class="ti ti-clock"></i>' + curso.cargaHoraria + "h</span>" +
            '<span><i class="ti ti-device-desktop"></i>' + esc(curso.modalidade) + "</span>" +
          "</div>" +
          '<div class="v2-card-meta">' +
            '<span class="v2-card-rate"><i class="ti ti-star-filled"></i>' + curso.nota.toFixed(1) +
              ' <span class="v2-muted v2-sm">(' + curso.avaliacoes + ")</span></span>" +
            '<span><i class="ti ti-users"></i>' + nf(curso.alunos) + " alunos</span>" +
          "</div>" +
          '<div class="v2-card-foot">' +
            '<span class="v2-price">' + BRL(curso.preco) + "</span>" +
            '<span class="v2-btn v2-btn-outline v2-btn-sm">Ver curso</span>' +
          "</div>" +
        "</div>" +
      "</a>";
  }

  function renderCatalogoV2() {
    var grid = $("#v2c-grid");
    if (!grid) return;

    var state = { q: "", cat: "todos", mod: "", preco: "", carga: "", destaque: false, ordem: "relevancia", page: 1, perPage: 6 };

    // Apenas cursos (eventos têm página própria em /v2/eventos/)
    var ITENS = CURSOS.filter(function (c) { return c.tipo !== "evento"; });

    // Categorias presentes nos dados (mantém a ordem de V2_CATEGORIAS e acrescenta extras)
    var ordemCats = Object.keys(CATS);
    var presentes = [];
    ITENS.forEach(function (c) { if (presentes.indexOf(c.categoria) === -1) presentes.push(c.categoria); });
    var cats = ordemCats.filter(function (n) { return presentes.indexOf(n) !== -1; });
    presentes.forEach(function (n) { if (cats.indexOf(n) === -1) cats.push(n); });

    // Modalidades presentes nos dados
    var mods = [];
    ITENS.forEach(function (c) { if (mods.indexOf(c.modalidade) === -1) mods.push(c.modalidade); });

    // Chips de categoria
    var chipsBox = $("#v2c-chips");
    if (chipsBox) {
      var chips = '<button type="button" class="v2-chip is-on" data-cat="todos">Todos</button>';
      cats.forEach(function (n) { chips += '<button type="button" class="v2-chip" data-cat="' + esc(n) + '">' + esc(n) + "</button>"; });
      chipsBox.innerHTML = chips;
    }

    // Radios de modalidade (somente as existentes nos dados)
    var modBox = $("#v2c-modalidade");
    if (modBox) {
      var html = '<label class="v2-fopt"><input type="radio" name="v2c-modalidade" value="" checked> Todas</label>';
      mods.forEach(function (m) {
        html += '<label class="v2-fopt"><input type="radio" name="v2c-modalidade" value="' + esc(m.toLowerCase()) + '"> ' + esc(m) + "</label>";
      });
      modBox.innerHTML = html;
    }

    var search = $("#v2c-search");
    var order = $("#v2c-order");
    var destaqueChk = $("#v2c-destaque");
    var countEl = $("#v2c-count");
    var emptyEl = $("#v2c-empty");
    var pagEl = $("#v2c-pag");
    var filters = $("#v2c-filters");
    var overlay = $("#v2c-overlay");

    /* ---- Query string <-> estado ---- */
    function readQuery() {
      var qs = new URLSearchParams(location.search);
      if (qs.get("q")) state.q = qs.get("q");
      if (qs.get("cat") && (qs.get("cat") === "todos" || cats.indexOf(qs.get("cat")) !== -1)) state.cat = qs.get("cat");
      if (qs.get("mod")) state.mod = qs.get("mod").toLowerCase();
      if (["gratis", "ate100", "100a150", "acima150"].indexOf(qs.get("preco")) !== -1) state.preco = qs.get("preco");
      if (["ate10", "11a30", "acima30"].indexOf(qs.get("carga")) !== -1) state.carga = qs.get("carga");
      if (qs.get("destaque") === "1") state.destaque = true;
      if (["relevancia", "nota", "preco-asc", "preco-desc", "alfabetica"].indexOf(qs.get("ordem")) !== -1) state.ordem = qs.get("ordem");
      var p = parseInt(qs.get("page"), 10); if (p > 0) state.page = p;
    }
    function writeQuery() {
      var qs = new URLSearchParams();
      if (state.q) qs.set("q", state.q);
      if (state.cat !== "todos") qs.set("cat", state.cat);
      if (state.mod) qs.set("mod", state.mod);
      if (state.preco) qs.set("preco", state.preco);
      if (state.carga) qs.set("carga", state.carga);
      if (state.destaque) qs.set("destaque", "1");
      if (state.ordem !== "relevancia") qs.set("ordem", state.ordem);
      if (state.page > 1) qs.set("page", state.page);
      var str = qs.toString();
      try { history.replaceState(null, "", location.pathname + (str ? "?" + str : "")); } catch (e) {}
    }

    /* ---- Reflete estado nos controles ---- */
    function syncControls() {
      if (search) search.value = state.q;
      if (order) order.value = state.ordem;
      if (destaqueChk) destaqueChk.checked = state.destaque;
      $$('input[name="v2c-modalidade"]').forEach(function (r) { r.checked = (r.value === state.mod); });
      $$('input[name="v2c-preco"]').forEach(function (r) { r.checked = (r.value === state.preco); });
      $$('input[name="v2c-carga"]').forEach(function (r) { r.checked = (r.value === state.carga); });
      if (chipsBox) $$(".v2-chip", chipsBox).forEach(function (ch) { ch.classList.toggle("is-on", ch.getAttribute("data-cat") === state.cat); });
    }

    function matches(c) {
      if (state.q) {
        var hay = (c.titulo + " " + c.categoria + " " + c.modalidade + " " + (c.descricaoCurta || "")).toLowerCase();
        if (hay.indexOf(state.q.toLowerCase()) === -1) return false;
      }
      if (state.cat !== "todos" && c.categoria !== state.cat) return false;
      if (state.mod && c.modalidade.toLowerCase() !== state.mod) return false;
      if (state.preco === "gratis" && c.preco !== 0) return false;
      if (state.preco === "ate100" && c.preco > 100) return false;
      if (state.preco === "100a150" && (c.preco <= 100 || c.preco > 150)) return false;
      if (state.preco === "acima150" && c.preco <= 150) return false;
      if (state.carga === "ate10" && c.cargaHoraria > 10) return false;
      if (state.carga === "11a30" && (c.cargaHoraria < 11 || c.cargaHoraria > 30)) return false;
      if (state.carga === "acima30" && c.cargaHoraria <= 30) return false;
      if (state.destaque && !c.destaque) return false;
      return true;
    }
    function sortFn(a, b) {
      if (state.ordem === "preco-asc") return a.preco - b.preco;
      if (state.ordem === "preco-desc") return b.preco - a.preco;
      if (state.ordem === "nota") return (b.nota - a.nota) || (b.avaliacoes - a.avaliacoes);
      if (state.ordem === "alfabetica") return a.titulo.localeCompare(b.titulo, "pt-BR");
      return (b.destaque - a.destaque) || (b.alunos - a.alunos);
    }

    function render() {
      var list = ITENS.filter(matches).sort(sortFn);
      if (countEl) countEl.textContent = list.length + (list.length === 1 ? " curso encontrado" : " cursos encontrados");
      if (chipsBox) $$(".v2-chip", chipsBox).forEach(function (ch) { ch.classList.toggle("is-on", ch.getAttribute("data-cat") === state.cat); });

      if (!list.length) {
        grid.innerHTML = "";
        if (emptyEl) emptyEl.style.display = "block";
        if (pagEl) pagEl.innerHTML = "";
        writeQuery();
        return;
      }
      if (emptyEl) emptyEl.style.display = "none";

      var pages = Math.ceil(list.length / state.perPage);
      if (state.page > pages) state.page = pages;
      if (state.page < 1) state.page = 1;
      var start = (state.page - 1) * state.perPage;
      grid.innerHTML = list.slice(start, start + state.perPage).map(catalogoCardHTML).join("");

      var pag = "";
      if (pages > 1) {
        for (var i = 1; i <= pages; i++) pag += '<button type="button" class="v2-page' + (i === state.page ? " is-on" : "") + '" data-page="' + i + '">' + i + "</button>";
      }
      if (pagEl) pagEl.innerHTML = pag;
      writeQuery();
    }

    /* ---- Bottom sheet (mobile) ---- */
    function openSheet() { if (filters) filters.classList.add("is-open"); if (overlay) overlay.classList.add("is-open"); }
    function closeSheet() { if (filters) filters.classList.remove("is-open"); if (overlay) overlay.classList.remove("is-open"); }

    /* ---- Eventos (vinculados uma única vez) ---- */
    if (search) search.addEventListener("input", function () { state.q = this.value; state.page = 1; render(); });
    if (order) order.addEventListener("change", function () { state.ordem = this.value; state.page = 1; render(); });
    if (destaqueChk) destaqueChk.addEventListener("change", function () { state.destaque = this.checked; state.page = 1; render(); });
    if (chipsBox) chipsBox.addEventListener("click", function (e) { var b = e.target.closest(".v2-chip"); if (!b) return; state.cat = b.getAttribute("data-cat"); state.page = 1; render(); });

    $$('input[name="v2c-modalidade"]').forEach(function (r) { r.addEventListener("change", function () { state.mod = this.value; state.page = 1; render(); }); });
    $$('input[name="v2c-preco"]').forEach(function (r) { r.addEventListener("change", function () { state.preco = this.value; state.page = 1; render(); }); });
    $$('input[name="v2c-carga"]').forEach(function (r) { r.addEventListener("change", function () { state.carga = this.value; state.page = 1; render(); }); });

    if (pagEl) pagEl.addEventListener("click", function (e) {
      var b = e.target.closest(".v2-page"); if (!b) return;
      state.page = parseInt(b.getAttribute("data-page"), 10);
      render();
      window.scrollTo({ top: 0, behavior: "smooth" });
    });

    $$("[data-open-filters-c]").forEach(function (b) { b.addEventListener("click", openSheet); });
    $$("[data-close-filters-c]").forEach(function (b) { b.addEventListener("click", closeSheet); });
    if (overlay) overlay.addEventListener("click", closeSheet);
    document.addEventListener("keydown", function (e) {
      if ((e.key === "Escape" || e.key === "Esc") && filters && filters.classList.contains("is-open")) closeSheet();
    });

    $$("[data-clear-filters-c]").forEach(function (clear) {
      clear.addEventListener("click", function () {
        state.q = ""; state.cat = "todos"; state.mod = ""; state.preco = ""; state.carga = ""; state.destaque = false; state.ordem = "relevancia"; state.page = 1;
        syncControls();
        render();
      });
    });

    // Estado inicial a partir da query string
    readQuery();
    syncControls();
    render();
  }

  /* ---------------- CATÁLOGO V2 INTEGRADO (server-side) ------ */
  /* Não renderiza dados: o catálogo /v2/catalogo/ é entregue pelo backend
     com dados reais via GET. Aqui só melhoramos a UX do formulário GET —
     abrir/fechar o bottom sheet de filtros (mobile) e enviar a ordenação ao
     trocar o select. Busca, filtros, ordenação e paginação funcionam sem JS. */
  function initCatalogoIntegradoV2() {
    if (!window.V2_CATALOGO_INTEGRADO) return;
    var form = $("#v2c-form");
    if (!form) return;

    var filters = $("#v2c-filters");
    var overlay = $("#v2c-overlay");

    function openSheet() { if (filters) filters.classList.add("is-open"); if (overlay) overlay.classList.add("is-open"); }
    function closeSheet() { if (filters) filters.classList.remove("is-open"); if (overlay) overlay.classList.remove("is-open"); }

    $$("[data-open-filters-c]").forEach(function (b) { b.addEventListener("click", openSheet); });
    $$("[data-close-filters-c]").forEach(function (b) { b.addEventListener("click", closeSheet); });
    if (overlay) overlay.addEventListener("click", closeSheet);
    document.addEventListener("keydown", function (e) {
      if ((e.key === "Escape" || e.key === "Esc") && filters && filters.classList.contains("is-open")) closeSheet();
    });

    // Enviar ordenação ao trocar o select (progressive enhancement).
    var order = $("#v2c-order");
    var orderApply = $("#v2c-order-apply");
    if (order) {
      order.addEventListener("change", function () { form.submit(); });
      if (orderApply) orderApply.style.display = "none"; // botão de apoio só é necessário sem JS
    }
  }

  /* ---------------- FICHA DO CURSO (/v2/curso/?id=…) --------- */
  /* Lê o ?id= da query string, busca o curso em window.V2_CURSOS e
     renderiza a ficha completa no cliente. Sem rede, sem backend. */
  function tipoInfo(t) {
    switch (t) {
      case "video": return { ic: "ti-player-play", label: "Vídeo" };
      case "texto": return { ic: "ti-file-text", label: "Leitura" };
      case "pdf": return { ic: "ti-file-type-pdf", label: "PDF" };
      case "quiz": return { ic: "ti-help-circle", label: "Quiz" };
      case "atividade": return { ic: "ti-clipboard-check", label: "Atividade" };
      default: return { ic: "ti-circle", label: "" };
    }
  }

  function renderCursoV2() {
    var root = $("#v2-curso");
    if (!root) return;

    var crumb = $("#v2-curso-breadcrumb");
    var empty = $("#v2-curso-empty");
    var ctaFixed = $("#v2-curso-ctafixed");

    var params = new URLSearchParams(location.search);
    var id = params.get("id");
    var curso = null;
    for (var i = 0; i < CURSOS.length; i++) { if (CURSOS[i].id === id) { curso = CURSOS[i]; break; } }

    // Curso inexistente → estado vazio elegante
    if (!curso) {
      if (empty) empty.style.display = "block";
      if (crumb) crumb.innerHTML =
        '<a href="/v2/">Início</a><i class="ti ti-chevron-right" aria-hidden="true"></i><a href="/v2/catalogo/">Cursos</a>';
      document.title = "Curso não encontrado — Desbloqueia Cursos";
      return;
    }

    document.title = curso.titulo + " — Desbloqueia Cursos";
    var cs = catStyle(curso.categoria);
    var turmas = (curso.turmas && curso.turmas.length) ? curso.turmas : [{ id: "", nome: "Turma única", modalidade: curso.modalidade, inicio: "Acesso imediato", vagas: null, local: null, preco: curso.preco, formato: "" }];
    var sel = 0;

    /* ---- Breadcrumb ---- */
    if (crumb) {
      crumb.innerHTML =
        '<a href="/v2/">Início</a>' +
        '<i class="ti ti-chevron-right" aria-hidden="true"></i>' +
        '<a href="/v2/catalogo/">Cursos</a>' +
        '<i class="ti ti-chevron-right" aria-hidden="true"></i>' +
        '<a href="/v2/catalogo/?cat=' + encodeURIComponent(curso.categoria) + '">' + esc(curso.categoria) + "</a>" +
        '<i class="ti ti-chevron-right" aria-hidden="true"></i>' +
        '<span aria-current="page">' + esc(curso.titulo) + "</span>";
    }

    /* ---- Capa (placeholder por categoria) ---- */
    var capaBadges = "";
    if (curso.destaque) capaBadges += '<span class="v2-badge v2-badge-destaque"><i class="ti ti-flame"></i> Destaque</span>';
    if (curso.novo) capaBadges += '<span class="v2-badge v2-badge-novo">Novo</span>';
    var cover = '<div class="v2-cover" style="background:linear-gradient(135deg,' + cs.g1 + ',' + cs.g2 + ');">' +
      (capaBadges ? '<div class="v2-thumb-badges">' + capaBadges + "</div>" : "") +
      '<i class="ti ' + cs.icon + '" style="color:' + cs.cor + ';"></i>' +
      "</div>";

    /* ---- Avaliação + meta ---- */
    var rate = '<div class="v2-card-rate v2-curso-rate"><i class="ti ti-star-filled"></i> ' +
      Number(curso.nota).toFixed(1).replace(".", ",") +
      ' <span class="v2-muted v2-sm">(' + (curso.totalAvaliacoes || curso.avaliacoes) + " avaliações) · " + nf(curso.totalAlunos || curso.alunos) + " alunos</span></div>";
    var meta = '<div class="v2-course-meta">' +
      '<span><i class="ti ti-clock"></i> ' + curso.cargaHoraria + " horas</span>" +
      '<span><i class="ti ti-device-desktop"></i> ' + esc(curso.modalidade) + "</span>" +
      (curso.nivel ? '<span><i class="ti ti-stairs-up"></i> ' + esc(curso.nivel) + "</span>" : "") +
      (curso.certificado ? '<span><i class="ti ti-certificate"></i> Certificado incluso</span>' : "") +
      (curso.acesso ? '<span><i class="ti ti-infinity"></i> ' + esc(curso.acesso) + "</span>" : "") +
      "</div>";

    /* ---- O que você vai aprender ---- */
    var objetivos = curso.objetivos || curso.topicos || [];
    var aprender = "";
    if (objetivos.length) {
      aprender = '<div class="v2-block"><h2 class="v2-h3" style="margin-bottom:12px;">O que você vai aprender</h2><ul class="v2-learn">' +
        objetivos.map(function (o) { return '<li><i class="ti ti-circle-check-filled"></i> ' + esc(o) + "</li>"; }).join("") +
        "</ul></div>";
    }

    /* ---- Sobre o curso ---- */
    var descParas = String(curso.descricao || curso.descricaoCurta || "").split("\n\n")
      .map(function (p) { return '<p class="v2-muted">' + esc(p) + "</p>"; }).join("");
    var sobre = '<div class="v2-block v2-curso-prose"><h2 class="v2-h3" style="margin-bottom:8px;">Sobre o curso</h2>' + descParas + "</div>";

    /* ---- Conteúdo programático (accordion) ---- */
    var modulos = curso.conteudosProgramaticos || [];
    var totalAulas = 0;
    var modsHtml = modulos.map(function (m, idx) {
      var aulas = m.aulas || [];
      totalAulas += aulas.length;
      var aulasHtml = aulas.map(function (a) {
        var t = tipoInfo(a.tipo);
        return '<div class="v2-acc-aula">' +
          '<i class="ti ' + t.ic + ' v2-acc-aula-ic" aria-hidden="true"></i>' +
          '<span class="v2-acc-aula-label">' + esc(a.titulo) + "</span>" +
          '<span class="v2-acc-aula-dur">' + esc(a.duracao ? a.duracao : t.label) + "</span>" +
          "</div>";
      }).join("");
      var open = idx === 0;
      var paneId = "v2-acc-pane-" + idx;
      return '<div class="v2-acc' + (open ? " is-open" : "") + '">' +
        '<button type="button" class="v2-acc-head" aria-expanded="' + (open ? "true" : "false") + '" aria-controls="' + paneId + '">' +
          '<span class="v2-mod-num">' + (idx + 1) + "</span>" +
          '<span class="v2-acc-head-info"><b>' + esc(m.titulo) + "</b>" +
            "<small>" + aulas.length + (aulas.length === 1 ? " aula" : " aulas") + (m.duracao ? " · " + esc(m.duracao) : "") + "</small></span>" +
          '<i class="ti ti-chevron-down v2-acc-chev" aria-hidden="true"></i>' +
        "</button>" +
        '<div class="v2-acc-body" id="' + paneId + '">' + aulasHtml + "</div>" +
        "</div>";
    }).join("");
    var conteudo = "";
    if (modsHtml) {
      conteudo = '<div class="v2-block"><h2 class="v2-h3" style="margin-bottom:6px;">Conteúdo programático</h2>' +
        '<p class="v2-muted v2-sm" style="margin-bottom:12px;">' + modulos.length + (modulos.length === 1 ? " módulo" : " módulos") + " · " + totalAulas + " aulas · " + curso.cargaHoraria + " horas</p>" +
        modsHtml + "</div>";
    }

    /* ---- Turmas disponíveis (selecionáveis) ---- */
    var turmasHtml = turmas.map(function (t, idx) {
      var badge = (t.vagas === null || t.vagas === undefined)
        ? '<span class="v2-badge v2-badge-gratis">Vagas abertas</span>'
        : '<span class="v2-badge v2-badge-destaque">' + t.vagas + " vagas</span>";
      var metas = '<span><i class="ti ti-calendar"></i> ' + esc(t.inicio) + "</span>" +
        '<span><i class="ti ti-' + (t.modalidade === "Presencial" ? "map-pin" : "device-desktop") + '"></i> ' + esc(t.modalidade) + "</span>" +
        (t.local ? '<span><i class="ti ti-map-pin"></i> ' + esc(t.local) + "</span>" : "") +
        (t.formato ? '<span><i class="ti ti-info-circle"></i> ' + esc(t.formato) + "</span>" : "");
      return '<label class="v2-turma v2-turma-sel' + (idx === sel ? " is-sel" : "") + '" data-turma="' + idx + '">' +
        '<span class="v2-turma-radio"><input type="radio" name="v2-curso-turma" value="' + idx + '"' + (idx === sel ? " checked" : "") + ' aria-label="Selecionar ' + esc(t.nome) + '"></span>' +
        '<span class="v2-turma-body">' +
          '<span class="v2-turma-top"><span class="v2-turma-nome">' + esc(t.nome) + "</span>" + badge + "</span>" +
          '<span class="v2-turma-meta">' + metas + "</span>" +
          '<span class="v2-turma-preco">' + BRL(t.preco) + "</span>" +
        "</span></label>";
    }).join("");
    var turmasBlock = '<div class="v2-block"><h2 class="v2-h3" style="margin-bottom:12px;">Turmas disponíveis</h2>' + turmasHtml + "</div>";

    /* ---- Instrutor ---- */
    var instrutor = "";
    if (curso.instrutor) {
      var ins = curso.instrutor;
      instrutor = '<div class="v2-block"><h2 class="v2-h3" style="margin-bottom:12px;">Instrutor</h2>' +
        '<div class="v2-instrutor">' +
          '<span class="v2-instrutor-av" aria-hidden="true">' + esc(ins.iniciais || (ins.nome || "?").charAt(0)) + "</span>" +
          '<div class="v2-instrutor-body">' +
            "<b>" + esc(ins.nome) + "</b>" +
            (ins.area ? '<span class="v2-card-cat" style="display:block;margin:2px 0 6px;">' + esc(ins.area) + "</span>" : "") +
            '<p class="v2-muted v2-sm" style="margin:0;">' + esc(ins.bio) + "</p>" +
          "</div>" +
        "</div></div>";
    }

    /* ---- Perguntas frequentes (accordion) ---- */
    var faqs = [
      ["Como acesso o curso?", "Após a matrícula, o curso fica disponível na sua área do aluno. Esta é uma demonstração visual da V2 — nenhuma compra é processada de verdade."],
      ["O curso oferece certificado?", "Os cursos desta demonstração indicam certificado de conclusão. Por ser uma demonstração, nenhum certificado real é emitido."],
      ["Por quanto tempo terei acesso?", "O tempo de acesso aparece nas informações do curso (por exemplo, acesso vitalício). Os prazos são ilustrativos nesta V2."],
      ["Posso comprar para outra pessoa?", "No checkout da demonstração é possível indicar que a compra é para outra pessoa. Nenhum dado é enviado ao servidor."],
      ["Como funciona o pagamento?", "O checkout simula as etapas de pagamento (inclusive PIX) apenas de forma visual. Esta V2 é demonstrativa e não realiza cobranças."]
    ];
    var faqHtml = faqs.map(function (f, idx) {
      var paneId = "v2-faq-pane-" + idx;
      return '<div class="v2-acc">' +
        '<button type="button" class="v2-acc-head" aria-expanded="false" aria-controls="' + paneId + '">' +
          '<span class="v2-acc-head-info"><b>' + esc(f[0]) + "</b></span>" +
          '<i class="ti ti-chevron-down v2-acc-chev" aria-hidden="true"></i>' +
        "</button>" +
        '<div class="v2-acc-body" id="' + paneId + '"><p class="v2-muted" style="margin:0;">' + esc(f[1]) + "</p></div>" +
        "</div>";
    }).join("");
    var faqBlock = '<div class="v2-block"><h2 class="v2-h3" style="margin-bottom:12px;">Perguntas frequentes</h2>' + faqHtml + "</div>";

    /* ---- Aside / card de compra ---- */
    var aside = '<aside class="v2-course-aside">' +
      '<div class="v2-cta-card">' +
        '<div class="v2-cta-price" id="v2-curso-price">' + BRL(turmas[sel].preco) + "</div>" +
        '<p class="v2-muted v2-sm" style="margin-bottom:14px;">ou em até 12x no cartão <span class="v2-sm">(demonstração)</span></p>' +
        '<div class="v2-cta-turma v2-sm"><i class="ti ti-users"></i> Turma: <b id="v2-curso-turma-nome">' + esc(turmas[sel].nome) + "</b></div>" +
        '<a href="#" class="v2-btn v2-btn-primary v2-btn-block" id="v2-curso-cta">Quero desbloquear <i class="ti ti-arrow-right"></i></a>' +
        '<ul class="v2-cta-list">' +
          '<li><i class="ti ti-certificate"></i> Certificado incluso</li>' +
          '<li><i class="ti ti-player-play"></i> Acesso ao conteúdo</li>' +
          '<li><i class="ti ti-school"></i> Ambiente de aprendizagem</li>' +
          '<li><i class="ti ti-headset"></i> Suporte especializado</li>' +
        "</ul>" +
        '<div class="v2-secure"><i class="ti ti-lock"></i> Compra 100% segura (demonstração)</div>' +
      "</div>" +
      "</aside>";

    /* ---- Coluna principal ---- */
    var mainCol = '<div class="v2-course-main">' +
      cover +
      '<span class="v2-card-cat">' + esc(curso.categoria) + "</span>" +
      '<h1 class="v2-h1" style="margin:6px 0;">' + esc(curso.titulo) + "</h1>" +
      (curso.resumo ? '<p class="v2-curso-resumo v2-muted">' + esc(curso.resumo) + "</p>" : "") +
      rate + meta +
      aprender + sobre + conteudo + turmasBlock + instrutor + faqBlock +
      "</div>";

    root.classList.add("v2-course");
    root.innerHTML = mainCol + aside;

    /* ---- CTA fixa mobile ---- */
    if (ctaFixed) {
      ctaFixed.innerHTML =
        '<div><div class="v2-price" id="v2-curso-price-m">' + BRL(turmas[sel].preco) + "</div>" +
        '<div class="v2-muted v2-sm" style="margin-top:-2px;">12x no cartão (demo)</div></div>' +
        '<a href="#" class="v2-btn v2-btn-primary" id="v2-curso-cta-m">Quero desbloquear</a>';
      ctaFixed.style.display = "";
    }

    /* ---- Estado dinâmico: turma selecionada → preço, CTA e checkout ---- */
    function applyTurma() {
      var t = turmas[sel] || { preco: curso.preco, nome: "", id: "" };
      var p = BRL(t.preco);
      var pe = $("#v2-curso-price"); if (pe) pe.textContent = p;
      var pm = $("#v2-curso-price-m"); if (pm) pm.textContent = p;
      var tn = $("#v2-curso-turma-nome"); if (tn) tn.textContent = t.nome;
      var href = "/v2/checkout/?curso=" + encodeURIComponent(curso.id) + "&turma=" + encodeURIComponent(t.id || "");
      var c1 = $("#v2-curso-cta"); if (c1) c1.setAttribute("href", href);
      var c2 = $("#v2-curso-cta-m"); if (c2) c2.setAttribute("href", href);
      $$(".v2-turma-sel", root).forEach(function (el) {
        var i = parseInt(el.getAttribute("data-turma"), 10);
        el.classList.toggle("is-sel", i === sel);
        var r = el.querySelector("input"); if (r) r.checked = (i === sel);
      });
    }

    /* ---- Listener único (delegação): turmas + accordions ---- */
    root.addEventListener("click", function (e) {
      var lab = e.target.closest(".v2-turma-sel");
      if (lab && root.contains(lab)) {
        var idx = parseInt(lab.getAttribute("data-turma"), 10);
        if (!isNaN(idx)) { sel = idx; applyTurma(); }
        return;
      }
      var head = e.target.closest(".v2-acc-head");
      if (head && root.contains(head)) {
        var acc = head.closest(".v2-acc");
        var open = acc.classList.toggle("is-open");
        head.setAttribute("aria-expanded", open ? "true" : "false");
      }
    });

    applyTurma();
  }

  /* ---------------- FICHA DE CURSO V2 INTEGRADA (server-side) -- */
  /* A ficha /v2/curso/?curso_id=ID é renderizada pelo backend com dados reais.
     Aqui só melhoramos a UX: accordions visuais do conteúdo programático,
     seleção de turma sem recarregar a página e atualização segura do link de
     CTA (rota oficial /inscricao). Sem rede, sem dados demonstrativos.
     A seleção de turma também funciona sem JS, via link/GET (?turma_id=). */
  function initCursoIntegradoV2() {
    if (!window.V2_CURSO_INTEGRADO) return;
    var root = $("#v2-curso-integrado");
    if (!root) return;

    function setText(id, value) { var el = $("#" + id); if (el) el.textContent = value; }
    function setHref(id, value) { var el = $("#" + id); if (el && value) el.setAttribute("href", value); }

    root.addEventListener("click", function (e) {
      // Accordion do conteúdo programático (apenas visual).
      var head = e.target.closest(".v2-acc-head");
      if (head && root.contains(head)) {
        var acc = head.closest(".v2-acc");
        if (acc) {
          var open = acc.classList.toggle("is-open");
          head.setAttribute("aria-expanded", open ? "true" : "false");
        }
        return;
      }

      // Seleção de turma sem recarregar (enhancement do link/GET).
      var turma = e.target.closest(".v2-turma-sel");
      if (turma && root.contains(turma)) {
        e.preventDefault();
        $$(".v2-turma-sel", root).forEach(function (el) { el.classList.remove("is-sel"); });
        turma.classList.add("is-sel");
        var nome = turma.getAttribute("data-turma-nome") || "";
        var ctaHref = turma.getAttribute("data-cta-href") || "";
        setText("v2-curso-turma-nome", nome);
        setText("v2-curso-turma-nome-m", nome);
        setHref("v2-curso-cta", ctaHref);
        setHref("v2-curso-cta-m", ctaHref);
      }
    });
  }

  /* ============================================================
     CHECKOUT V2 (/v2/checkout/?curso=…&turma=…)
     4 etapas navegáveis, 100% client-side. Sem rede/backend.
     ============================================================ */
  var CO_KEY = "v2_checkout_demo_state";
  var CO_PIX = "00020126580014BR.GOV.BCB.PIX0136desbloqueia-cursos-demo-pix-5204";

  function initCheckoutV2() {
    var root = $("#v2-checkout-v2");
    if (!root) return;
    var emptyEl = $("#v2-co-empty");

    var params = new URLSearchParams(location.search);
    var cursoId = params.get("curso");
    var turmaParam = params.get("turma");

    var curso = null;
    for (var i = 0; i < CURSOS.length; i++) { if (CURSOS[i].id === cursoId) { curso = CURSOS[i]; break; } }

    // Curso inexistente → estado vazio
    if (!curso) {
      if (emptyEl) emptyEl.style.display = "block";
      root.style.display = "none";
      document.title = "Curso não encontrado — Checkout";
      return;
    }
    root.style.display = "";
    document.title = "Checkout — " + curso.titulo;

    var backLink = $("#v2-co-back");
    if (backLink) backLink.setAttribute("href", "/v2/curso/?id=" + encodeURIComponent(curso.id));

    var turmas = (curso.turmas && curso.turmas.length) ? curso.turmas
      : [{ id: "", nome: "Turma única", modalidade: curso.modalidade, inicio: "Acesso imediato", vagas: null, local: null, preco: curso.preco, formato: "" }];

    // Localiza a turma pelo parâmetro; se não existir, usa a primeira
    var turmaIndex = 0, achouTurma = false;
    for (var j = 0; j < turmas.length; j++) { if (turmas[j].id === turmaParam) { turmaIndex = j; achouTurma = true; break; } }

    // Estado persistido (apenas etapa/curso/turma/tipo/qtd/cupom)
    var persisted = null;
    try { persisted = JSON.parse(localStorage.getItem(CO_KEY)); } catch (e) { persisted = null; }

    var state = { step: 1, tipo: "mim", turmaIndex: turmaIndex, cupom: "" };
    var loteQtd = 2;
    if (persisted && persisted.curso === curso.id) {
      if (["mim", "outra", "lote"].indexOf(persisted.tipo) !== -1) state.tipo = persisted.tipo;
      if (persisted.cupom === "DESBLOQUEIA10") state.cupom = "DESBLOQUEIA10";
      if (typeof persisted.step === "number") state.step = Math.min(4, Math.max(1, persisted.step));
      if (!achouTurma && persisted.turma) {
        for (var p = 0; p < turmas.length; p++) { if (turmas[p].id === persisted.turma) { state.turmaIndex = p; achouTurma = true; break; } }
      }
      if (typeof persisted.qtd === "number" && persisted.qtd >= 1) loteQtd = Math.min(20, persisted.qtd);
    }

    // Dados em memória (NÃO persistidos): CPF/e-mail nunca vão ao localStorage
    var formData = {
      mim: { nome: "Ana Souza", email: "ana.souza@exemplo.com", cpf: "123.456.789-09" },
      outra: { nome: "", email: "", cpf: "" }
    };
    var participantes = [];
    for (var q = 0; q < loteQtd; q++) participantes.push({ nome: "", email: "", cpf: "" });

    /* ---- URL canônica sempre com a turma efetiva ---- */
    function syncURL() {
      try {
        history.replaceState(null, "", "/v2/checkout/?curso=" + encodeURIComponent(curso.id) + "&turma=" + encodeURIComponent(turmas[state.turmaIndex].id || ""));
      } catch (e) {}
    }
    syncURL();

    function persistCO() {
      try {
        localStorage.setItem(CO_KEY, JSON.stringify({
          step: state.step, curso: curso.id, turma: turmas[state.turmaIndex].id || "",
          tipo: state.tipo, qtd: qtd(), cupom: state.cupom
        }));
      } catch (e) {}
    }

    /* ---- Cálculo de valores ---- */
    function unit() { return turmas[state.turmaIndex].preco; }
    function qtd() { return state.tipo === "lote" ? Math.max(1, participantes.length) : 1; }
    function subtotal() { return unit() * qtd(); }
    function desconto() { return state.cupom === "DESBLOQUEIA10" ? subtotal() * 0.10 : 0; }
    function total() { return subtotal() - desconto(); }

    var cs = catStyle(curso.categoria);

    /* ---- Resumo (aside) ---- */
    function renderSummary() {
      var t = turmas[state.turmaIndex];
      var totEl = $("#v2-co-sum-total"); if (totEl) totEl.textContent = BRL(total());
      var body = $("#v2-co-sum-body"); if (!body) return;
      body.innerHTML =
        '<div class="v2-co-mini">' +
          '<span class="v2-co-mini-thumb" style="background:linear-gradient(135deg,' + cs.g1 + ',' + cs.g2 + ');"><i class="ti ' + cs.icon + '" style="color:' + cs.cor + ';"></i></span>' +
          '<span class="v2-co-mini-body"><span class="v2-card-cat">' + esc(curso.categoria) + "</span><b>" + esc(curso.titulo) + "</b><span class=\"v2-muted v2-sm\">" + esc(t.nome) + "</span></span>" +
        "</div>" +
        '<div class="v2-resumo-row"><span>Participantes</span><span>' + qtd() + "</span></div>" +
        '<div class="v2-resumo-row"><span>Valor unitário</span><span>' + BRL(unit()) + "</span></div>" +
        '<div class="v2-resumo-row"><span>Subtotal</span><span>' + BRL(subtotal()) + "</span></div>" +
        (desconto() > 0 ? '<div class="v2-resumo-row"><span>Desconto (DESBLOQUEIA10)</span><span>− ' + BRL(desconto()) + "</span></div>" : "") +
        '<div class="v2-resumo-row v2-resumo-total"><span>Total</span><span>' + BRL(total()) + "</span></div>" +
        '<div class="v2-callout v2-callout-info" style="margin:14px 0 0;"><i class="ti ti-info-circle"></i><span>Ambiente demonstrativo: nenhum pedido ou pagamento será enviado ao sistema real.</span></div>';
    }

    /* ---- Navegação inferior dos painéis ---- */
    function navHTML(step) {
      var back = step > 1 ? '<button type="button" class="v2-btn v2-btn-ghost" data-co-back><i class="ti ti-arrow-left"></i> Voltar</button>' : "";
      if (step < 4) return '<div class="v2-checkout-nav">' + back + '<button type="button" class="v2-btn v2-btn-primary" data-co-next>Continuar <i class="ti ti-arrow-right"></i></button></div>';
      return '<div class="v2-checkout-nav">' + back + '<button type="button" class="v2-btn v2-btn-primary" id="v2-co-final" disabled>Enviar comprovante</button></div>';
    }

    function fieldHTML(group, key, label, type, value, placeholder, autoc) {
      return '<div class="v2-field" data-fw="' + group + "-" + key + '">' +
        '<label for="co-' + group + "-" + key + '">' + esc(label) + "</label>" +
        '<input id="co-' + group + "-" + key + '" class="v2-input" type="' + type + '" data-group="' + group + '" data-k="' + key + '"' +
          (key === "cpf" ? ' data-mask="cpf" inputmode="numeric"' : "") +
          (autoc ? ' autocomplete="' + autoc + '"' : "") +
          ' value="' + esc(value || "") + '" placeholder="' + esc(placeholder || "") + '">' +
        '<span class="v2-field-error"></span>' +
        "</div>";
    }

    /* ---- ETAPA 1 — Inscrição ---- */
    function buildP1() {
      var panel = $("#v2-co-p1"); if (!panel) return;
      var t = turmas[state.turmaIndex];
      var resumoCurso = '<div class="v2-scard"><div class="v2-scard-title">Curso selecionado</div>' +
        '<div class="v2-co-mini">' +
          '<span class="v2-co-mini-thumb" style="background:linear-gradient(135deg,' + cs.g1 + ',' + cs.g2 + ');"><i class="ti ' + cs.icon + '" style="color:' + cs.cor + ';"></i></span>' +
          '<span class="v2-co-mini-body"><span class="v2-card-cat">' + esc(curso.categoria) + "</span><b>" + esc(curso.titulo) + "</b></span>" +
        "</div>" +
        '<div class="v2-course-meta" style="margin:6px 0 0;">' +
          '<span><i class="ti ti-device-desktop"></i> ' + esc(curso.modalidade) + "</span>" +
          '<span><i class="ti ti-clock"></i> ' + curso.cargaHoraria + " horas</span>" +
          (curso.nivel ? '<span><i class="ti ti-stairs-up"></i> ' + esc(curso.nivel) + "</span>" : "") +
        "</div></div>";

      var turmasCards = turmas.map(function (tu, idx) {
        var vagas = (tu.vagas === null || tu.vagas === undefined) ? "Vagas abertas" : (tu.vagas + " vagas");
        return '<label class="v2-radio-card v2-co-turma' + (idx === state.turmaIndex ? " is-sel" : "") + '" data-co-turma="' + idx + '">' +
          '<input type="radio" name="v2-co-turma" value="' + idx + '"' + (idx === state.turmaIndex ? " checked" : "") + ' aria-label="Selecionar ' + esc(tu.nome) + '">' +
          '<span class="v2-co-turma-body">' +
            "<b>" + esc(tu.nome) + "</b>" +
            '<span class="v2-turma-meta">' +
              '<span><i class="ti ti-calendar"></i> ' + esc(tu.inicio) + "</span>" +
              '<span><i class="ti ti-' + (tu.modalidade === "Presencial" ? "map-pin" : "device-desktop") + '"></i> ' + esc(tu.modalidade) + "</span>" +
              '<span><i class="ti ti-users"></i> ' + vagas + "</span>" +
              (tu.local ? '<span><i class="ti ti-map-pin"></i> ' + esc(tu.local) + "</span>" : "") +
            "</span>" +
            '<span class="v2-turma-preco">' + BRL(tu.preco) + "</span>" +
          "</span></label>";
      }).join("");

      var tipos = [["mim", "ti-user", "Para mim"], ["outra", "ti-user-plus", "Para outra pessoa"], ["lote", "ti-users-group", "Compra em lote"]];
      var seg = tipos.map(function (x) {
        return '<button type="button" data-co-tipo="' + x[0] + '" class="' + (state.tipo === x[0] ? "is-on" : "") + '"><i class="ti ' + x[1] + '"></i> ' + x[2] + "</button>";
      }).join("");

      panel.innerHTML = resumoCurso +
        '<div class="v2-scard"><div class="v2-scard-title">Selecione a turma</div><div class="v2-co-turmas">' + turmasCards + "</div></div>" +
        '<div class="v2-scard"><div class="v2-scard-title">Esta inscrição é</div><div class="v2-seg" role="group" aria-label="Tipo de inscrição">' + seg + "</div></div>" +
        navHTML(1);
    }

    /* ---- ETAPA 2 — Participantes ---- */
    function buildP2() {
      var panel = $("#v2-co-p2"); if (!panel) return;
      var inner = "";
      if (state.tipo === "mim") {
        inner = '<div class="v2-scard"><div class="v2-scard-title">Seus dados</div>' +
          '<p class="v2-muted v2-sm" style="margin-bottom:12px;">Dados de demonstração — você pode editar à vontade (nada é salvo de verdade).</p>' +
          fieldHTML("mim", "nome", "Nome completo", "text", formData.mim.nome, "", "name") +
          fieldHTML("mim", "email", "E-mail", "email", formData.mim.email, "", "email") +
          fieldHTML("mim", "cpf", "CPF", "text", formData.mim.cpf, "000.000.000-00", "off") +
          "</div>";
      } else if (state.tipo === "outra") {
        inner = '<div class="v2-scard"><div class="v2-scard-title">Dados da pessoa</div>' +
          '<p class="v2-muted v2-sm" style="margin-bottom:12px;">Informe os dados de quem vai fazer o curso.</p>' +
          fieldHTML("outra", "nome", "Nome completo", "text", formData.outra.nome, "Nome de quem vai estudar", "off") +
          fieldHTML("outra", "email", "E-mail", "email", formData.outra.email, "email@exemplo.com", "off") +
          fieldHTML("outra", "cpf", "CPF", "text", formData.outra.cpf, "000.000.000-00", "off") +
          "</div>";
      } else {
        var cards = participantes.map(function (pt, idx) {
          var canRemove = participantes.length > 1;
          return '<div class="v2-scard v2-part" data-part="' + idx + '">' +
            '<div class="v2-part-head"><b>Participante ' + (idx + 1) + "</b>" +
              (canRemove ? '<button type="button" class="v2-iconbtn" data-remove="' + idx + '" aria-label="Remover participante ' + (idx + 1) + '"><i class="ti ti-trash"></i></button>' : "") +
            "</div>" +
            '<div class="v2-field" data-fw="lote-nome-' + idx + '"><label for="co-lote-nome-' + idx + '">Nome completo</label>' +
              '<input id="co-lote-nome-' + idx + '" class="v2-input" type="text" data-lote="' + idx + '" data-k="nome" value="' + esc(pt.nome) + '"><span class="v2-field-error"></span></div>' +
            '<div class="v2-field" data-fw="lote-email-' + idx + '"><label for="co-lote-email-' + idx + '">E-mail</label>' +
              '<input id="co-lote-email-' + idx + '" class="v2-input" type="email" data-lote="' + idx + '" data-k="email" value="' + esc(pt.email) + '"><span class="v2-field-error"></span></div>' +
            '<div class="v2-field" data-fw="lote-cpf-' + idx + '"><label for="co-lote-cpf-' + idx + '">CPF</label>' +
              '<input id="co-lote-cpf-' + idx + '" class="v2-input" type="text" inputmode="numeric" data-lote="' + idx + '" data-k="cpf" data-mask="cpf" value="' + esc(pt.cpf) + '" placeholder="000.000.000-00"><span class="v2-field-error"></span></div>' +
            "</div>";
        }).join("");
        inner = '<div class="v2-scard"><div class="v2-scard-title">Participantes</div>' +
          '<p class="v2-co-counter" id="v2-co-counter">' + participantes.length + (participantes.length === 1 ? " participante" : " participantes") + " no pedido</p>" +
          cards +
          '<button type="button" class="v2-btn v2-btn-ghost v2-btn-block" data-add><i class="ti ti-plus"></i> Adicionar participante</button>' +
          "</div>";
      }
      panel.innerHTML =
        '<div class="v2-callout v2-callout-danger" id="v2-co-p2-err" role="alert" style="display:none;"><i class="ti ti-alert-circle"></i><span>Revise os campos destacados antes de continuar.</span></div>' +
        inner + navHTML(2);
    }

    /* ---- ETAPA 3 — Resumo ---- */
    function buildP3() {
      var panel = $("#v2-co-p3"); if (!panel) return;
      var cupomCtrl = state.cupom
        ? '<input id="v2-co-cupom" class="v2-input" value="' + state.cupom + '" disabled aria-label="Cupom aplicado"><button type="button" class="v2-btn v2-btn-ghost" id="v2-co-cupom-remove">Remover</button>'
        : '<input id="v2-co-cupom" class="v2-input" placeholder="Digite seu cupom" aria-label="Cupom de desconto"><button type="button" class="v2-btn v2-btn-outline" id="v2-co-cupom-apply">Aplicar cupom</button>';
      var msg = state.cupom
        ? '<div id="v2-co-cupom-msg" class="v2-callout v2-callout-success" role="status" aria-live="polite" style="margin-top:10px;"><i class="ti ti-circle-check"></i><span>Cupom aplicado: 10% de desconto.</span></div>'
        : '<div id="v2-co-cupom-msg" class="v2-callout" role="status" aria-live="polite" style="display:none;margin-top:10px;"></div>';
      panel.innerHTML =
        '<div class="v2-scard"><div class="v2-scard-title">Resumo do pedido</div>' +
          '<div class="v2-resumo-row"><span>Curso</span><span>' + esc(curso.titulo) + "</span></div>" +
          '<div class="v2-resumo-row"><span>Turma</span><span>' + esc(turmas[state.turmaIndex].nome) + "</span></div>" +
          '<div class="v2-resumo-row"><span>Participantes</span><span>' + qtd() + "</span></div>" +
          '<div class="v2-resumo-row"><span>Valor unitário</span><span>' + BRL(unit()) + "</span></div>" +
          '<div class="v2-resumo-row"><span>Subtotal</span><span id="v2-co-p3-sub">' + BRL(subtotal()) + "</span></div>" +
          '<div class="v2-resumo-row" id="v2-co-p3-desc-row"' + (desconto() > 0 ? "" : ' style="display:none;"') + '><span>Desconto</span><span id="v2-co-p3-desc">− ' + BRL(desconto()) + "</span></div>" +
          '<div class="v2-resumo-row v2-resumo-total"><span>Total</span><span id="v2-co-p3-total">' + BRL(total()) + "</span></div>" +
        "</div>" +
        '<div class="v2-scard"><div class="v2-scard-title">Cupom de desconto</div>' +
          '<div class="v2-cupom-row">' + cupomCtrl + "</div>" + msg +
        "</div>" +
        '<div class="v2-callout v2-callout-info"><i class="ti ti-info-circle"></i><span>Ambiente demonstrativo: nenhum pedido ou pagamento será enviado ao sistema real.</span></div>' +
        navHTML(3);
    }

    /* ---- ETAPA 4 — Pagamento ---- */
    function buildP4() {
      var panel = $("#v2-co-p4"); if (!panel) return;
      panel.innerHTML =
        '<div class="v2-scard"><div class="v2-scard-title">Pagamento via PIX</div>' +
          '<div class="v2-pix">' +
            '<div class="v2-muted v2-sm">Valor a pagar</div>' +
            '<div class="v2-pix-valor" id="v2-co-pix-valor">' + BRL(total()) + "</div>" +
            '<div class="v2-qr" role="img" aria-label="QR Code PIX demonstrativo"></div>' +
            '<div class="v2-pix-key"><code id="v2-co-pix-code">' + CO_PIX + '</code>' +
              '<button type="button" class="v2-iconbtn" id="v2-co-pix-copy" aria-label="Copiar código PIX"><i class="ti ti-copy"></i></button></div>' +
            '<div class="v2-callout v2-callout-warning" style="text-align:left;"><i class="ti ti-alert-triangle"></i><span><b>PIX demonstrativo — não efetua pagamento real.</b></span></div>' +
          "</div>" +
          '<div class="v2-scard-title" style="margin-top:6px;">Comprovante</div>' +
          '<p class="v2-muted v2-sm" style="margin-bottom:10px;">Anexe um comprovante (PNG, JPG ou PDF). A pré-visualização é apenas local — nada é enviado.</p>' +
          '<div class="v2-upload" id="v2-co-upload" role="button" tabindex="0" aria-label="Selecionar comprovante">' +
            '<i class="ti ti-cloud-upload"></i><b>Toque para selecionar</b><span class="v2-muted v2-sm">PNG, JPG ou PDF</span>' +
          "</div>" +
          '<input type="file" id="v2-co-upload-input" accept=".png,.jpg,.jpeg,.pdf,image/png,image/jpeg,application/pdf" class="v2-sr-only" tabindex="-1" aria-hidden="true">' +
          '<div class="v2-upload-prev" id="v2-co-upload-prev"><span class="v2-upload-ic"><i class="ti ti-file-check"></i></span>' +
            '<span class="v2-upload-info"><b id="v2-co-upload-name"></b><span class="v2-muted v2-sm" id="v2-co-upload-size"></span></span>' +
            '<button type="button" class="v2-iconbtn" id="v2-co-upload-remove" aria-label="Remover arquivo"><i class="ti ti-x"></i></button></div>' +
        "</div>" +
        navHTML(4);
    }

    /* ---- ETAPA final — Confirmação ---- */
    function finalize() {
      var done = $("#v2-co-done"); if (!done) return;
      var t = turmas[state.turmaIndex];
      var partList = "";
      if (state.tipo === "lote") {
        partList = '<ul class="v2-cta-list" style="margin-top:12px;">' + participantes.map(function (pt, idx) {
          return '<li><i class="ti ti-user"></i> ' + (esc(pt.nome) || ("Participante " + (idx + 1))) + "</li>";
        }).join("") + "</ul>";
      } else {
        var nome = state.tipo === "outra" ? (esc(formData.outra.nome) || "Outra pessoa") : esc(formData.mim.nome);
        partList = '<div class="v2-resumo-row"><span>Para</span><span>' + nome + "</span></div>";
      }
      done.innerHTML =
        '<div class="v2-scard v2-center">' +
          '<i class="ti ti-circle-check-filled" style="font-size:54px;color:var(--v2-success);"></i>' +
          '<h2 class="v2-h2" style="margin:10px 0 6px;">Pedido de demonstração criado!</h2>' +
          '<p class="v2-muted" style="margin:0 auto 16px;max-width:46ch;">Esta é uma simulação da nova experiência de checkout. Nenhum pagamento foi processado.</p>' +
          '<div class="v2-block" style="text-align:left;margin:0 0 16px;">' +
            '<div class="v2-resumo-row"><span>Curso</span><span>' + esc(curso.titulo) + "</span></div>" +
            '<div class="v2-resumo-row"><span>Turma</span><span>' + esc(t.nome) + "</span></div>" +
            '<div class="v2-resumo-row"><span>Participantes</span><span>' + qtd() + "</span></div>" +
            '<div class="v2-resumo-row v2-resumo-total"><span>Total</span><span>' + BRL(total()) + "</span></div>" +
            partList +
          "</div>" +
          '<div class="v2-checkout-nav" style="justify-content:center;">' +
            '<a href="/v2/aluno/" class="v2-btn v2-btn-primary">Ir para minha área</a>' +
            '<a href="/v2/" class="v2-btn v2-btn-ghost">Voltar ao início</a>' +
          "</div>" +
        "</div>";
      var st = $("#v2-co-stepper"); if (st) st.style.display = "none";
      var si = $("#v2-co-stepinfo"); if (si) si.style.display = "none";
      var as = $("#v2-co-aside"); if (as) as.style.display = "none";
      $$(".v2-panel", root).forEach(function (p) { p.classList.remove("is-active"); });
      done.classList.add("is-active");
      try { localStorage.removeItem(CO_KEY); } catch (e) {}
      window.scrollTo({ top: 0, behavior: "smooth" });
    }

    /* ---- Stepper ---- */
    function paintStepper() {
      $$(".v2-step", root).forEach(function (s) {
        var n = parseInt(s.getAttribute("data-step"), 10);
        s.classList.toggle("is-done", n < state.step);
        s.classList.toggle("is-active", n === state.step);
        if (n === state.step) s.setAttribute("aria-current", "step"); else s.removeAttribute("aria-current");
        var dot = s.querySelector(".v2-step-dot");
        if (dot) dot.innerHTML = (n < state.step) ? '<i class="ti ti-check"></i>' : String(n);
      });
      $$(".v2-step-line", root).forEach(function (l, idx) { l.classList.toggle("is-done", (idx + 1) < state.step); });
      var labels = ["Inscrição", "Participantes", "Resumo", "Pagamento"];
      var info = $("#v2-co-stepinfo"); if (info) info.textContent = "Etapa " + state.step + " de 4 — " + labels[state.step - 1];
    }

    function goStep(n) {
      n = Math.min(4, Math.max(1, n));
      state.step = n;
      if (n === 2) buildP2();
      if (n === 3) buildP3();
      if (n === 4) buildP4();
      paintStepper();
      $$(".v2-panel", root).forEach(function (p) { p.classList.remove("is-active"); });
      var pane = $('[data-panel="' + n + '"]', root); if (pane) pane.classList.add("is-active");
      renderSummary();
      persistCO();
      window.scrollTo({ top: 0, behavior: "smooth" });
    }

    /* ---- Validação da etapa 2 ---- */
    function markField(sel, msg) {
      var fw = $('[data-fw="' + sel + '"]', root);
      if (fw) { fw.classList.add("has-error"); var e = $(".v2-field-error", fw); if (e) e.textContent = msg; }
    }
    function clearFieldWraps() { $$(".v2-field.has-error", root).forEach(function (f) { f.classList.remove("has-error"); }); }
    function checkPessoa(prefix, obj) {
      var ok = true;
      if (!obj.nome || obj.nome.trim().length < 3) { markField(prefix + "-nome", "Informe o nome completo."); ok = false; }
      if (!validEmail((obj.email || "").trim())) { markField(prefix + "-email", "E-mail inválido."); ok = false; }
      if (!validCPF(obj.cpf || "")) { markField(prefix + "-cpf", "CPF inválido."); ok = false; }
      return ok;
    }
    function validateP2() {
      clearFieldWraps();
      var ok = true;
      if (state.tipo === "mim") ok = checkPessoa("mim", formData.mim);
      else if (state.tipo === "outra") ok = checkPessoa("outra", formData.outra);
      else {
        for (var k = 0; k < participantes.length; k++) {
          var pt = participantes[k];
          if (!pt.nome || pt.nome.trim().length < 3) { markField("lote-nome-" + k, "Informe o nome completo."); ok = false; }
          if (!validEmail((pt.email || "").trim())) { markField("lote-email-" + k, "E-mail inválido."); ok = false; }
          if (!validCPF(pt.cpf || "")) { markField("lote-cpf-" + k, "CPF inválido."); ok = false; }
        }
      }
      var err = $("#v2-co-p2-err"); if (err) err.style.display = ok ? "none" : "flex";
      return ok;
    }

    /* ---- Cupom ---- */
    function applyCupom() {
      var inp = $("#v2-co-cupom");
      var code = ((inp && inp.value) || "").trim().toUpperCase();
      var msg = $("#v2-co-cupom-msg");
      if (code === "DESBLOQUEIA10") {
        state.cupom = "DESBLOQUEIA10";
        persistCO(); buildP3(); renderSummary();
      } else if (msg) {
        msg.className = "v2-callout v2-callout-danger";
        msg.style.display = "flex";
        msg.innerHTML = '<i class="ti ti-alert-circle"></i><span>Cupom inválido ou expirado. Tente DESBLOQUEIA10.</span>';
      }
    }
    function removeCupom() { state.cupom = ""; persistCO(); buildP3(); renderSummary(); }

    /* ---- Upload visual ---- */
    function showFile(file) {
      var up = $("#v2-co-upload"), prev = $("#v2-co-upload-prev"), fin = $("#v2-co-final");
      if (up) up.style.display = "none";
      if (prev) prev.classList.add("is-on");
      var nm = $("#v2-co-upload-name"); if (nm) nm.textContent = file.name;
      var sz = $("#v2-co-upload-size"); if (sz) sz.textContent = (file.size / 1024).toFixed(0) + " KB";
      if (fin) fin.disabled = false;
    }
    function resetFile() {
      var up = $("#v2-co-upload"), prev = $("#v2-co-upload-prev"), fin = $("#v2-co-final"), inp = $("#v2-co-upload-input");
      if (inp) inp.value = "";
      if (up) up.style.display = "flex";
      if (prev) prev.classList.remove("is-on");
      if (fin) fin.disabled = true;
    }

    /* ---- Delegação de eventos (vinculada uma única vez) ---- */
    root.addEventListener("click", function (e) {
      var turmaCard = e.target.closest("[data-co-turma]");
      if (turmaCard) {
        state.turmaIndex = parseInt(turmaCard.getAttribute("data-co-turma"), 10) || 0;
        $$(".v2-co-turma", root).forEach(function (el) {
          var i = parseInt(el.getAttribute("data-co-turma"), 10);
          el.classList.toggle("is-sel", i === state.turmaIndex);
          var r = el.querySelector("input"); if (r) r.checked = (i === state.turmaIndex);
        });
        syncURL(); persistCO(); renderSummary();
        return;
      }
      var tipoBtn = e.target.closest("[data-co-tipo]");
      if (tipoBtn) {
        state.tipo = tipoBtn.getAttribute("data-co-tipo");
        $$("[data-co-tipo]", root).forEach(function (b) { b.classList.toggle("is-on", b === tipoBtn); });
        buildP2(); renderSummary(); persistCO();
        return;
      }
      if (e.target.closest("[data-co-next]")) {
        if (state.step === 2 && !validateP2()) return;
        goStep(state.step + 1);
        return;
      }
      if (e.target.closest("[data-co-back]")) { goStep(state.step - 1); return; }
      var addBtn = e.target.closest("[data-add]");
      if (addBtn) { readLote(); if (participantes.length < 20) participantes.push({ nome: "", email: "", cpf: "" }); buildP2(); renderSummary(); persistCO(); return; }
      var rmBtn = e.target.closest("[data-remove]");
      if (rmBtn) { readLote(); var ri = parseInt(rmBtn.getAttribute("data-remove"), 10); if (participantes.length > 1) participantes.splice(ri, 1); buildP2(); renderSummary(); persistCO(); return; }
      if (e.target.closest("#v2-co-cupom-apply")) { applyCupom(); return; }
      if (e.target.closest("#v2-co-cupom-remove")) { removeCupom(); return; }
      if (e.target.closest("#v2-co-upload")) { var fi = $("#v2-co-upload-input"); if (fi) fi.click(); return; }
      if (e.target.closest("#v2-co-upload-remove")) { resetFile(); return; }
      if (e.target.closest("#v2-co-pix-copy")) {
        var code = $("#v2-co-pix-code"); var txt = code ? code.textContent : CO_PIX;
        function done() { toast("Código PIX copiado!"); }
        if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(txt).then(done).catch(done);
        else done();
        return;
      }
      if (e.target.closest("#v2-co-final")) { finalize(); return; }
    });

    function readLote() {
      $$('[data-lote]', root).forEach(function (inp) {
        var idx = parseInt(inp.getAttribute("data-lote"), 10);
        var k = inp.getAttribute("data-k");
        if (participantes[idx]) participantes[idx][k] = inp.value;
      });
    }

    root.addEventListener("input", function (e) {
      var inp = e.target;
      if (inp.getAttribute && inp.getAttribute("data-mask") === "cpf") inp.value = maskCPF(inp.value);
      if (inp.hasAttribute && inp.hasAttribute("data-lote")) {
        var idx = parseInt(inp.getAttribute("data-lote"), 10);
        var k = inp.getAttribute("data-k");
        if (participantes[idx]) participantes[idx][k] = inp.value;
      } else if (inp.hasAttribute && inp.hasAttribute("data-group")) {
        var g = inp.getAttribute("data-group"); var key = inp.getAttribute("data-k");
        if (formData[g]) formData[g][key] = inp.value;
      }
    });

    root.addEventListener("change", function (e) {
      if (e.target.id === "v2-co-upload-input") {
        var f = e.target.files && e.target.files[0];
        if (f) showFile(f);
      }
    });

    // Teclado no "dropzone" de upload
    root.addEventListener("keydown", function (e) {
      if (e.target.id === "v2-co-upload" && (e.key === "Enter" || e.key === " ")) {
        e.preventDefault(); var fi = $("#v2-co-upload-input"); if (fi) fi.click();
      }
    });

    /* ---- Stepper: voltar para etapas anteriores ---- */
    var stepper = $("#v2-co-stepper");
    if (stepper) stepper.addEventListener("click", function (e) {
      var b = e.target.closest(".v2-step"); if (!b) return;
      var n = parseInt(b.getAttribute("data-step"), 10);
      if (n < state.step) goStep(n);
    });

    /* ---- Resumo recolhível (mobile) + Escape ---- */
    var sumHead = $("#v2-co-sum-head"), summary = $("#v2-co-summary");
    if (sumHead && summary) {
      sumHead.addEventListener("click", function () {
        var open = summary.classList.toggle("is-open");
        sumHead.setAttribute("aria-expanded", open ? "true" : "false");
      });
      document.addEventListener("keydown", function (e) {
        if ((e.key === "Escape" || e.key === "Esc") && summary.classList.contains("is-open")) {
          summary.classList.remove("is-open");
          sumHead.setAttribute("aria-expanded", "false");
        }
      });
    }

    /* ---- Render inicial ---- */
    buildP1(); buildP2(); buildP3(); buildP4();
    renderSummary();
    goStep(state.step);
  }

  /* ============================================================
     ÁREA DO ALUNO V2 (/v2/aluno/?aba=…)
     Abas controladas por URL, dados demonstrativos locais,
     modais acessíveis. 100% client-side. Sem rede/backend.
     ============================================================ */
  var ALUNO_ABA_KEY = "v2_aluno_aba_ativa";
  var ALUNO_PREF_KEY = "v2_aluno_demo_preferencias";
  var ALUNO_ABAS = ["cursos", "pedidos", "certificados", "perfil"];

  function initAlunoV2() {
    var paneCursos = $("#v2-pane-cursos");
    if (!paneCursos) return;
    var ALUNO = window.V2_ALUNO;
    if (!ALUNO) return;

    function cursoById(id) { for (var i = 0; i < CURSOS.length; i++) if (CURSOS[i].id === id) return CURSOS[i]; return null; }

    var STATUS_CURSO = {
      "nao-iniciado": { label: "Não iniciado", badge: "v2-badge-neutro", acao: "Começar curso" },
      "andamento": { label: "Em andamento", badge: "v2-badge-destaque", acao: "Continuar curso" },
      "concluido": { label: "Concluído", badge: "v2-badge-gratis", acao: "Revisar curso" }
    };
    var STATUS_PEDIDO = {
      "pago": { label: "Pago", badge: "v2-badge-gratis" },
      "aguardando": { label: "Aguardando confirmação", badge: "v2-badge-destaque" },
      "cancelado": { label: "Cancelado", badge: "v2-badge-esgotado" }
    };
    var STATUS_CERT = {
      "disponivel": { label: "Disponível", badge: "v2-badge-gratis" },
      "analise": { label: "Em análise", badge: "v2-badge-destaque" },
      "bloqueado": { label: "Bloqueado por progresso", badge: "v2-badge-neutro" }
    };

    /* ---------- Cabeçalho: perfil + estatísticas ---------- */
    var perfil = ALUNO.perfil || {};
    var ola = $("#v2-aluno-ola"); if (ola) ola.textContent = "Olá, " + (perfil.nome || "Ana").split(" ")[0] + "! 👋";
    var av = $("#v2-aluno-avatar"); if (av) av.textContent = perfil.iniciais || "AS";
    var avBtn = $("#v2-avatar-btn"); if (avBtn) avBtn.textContent = perfil.iniciais || "AS";

    var mats = ALUNO.matriculas || [];
    var certs = ALUNO.certificados || [];
    var cursosAtivos = mats.filter(function (m) { return m.status === "andamento"; }).length;
    var certsDisp = certs.filter(function (c) { return c.status === "disponivel"; }).length;
    var progMedio = mats.length ? Math.round(mats.reduce(function (s, m) { return s + (m.progresso || 0); }, 0) / mats.length) : 0;
    var horas = mats.reduce(function (s, m) { var c = cursoById(m.cursoId); return s + (c ? Math.round(c.cargaHoraria * (m.progresso || 0) / 100) : 0); }, 0);
    var statsBox = $("#v2-aluno-stats");
    if (statsBox) {
      statsBox.innerHTML =
        '<div class="v2-stat"><div class="v2-stat-n">' + cursosAtivos + '</div><div class="v2-stat-l">Cursos ativos</div></div>' +
        '<div class="v2-stat"><div class="v2-stat-n">' + certsDisp + '</div><div class="v2-stat-l">Certificados</div></div>' +
        '<div class="v2-stat"><div class="v2-stat-n">' + progMedio + '%</div><div class="v2-stat-l">Progresso médio</div></div>' +
        '<div class="v2-stat"><div class="v2-stat-n">' + horas + 'h</div><div class="v2-stat-l">Horas estudadas</div></div>';
    }

    function progressoHTML(pct, legenda) {
      return '<span class="v2-progress"><span class="v2-progress-track"><span class="v2-progress-fill" style="width:' + pct + '%"></span></span>' +
        '<span class="v2-progress-row"><span class="v2-progress-pct">' + pct + '%</span><span class="v2-muted">' + esc(legenda || "") + "</span></span></span>";
    }

    /* ---------- ABA: Meus cursos ---------- */
    function renderCursos() {
      var ca = ALUNO.continueAprendendo;
      var cont = "";
      if (ca) {
        var cc = cursoById(ca.cursoId), ccs = cc ? catStyle(cc.categoria) : catStyle("");
        cont = '<div class="v2-continue">' +
          '<div class="v2-continue-label">Continue aprendendo</div>' +
          '<div class="v2-continue-row">' +
            '<span class="v2-list-thumb" style="background:linear-gradient(135deg,' + ccs.g1 + ',' + ccs.g2 + ');"><i class="ti ' + ccs.icon + '" style="color:' + ccs.cor + ';"></i></span>' +
            '<div style="flex:1;min-width:0;">' +
              "<b style=\"display:block;\">" + esc(cc ? cc.titulo : "") + "</b>" +
              progressoHTML(ca.progresso, "Última aula: " + ca.ultimaAula) +
            "</div>" +
          "</div>" +
          '<a href="' + esc(ca.href) + '" class="v2-btn v2-btn-primary v2-continue-cta"><i class="ti ti-player-play"></i> Continuar aula</a>' +
        "</div>";
      }

      var cards = mats.map(function (m) {
        var c = cursoById(m.cursoId); if (!c) return "";
        var st = STATUS_CURSO[m.status] || STATUS_CURSO["nao-iniciado"];
        var s = catStyle(c.categoria);
        var cert = null;
        for (var k = 0; k < certs.length; k++) if (certs[k].cursoId === c.id) { cert = certs[k]; break; }
        var selo = (m.status === "concluido") ? '<div class="v2-thumb-badges"><span class="v2-badge v2-badge-gratis"><i class="ti ti-circle-check"></i> Concluído</span></div>' : "";
        var actions = '<a class="v2-btn v2-btn-primary v2-btn-sm" href="/v2/aula/?curso=' + encodeURIComponent(c.id) + '">' + st.acao + "</a>";
        if (m.status === "concluido" && cert && cert.status === "disponivel") {
          actions += '<button type="button" class="v2-btn v2-btn-outline v2-btn-sm" data-cert="' + esc(c.id) + '"><i class="ti ti-certificate"></i> Ver certificado</button>';
        }
        return '<div class="v2-aluno-card">' +
          '<div class="v2-thumb" style="background:linear-gradient(135deg,' + s.g1 + ',' + s.g2 + ');">' + selo + '<i class="ti ' + s.icon + '" style="color:' + s.cor + ';"></i></div>' +
          '<div class="v2-card-body">' +
            '<span class="v2-card-cat">' + esc(c.categoria) + "</span>" +
            '<h3 class="v2-card-title">' + esc(c.titulo) + "</h3>" +
            '<div class="v2-card-meta"><span><i class="ti ti-clock"></i>' + c.cargaHoraria + "h</span><span><i class=\"ti ti-device-desktop\"></i>" + esc(c.modalidade) + "</span></div>" +
            '<div class="v2-aluno-card-status"><span class="v2-badge ' + st.badge + '">' + st.label + '</span><span class="v2-muted v2-sm">' + esc(m.ultimaAtividade || "") + "</span></div>" +
            progressoHTML(m.progresso, "") +
            '<div class="v2-aluno-card-actions">' + actions + "</div>" +
          "</div>" +
        "</div>";
      }).join("");

      paneCursos.innerHTML = cont +
        '<h2 class="v2-h3" style="margin:20px 0 12px;">Meus cursos</h2>' +
        '<div class="v2-aluno-cursos">' + cards + "</div>";
    }

    /* ---------- ABA: Pedidos ---------- */
    function renderPedidos() {
      var pane = $("#v2-pane-pedidos");
      var html = '<h2 class="v2-h3" style="margin:4px 0 12px;">Meus pedidos</h2>';
      html += (ALUNO.pedidos || []).map(function (p) {
        var c = cursoById(p.cursoId);
        var st = STATUS_PEDIDO[p.status] || STATUS_PEDIDO["aguardando"];
        var acoes = '<button type="button" class="v2-btn v2-btn-ghost v2-btn-sm" data-pedido="' + esc(p.codigo) + '"><i class="ti ti-eye"></i> Ver detalhes</button>';
        if (p.status === "pago") acoes += '<a class="v2-btn v2-btn-outline v2-btn-sm" href="/v2/aula/?curso=' + encodeURIComponent(p.cursoId) + '"><i class="ti ti-player-play"></i> Acessar curso</a>';
        else if (p.status === "aguardando") acoes += '<button type="button" class="v2-btn v2-btn-outline v2-btn-sm" data-comprovante="' + esc(p.codigo) + '"><i class="ti ti-upload"></i> Enviar comprovante</button>';
        return '<div class="v2-pedido">' +
          '<div class="v2-pedido-top"><div><span class="v2-pedido-cod">' + esc(p.codigo) + "</span><b>" + esc(c ? c.titulo : p.cursoId) + "</b></div>" +
            '<span class="v2-badge ' + st.badge + '">' + st.label + "</span></div>" +
          '<div class="v2-card-meta"><span><i class="ti ti-calendar"></i> ' + esc(p.data) + "</span>" +
            '<span><i class="ti ti-users"></i> ' + p.participantes + (p.participantes === 1 ? " participante" : " participantes") + "</span>" +
            '<span><i class="ti ti-currency-real"></i> ' + BRL(p.total) + "</span>" +
            '<span><i class="ti ti-' + (p.metodo === "PIX" ? "qrcode" : "credit-card") + '"></i> ' + esc(p.metodo) + "</span></div>" +
          '<div class="v2-aluno-card-actions">' + acoes + "</div>" +
        "</div>";
      }).join("");
      if (pane) pane.innerHTML = html;
    }

    /* ---------- ABA: Certificados ---------- */
    function renderCertificados() {
      var pane = $("#v2-pane-certificados");
      var html = '<h2 class="v2-h3" style="margin:4px 0 12px;">Meus certificados</h2>';
      html += (ALUNO.certificados || []).map(function (cert) {
        var c = cursoById(cert.cursoId);
        var st = STATUS_CERT[cert.status] || STATUS_CERT["bloqueado"];
        if (cert.status === "bloqueado") {
          return '<div class="v2-cert is-locked">' +
            '<span class="v2-cert-ic" style="background:var(--v2-surface-2);color:var(--v2-text-2);"><i class="ti ti-lock"></i></span>' +
            '<div style="flex:1;min-width:0;"><b>' + esc(c ? c.titulo : cert.cursoId) + "</b>" +
              progressoHTML(cert.progresso || 0, "Conclua o curso para liberar o certificado.") +
            "</div></div>";
        }
        var botoes = "";
        if (cert.status === "disponivel") {
          botoes = '<div class="v2-aluno-card-actions" style="margin-top:10px;">' +
            '<button type="button" class="v2-btn v2-btn-primary v2-btn-sm" data-cert="' + esc(cert.cursoId) + '"><i class="ti ti-eye"></i> Visualizar certificado</button>' +
            '<button type="button" class="v2-btn v2-btn-outline v2-btn-sm" data-cert="' + esc(cert.cursoId) + '"><i class="ti ti-download"></i> Baixar PDF</button>' +
          "</div>";
        }
        return '<div class="v2-cert v2-cert-block">' +
          '<div class="v2-cert-row">' +
            '<span class="v2-cert-ic"><i class="ti ti-certificate"></i></span>' +
            '<div style="flex:1;min-width:0;"><b>' + esc(c ? c.titulo : cert.cursoId) + "</b>" +
              '<div class="v2-muted v2-sm">' + cert.cargaHoraria + "h" + (cert.dataEmissao && cert.dataEmissao !== "—" ? " · Emitido em " + esc(cert.dataEmissao) : "") + (cert.codigo ? " · Cód. " + esc(cert.codigo) : "") + "</div>" +
              '<span class="v2-badge ' + st.badge + '" style="margin-top:6px;">' + st.label + "</span>" +
            "</div>" +
          "</div>" + botoes +
        "</div>";
      }).join("");
      if (pane) pane.innerHTML = html;
    }

    /* ---------- ABA: Perfil ---------- */
    function loadPrefs() { try { return JSON.parse(localStorage.getItem(ALUNO_PREF_KEY)); } catch (e) { return null; } }
    function savePrefs(p) { try { localStorage.setItem(ALUNO_PREF_KEY, JSON.stringify(p)); } catch (e) {} }
    var basePref = ALUNO.preferencias || { novidades: true, lembretes: true, temaClaro: true };
    var prefs = loadPrefs() || { novidades: !!basePref.novidades, lembretes: !!basePref.lembretes, temaClaro: !!basePref.temaClaro };

    function fld(id, label, value, type, mask) {
      return '<div class="v2-field"><label for="' + id + '">' + esc(label) + "</label>" +
        '<input id="' + id + '" class="v2-input" type="' + type + '" value="' + esc(value || "") + '"' + (mask ? ' data-mask="cpf" inputmode="numeric"' : "") + " autocomplete=\"off\"></div>";
    }
    function prefRow(key, label) {
      return '<label class="v2-pref"><span>' + esc(label) + "</span>" +
        '<input type="checkbox" class="v2-switch" data-pref="' + key + '"' + (prefs[key] ? " checked" : "") + "></label>";
    }
    function renderPerfil() {
      var pane = $("#v2-pane-perfil");
      var p = perfil;
      var html = '<h2 class="v2-h3" style="margin:4px 0 12px;">Meu perfil</h2>' +
        '<form class="v2-scard" id="v2-perfil-form" novalidate>' +
          '<div class="v2-perfil-grid">' +
            fld("pf-nome", "Nome completo", p.nome, "text") +
            fld("pf-email", "E-mail", p.email, "email") +
            fld("pf-cpf", "CPF", p.cpf, "text", true) +
            fld("pf-tel", "Telefone", p.telefone, "tel") +
            fld("pf-cidade", "Cidade", p.cidade, "text") +
            fld("pf-estado", "Estado", p.estado, "text") +
          "</div>" +
          '<div id="v2-perfil-msg" class="v2-callout v2-callout-success" role="status" aria-live="polite" style="display:none;"></div>' +
          '<div class="v2-aluno-card-actions" style="margin-top:4px;">' +
            '<button type="submit" class="v2-btn v2-btn-primary">Salvar alterações</button>' +
            '<button type="button" class="v2-btn v2-btn-ghost" id="v2-perfil-senha"><i class="ti ti-lock"></i> Alterar senha</button>' +
          "</div>" +
        "</form>" +
        '<div class="v2-scard"><div class="v2-scard-title">Preferências</div>' +
          prefRow("novidades", "Receber novidades por e-mail") +
          prefRow("lembretes", "Receber lembretes de curso") +
          prefRow("temaClaro", "Tema claro") +
          '<p class="v2-muted v2-sm" style="margin:10px 0 0;">Preferências de demonstração — mantidas apenas neste navegador.</p>' +
        "</div>" +
        '<div class="v2-callout v2-callout-info"><i class="ti ti-info-circle"></i><span>Ambiente demonstrativo: nenhum dado pessoal é enviado ou armazenado pelo sistema real.</span></div>';
      if (pane) pane.innerHTML = html;

      var form = $("#v2-perfil-form");
      if (form) form.addEventListener("submit", function (e) {
        e.preventDefault();
        var msg = $("#v2-perfil-msg");
        if (msg) { msg.style.display = "flex"; msg.innerHTML = '<i class="ti ti-circle-check"></i><span>Alterações simuladas. Nesta versão, nenhum dado foi enviado ao sistema.</span>'; }
      });
      var cpf = $("#pf-cpf");
      if (cpf) cpf.addEventListener("input", function () { this.value = maskCPF(this.value); });
      var senha = $("#v2-perfil-senha");
      if (senha) senha.addEventListener("click", function () {
        openModal("Alterar senha", '<div class="v2-callout v2-callout-info"><i class="ti ti-info-circle"></i><span>Demonstração V2: a troca de senha é apenas ilustrativa. Nenhuma credencial é alterada.</span></div>' +
          '<div class="v2-field"><label for="md-s1">Nova senha</label><input id="md-s1" class="v2-input" type="password" placeholder="••••••"></div>' +
          '<div class="v2-field"><label for="md-s2">Confirmar nova senha</label><input id="md-s2" class="v2-input" type="password" placeholder="••••••"></div>' +
          '<button type="button" class="v2-btn v2-btn-primary v2-btn-block" data-modal-action="senha-ok">Salvar (simulado)</button>');
      });
      $$('[data-pref]').forEach(function (sw) {
        sw.addEventListener("change", function () { prefs[sw.getAttribute("data-pref")] = sw.checked; savePrefs(prefs); });
      });
    }

    /* ---------- Modal acessível ---------- */
    var overlay = $("#v2-modal-overlay"), modalTitle = $("#v2-modal-title"), modalBody = $("#v2-modal-body"), modalX = $("#v2-modal-x");
    var lastFocus = null;
    function openModal(title, html) {
      lastFocus = document.activeElement;
      if (modalTitle) modalTitle.textContent = title;
      if (modalBody) modalBody.innerHTML = html;
      if (overlay) { overlay.classList.add("is-open"); overlay.setAttribute("aria-hidden", "false"); }
      document.body.classList.add("v2-modal-open");
      if (modalX) modalX.focus();
    }
    function closeModal() {
      if (overlay) { overlay.classList.remove("is-open"); overlay.setAttribute("aria-hidden", "true"); }
      document.body.classList.remove("v2-modal-open");
      if (lastFocus && lastFocus.focus) lastFocus.focus();
    }
    if (overlay) {
      overlay.setAttribute("aria-hidden", "true");
      overlay.addEventListener("click", function (e) { if (e.target === overlay) closeModal(); });
    }
    if (modalX) modalX.addEventListener("click", closeModal);
    document.addEventListener("keydown", function (e) {
      if ((e.key === "Escape" || e.key === "Esc") && overlay && overlay.classList.contains("is-open")) closeModal();
    });
    if (modalBody) modalBody.addEventListener("click", function (e) {
      if (e.target.closest('[data-modal-action="senha-ok"]')) { closeModal(); toast("Senha alterada (demonstração)."); }
    });

    function openPedidoModal(codigo) {
      var p = null, list = ALUNO.pedidos || [];
      for (var i = 0; i < list.length; i++) if (list[i].codigo === codigo) { p = list[i]; break; }
      if (!p) return;
      var c = cursoById(p.cursoId);
      var st = STATUS_PEDIDO[p.status] || STATUS_PEDIDO["aguardando"];
      openModal("Pedido " + p.codigo,
        '<div class="v2-resumo-row"><span>Curso</span><span>' + esc(c ? c.titulo : p.cursoId) + "</span></div>" +
        '<div class="v2-resumo-row"><span>Turma</span><span>' + esc(p.turma) + "</span></div>" +
        '<div class="v2-resumo-row"><span>Data</span><span>' + esc(p.data) + "</span></div>" +
        '<div class="v2-resumo-row"><span>Participantes</span><span>' + p.participantes + "</span></div>" +
        '<div class="v2-resumo-row"><span>Forma de pagamento</span><span>' + esc(p.metodo) + "</span></div>" +
        '<div class="v2-resumo-row"><span>Valor unitário</span><span>' + BRL(p.total / p.participantes) + "</span></div>" +
        '<div class="v2-resumo-row v2-resumo-total"><span>Total</span><span>' + BRL(p.total) + "</span></div>" +
        '<div class="v2-resumo-row"><span>Status</span><span class="v2-badge ' + st.badge + '">' + st.label + "</span></div>" +
        '<div class="v2-callout v2-callout-info" style="margin-top:14px;"><i class="ti ti-info-circle"></i><span>Pedido de demonstração — nenhum pagamento, comprovante ou dado real está associado.</span></div>');
    }

    function openCertModal(cursoId) {
      var c = cursoById(cursoId);
      var cert = null, list = ALUNO.certificados || [];
      for (var i = 0; i < list.length; i++) if (list[i].cursoId === cursoId) { cert = list[i]; break; }
      openModal("Certificado",
        '<div class="v2-cert-preview">' +
          '<i class="ti ti-certificate"></i>' +
          "<p>Certificamos que <b>" + esc(perfil.nome) + "</b> concluiu o curso</p>" +
          "<b class=\"v2-cert-preview-title\">" + esc(c ? c.titulo : cursoId) + "</b>" +
          '<p class="v2-muted v2-sm">Carga horária: ' + (cert ? cert.cargaHoraria : (c ? c.cargaHoraria : "")) + "h" + (cert && cert.codigo ? " · Código: " + esc(cert.codigo) : "") + "</p>" +
        "</div>" +
        '<div class="v2-callout v2-callout-warning" style="margin-top:14px;"><i class="ti ti-alert-triangle"></i><span>Demonstração V2: não há certificado real nesta fase. Nenhum PDF é gerado e nada é enviado ao sistema.</span></div>');
    }

    /* ---------- Delegação de eventos das abas ---------- */
    var container = $(".v2-aluno-area");
    if (container) container.addEventListener("click", function (e) {
      var ped = e.target.closest("[data-pedido]");
      if (ped) { openPedidoModal(ped.getAttribute("data-pedido")); return; }
      var cert = e.target.closest("[data-cert]");
      if (cert) { openCertModal(cert.getAttribute("data-cert")); return; }
      var comp = e.target.closest("[data-comprovante]");
      if (comp) { toast("Envio de comprovante é apenas demonstrativo nesta versão."); return; }
    });

    /* ---------- Abas (URL + History API) ---------- */
    var tabs = $("#v2-aluno-tabs");
    var paneMap = { cursos: "#v2-pane-cursos", pedidos: "#v2-pane-pedidos", certificados: "#v2-pane-certificados", perfil: "#v2-pane-perfil" };

    function setAba(aba, push) {
      if (ALUNO_ABAS.indexOf(aba) === -1) aba = "cursos";
      $$(".v2-tab", tabs).forEach(function (t) {
        var on = t.getAttribute("data-aba") === aba;
        t.classList.toggle("is-on", on);
        t.setAttribute("aria-selected", on ? "true" : "false");
        t.setAttribute("tabindex", on ? "0" : "-1");
      });
      ALUNO_ABAS.forEach(function (a) { var el = $(paneMap[a]); if (el) el.hidden = (a !== aba); });
      $$(".v2-bnav-item[data-bnav]").forEach(function (b) {
        b.classList.toggle("is-active", b.getAttribute("data-bnav") === (aba === "perfil" ? "perfil" : "estudos"));
      });
      try { localStorage.setItem(ALUNO_ABA_KEY, aba); } catch (e) {}
      var url = "/v2/aluno/" + (aba === "cursos" ? "" : "?aba=" + aba);
      try { if (push) history.pushState({ aba: aba }, "", url); else history.replaceState({ aba: aba }, "", url); } catch (e) {}
    }

    if (tabs) {
      tabs.addEventListener("click", function (e) {
        var b = e.target.closest(".v2-tab"); if (!b) return;
        setAba(b.getAttribute("data-aba"), true);
      });
      tabs.addEventListener("keydown", function (e) {
        if (e.key !== "ArrowRight" && e.key !== "ArrowLeft") return;
        var cur = $(".v2-tab.is-on", tabs); if (!cur) return;
        var idx = ALUNO_ABAS.indexOf(cur.getAttribute("data-aba"));
        idx = (e.key === "ArrowRight") ? (idx + 1) % ALUNO_ABAS.length : (idx - 1 + ALUNO_ABAS.length) % ALUNO_ABAS.length;
        setAba(ALUNO_ABAS[idx], true);
        var nb = $('.v2-tab[data-aba="' + ALUNO_ABAS[idx] + '"]', tabs); if (nb) nb.focus();
      });
    }
    window.addEventListener("popstate", function () {
      var qs = new URLSearchParams(location.search);
      setAba(qs.get("aba") || "cursos", false);
    });

    /* ---------- Menu do avatar ---------- */
    var avatarBtn = $("#v2-avatar-btn"), dd = $("#v2-avatar-dd");
    if (avatarBtn && dd) {
      function closeDD() { dd.classList.remove("is-open"); avatarBtn.setAttribute("aria-expanded", "false"); }
      avatarBtn.addEventListener("click", function (e) {
        e.stopPropagation();
        var open = dd.classList.toggle("is-open");
        avatarBtn.setAttribute("aria-expanded", open ? "true" : "false");
      });
      document.addEventListener("click", function (e) { if (!dd.contains(e.target) && e.target !== avatarBtn) closeDD(); });
      document.addEventListener("keydown", function (e) { if (e.key === "Escape" || e.key === "Esc") closeDD(); });
      var logout = $("#v2-logout-demo");
      if (logout) logout.addEventListener("click", function () { closeDD(); toast("Sessão de demonstração encerrada."); setTimeout(function () { location.href = "/v2/"; }, 900); });
    }

    /* ---------- Render inicial ---------- */
    renderCursos();
    renderPedidos();
    renderCertificados();
    renderPerfil();

    var qs0 = new URLSearchParams(location.search);
    var abaInicial = qs0.get("aba");
    if (ALUNO_ABAS.indexOf(abaInicial) === -1) {
      var stored = null; try { stored = localStorage.getItem(ALUNO_ABA_KEY); } catch (e) {}
      abaInicial = (ALUNO_ABAS.indexOf(stored) !== -1) ? stored : "cursos";
    }
    setAba(abaInicial, false);
  }

  /* ============================================================
     LMS / AULA V2 (/v2/aula/?curso=…&aula=…)
     Player visual, módulos, quiz, progresso local. Sem rede.
     ============================================================ */
  var LMS_DONE_KEY = "v2_lms_completed_lessons";

  function initAulaV2() {
    var root = $("#v2-lms");
    if (!root) return;
    var emptyEl = $("#v2-lms-empty");
    var emptyMsg = $("#v2-lms-empty-msg");

    function tipoLMS(t) {
      if (t === "pdf") return "arquivo";
      if (t === "atividade") return "texto";
      return (["video", "texto", "arquivo", "link", "quiz"].indexOf(t) !== -1) ? t : "texto";
    }
    function tipoMeta(t) {
      switch (t) {
        case "video": return { ic: "ti-player-play", label: "Aula em vídeo" };
        case "texto": return { ic: "ti-file-text", label: "Leitura" };
        case "arquivo": return { ic: "ti-file-type-pdf", label: "Material" };
        case "link": return { ic: "ti-link", label: "Link" };
        case "quiz": return { ic: "ti-help-circle", label: "Quiz" };
        default: return { ic: "ti-circle", label: "Conteúdo" };
      }
    }
    function matIcon(t) { return t === "pdf" ? "ti-file-type-pdf" : t === "link" ? "ti-link" : t === "arquivo" ? "ti-file-download" : "ti-file"; }

    function normCurso(c) {
      var mods = (c.modulos && c.modulos.length) ? c.modulos : null;
      if (!mods) {
        mods = (c.conteudosProgramaticos || []).map(function (m, mi) {
          return { id: "m" + mi, titulo: m.titulo, duracao: m.duracao || "", aulas: (m.aulas || []).map(function (a, ai) {
            return { id: "m" + mi + "-a" + ai, titulo: a.titulo, tipo: tipoLMS(a.tipo), duracao: a.duracao || "", descricao: "", concluidaDemo: false };
          }) };
        });
      }
      return { id: c.id, titulo: c.titulo, categoria: c.categoria, modulos: mods };
    }

    var params = new URLSearchParams(location.search);
    var cursoId = params.get("curso");
    var aulaId = params.get("aula");
    var cursoRaw = null;
    for (var i = 0; i < CURSOS.length; i++) { if (CURSOS[i].id === cursoId) { cursoRaw = CURSOS[i]; break; } }

    if (!cursoRaw) {
      if (emptyEl) emptyEl.style.display = "block";
      root.style.display = "none";
      document.title = "Aula não encontrada — Desbloqueia Cursos";
      return;
    }

    var curso = normCurso(cursoRaw);
    var lessons = [];
    curso.modulos.forEach(function (m, mi) { (m.aulas || []).forEach(function (a) { lessons.push({ mi: mi, mod: m, aula: a }); }); });
    if (!lessons.length) {
      if (emptyEl) emptyEl.style.display = "block";
      if (emptyMsg) emptyMsg.textContent = "Este curso ainda não tem aulas nesta demonstração.";
      root.style.display = "none";
      return;
    }
    root.style.display = "";
    document.title = curso.titulo + " — Aula";
    var cs = catStyle(curso.categoria);

    /* ---- Estado de conclusão (localStorage, não sensível) ---- */
    var completed = {};
    lessons.forEach(function (l) { if (l.aula.concluidaDemo) completed[l.aula.id] = true; });
    (function () {
      try {
        var o = JSON.parse(localStorage.getItem(LMS_DONE_KEY)) || {};
        if (o[curso.id]) o[curso.id].forEach(function (id) { completed[id] = true; });
      } catch (e) {}
    })();
    function saveDone() {
      try {
        var o = {}; try { o = JSON.parse(localStorage.getItem(LMS_DONE_KEY)) || {}; } catch (e) {}
        o[curso.id] = Object.keys(completed);
        localStorage.setItem(LMS_DONE_KEY, JSON.stringify(o));
      } catch (e) {}
    }

    function findLesson(aid) { for (var k = 0; k < lessons.length; k++) if (lessons[k].aula.id === aid) return lessons[k]; return null; }
    var navList = lessons.filter(function (l) { return !l.aula.bloqueadaDemo; });
    function progress() {
      var tot = 0, don = 0;
      lessons.forEach(function (l) { if (l.aula.bloqueadaDemo) return; tot++; if (completed[l.aula.id]) don++; });
      return tot ? Math.round(don / tot * 100) : 0;
    }
    function allDone() {
      var tot = 0, don = 0;
      lessons.forEach(function (l) { if (l.aula.bloqueadaDemo) return; tot++; if (completed[l.aula.id]) don++; });
      return tot > 0 && don === tot;
    }

    /* ---- Aula atual ---- */
    var current = aulaId ? findLesson(aulaId) : null;
    var replaced = false;
    if (!current || current.aula.bloqueadaDemo) { current = navList[0] || lessons[0]; replaced = true; }
    var openMods = {}; openMods[current.mod.id] = true;

    function urlFor(aid) { return "/v2/aula/?curso=" + encodeURIComponent(curso.id) + "&aula=" + encodeURIComponent(aid); }
    function setURL(aid, push) { try { if (push) history.pushState({ aula: aid }, "", urlFor(aid)); else history.replaceState({ aula: aid }, "", urlFor(aid)); } catch (e) {} }
    if (replaced) setURL(current.aula.id, false);

    /* ---- Player (estado visual) ---- */
    var player = { playing: false, prog: 0 };

    /* ---------- Render: topbar ---------- */
    function renderTopbar() {
      var pct = progress();
      var c1 = $("#v2-lms-curso"); if (c1) c1.textContent = curso.titulo;
      var c2 = $("#v2-lms-modulo"); if (c2) c2.textContent = current.mod.titulo;
      var pc = $("#v2-lms-top-pct"); if (pc) pc.textContent = pct + "%";
      var bar = $("#v2-lms-top-bar"); if (bar) bar.style.width = pct + "%";
    }

    /* ---------- Render: stage (player / texto / quiz / material) ---------- */
    function blocksHTML(blocks) {
      return (blocks || []).map(function (b) {
        if (b.h) return "<h3 class=\"v2-h3\" style=\"margin:14px 0 6px;\">" + esc(b.h) + "</h3>";
        if (b.p) return "<p class=\"v2-muted\">" + esc(b.p) + "</p>";
        if (b.ul) return "<ul class=\"v2-learn\" style=\"grid-template-columns:1fr;\">" + b.ul.map(function (li) { return "<li><i class=\"ti ti-point-filled\" style=\"color:var(--v2-laranja);\"></i> " + esc(li) + "</li>"; }).join("") + "</ul>";
        if (b.callout) return "<div class=\"v2-callout v2-callout-info\"><i class=\"ti ti-info-circle\"></i><span>" + esc(b.callout) + "</span></div>";
        return "";
      }).join("");
    }
    function renderStage() {
      var stage = $("#v2-lms-stage"); if (!stage) return;
      var a = current.aula;
      if (a.tipo === "video") {
        stage.innerHTML =
          '<div class="v2-player">' +
            '<button class="v2-player-play" id="v2-lms-play" aria-label="Reproduzir vídeo (demonstração)"><i class="ti ti-player-play-filled"></i></button>' +
            '<div class="v2-player-time" id="v2-lms-time">00:00 / ' + esc(a.duracao || "12:00") + "</div>" +
            '<div class="v2-player-bar"><span id="v2-lms-pbar" style="width:0%"></span></div>' +
          "</div>" +
          '<p class="v2-muted v2-sm v2-lms-demonote"><i class="ti ti-info-circle"></i> Player demonstrativo — nenhum vídeo é carregado nesta versão.</p>';
      } else if (a.tipo === "texto") {
        stage.innerHTML = '<article class="v2-block v2-lms-prose">' + (a.conteudo ? blocksHTML(a.conteudo) : '<p class="v2-muted">' + esc(a.descricao || "") + "</p>") + "</article>";
      } else if (a.tipo === "quiz" && a.quiz) {
        var alts = a.quiz.alternativas.map(function (alt, idx) {
          return '<label class="v2-quiz-alt"><input type="radio" name="v2-lms-quiz" value="' + idx + '"> ' + esc(alt) + "</label>";
        }).join("");
        stage.innerHTML =
          '<div class="v2-quiz-q">' +
            '<div class="v2-quiz-enun">' + esc(a.quiz.enunciado) + "</div>" + alts +
            '<button type="button" class="v2-btn v2-btn-primary v2-btn-block" id="v2-lms-quiz-go"><i class="ti ti-send"></i> Responder</button>' +
          "</div>" +
          '<div class="v2-quiz-result" id="v2-lms-quiz-result" role="status" aria-live="polite" style="display:none;"></div>';
      } else {
        var meta = tipoMeta(a.tipo);
        stage.innerHTML =
          '<div class="v2-block v2-center v2-lms-material">' +
            '<i class="ti ' + meta.ic + '" style="font-size:42px;color:var(--v2-laranja);"></i>' +
            '<h3 class="v2-h3" style="margin:8px 0 4px;">' + esc(a.titulo) + "</h3>" +
            '<p class="v2-muted v2-sm" style="margin:0 auto 14px;max-width:40ch;">' + esc(a.descricao || "Material complementar desta aula.") + "</p>" +
            '<button type="button" class="v2-btn v2-btn-primary" data-lms-openmat="' + esc(a.id) + '"><i class="ti ti-external-link"></i> Abrir material</button>' +
          "</div>";
      }
    }

    /* ---------- Render: pane Conteúdo ---------- */
    function renderConteudo() {
      var pane = $("#v2-lms-pane-conteudo"); if (!pane) return;
      var a = current.aula, meta = tipoMeta(a.tipo);
      var done = !!completed[a.id];
      var html = '<span class="v2-badge v2-badge-novo" style="background:#fff4ec;color:#cc5500;"><i class="ti ' + meta.ic + '"></i> ' + meta.label + "</span>" +
        '<h1 class="v2-h3" style="margin:10px 0 6px;font-size:1.2rem;">' + esc(a.titulo) + "</h1>" +
        '<div class="v2-card-meta" style="margin-bottom:12px;">' +
          (a.duracao ? '<span><i class="ti ti-clock"></i> ' + esc(a.duracao) + "</span>" : "") +
          (done ? '<span style="color:var(--v2-success);"><i class="ti ti-circle-check-filled"></i> Concluída</span>' : '<span><i class="ti ti-circle"></i> Não concluída</span>') +
        "</div>" +
        (a.descricao && a.tipo !== "texto" ? '<p class="v2-muted">' + esc(a.descricao) + "</p>" : "");

      // Materiais de apoio
      if (a.materiais && a.materiais.length) {
        html += '<div class="v2-block" style="margin-top:14px;"><h2 class="v2-h3" style="margin-bottom:10px;">Materiais de apoio</h2>' +
          a.materiais.map(function (mt, idx) {
            return '<div class="v2-list-item" style="cursor:default;">' +
              '<span class="v2-list-thumb" style="width:40px;height:40px;background:#fff4ec;"><i class="ti ' + matIcon(mt.tipo) + '" style="color:#cc5500;"></i></span>' +
              '<span class="v2-list-body"><b>' + esc(mt.nome) + "</b><span class=\"v2-muted v2-sm\">" + esc(mt.descricao || "") + "</span></span>" +
              '<button type="button" class="v2-btn v2-btn-outline v2-btn-sm" data-lms-mat="' + idx + '">Abrir material</button>' +
            "</div>";
          }).join("") + "</div>";
      }

      // Ação de conclusão (exceto quiz, cuja conclusão vem do acerto)
      if (a.tipo !== "quiz") {
        html += '<div class="v2-aula-actions">' +
          (done
            ? '<button type="button" class="v2-btn v2-btn-block v2-concluido" disabled><i class="ti ti-circle-check"></i> Concluído</button>'
            : '<button type="button" class="v2-btn v2-btn-primary v2-btn-block" data-lms-mark><i class="ti ti-check"></i> Marcar como concluído</button>') +
          "</div>";
      }
      html += '<div id="v2-lms-feedback" class="v2-callout v2-callout-success" role="status" aria-live="polite" style="display:none;"></div>';

      // Navegação anterior/próxima
      var idx = -1; for (var k = 0; k < navList.length; k++) if (navList[k].aula.id === a.id) { idx = k; break; }
      var hasPrev = idx > 0, hasNext = idx > -1 && idx < navList.length - 1;
      html += '<div class="v2-aula-actions v2-lms-nav">' +
        '<button type="button" class="v2-btn v2-btn-ghost" data-lms-nav="prev"' + (hasPrev ? "" : " disabled") + '><i class="ti ti-arrow-left"></i> Anterior</button>' +
        '<button type="button" class="v2-btn v2-btn-primary" data-lms-nav="next"' + (hasNext ? "" : " disabled") + '>Próxima aula <i class="ti ti-arrow-right"></i></button>' +
        "</div>";

      // CTA de conclusão do curso
      if (allDone()) {
        html += '<div class="v2-block v2-center" style="background:linear-gradient(135deg,#fff4ec,#f0e8ff);margin-top:6px;">' +
          '<i class="ti ti-confetti" style="font-size:40px;color:var(--v2-laranja);"></i>' +
          '<h2 class="v2-h3" style="margin:8px 0 4px;">Parabéns!</h2>' +
          '<p class="v2-muted" style="margin:0 auto 14px;max-width:44ch;">Você concluiu a jornada demonstrativa deste curso.</p>' +
          '<a href="/v2/aluno/?aba=cursos" class="v2-btn v2-btn-primary">Voltar para meus cursos</a>' +
          "</div>";
      }
      pane.innerHTML = html;
    }

    /* ---------- Render: árvore de módulos (sidebar + aba) ---------- */
    function treeHTML() {
      return curso.modulos.map(function (m) {
        var aulas = m.aulas || [];
        var don = aulas.filter(function (a) { return completed[a.id]; }).length;
        var open = !!openMods[m.id];
        var items = aulas.map(function (a) {
          var locked = !!a.bloqueadaDemo;
          var isDone = !!completed[a.id];
          var isCur = a.id === current.aula.id;
          var ic = locked ? "ti-lock" : isDone ? "ti-circle-check-filled" : isCur ? "ti-player-play-filled" : tipoMeta(a.tipo).ic;
          var cls = "v2-mod-item" + (isDone ? " is-done" : "") + (isCur ? " is-current" : "") + (locked ? " is-locked" : "");
          if (locked) return '<div class="' + cls + '" aria-disabled="true"><i class="ti ' + ic + ' v2-mi-ic"></i><span class="v2-mi-label">' + esc(a.titulo) + "</span></div>";
          return '<button type="button" class="' + cls + '" data-lms-lesson="' + esc(a.id) + '"><i class="ti ' + ic + ' v2-mi-ic"></i><span class="v2-mi-label">' + esc(a.titulo) + "</span>" + (a.duracao ? '<span class="v2-muted v2-sm v2-mi-dur">' + esc(a.duracao) + "</span>" : "") + "</button>";
        }).join("");
        return '<div class="v2-mod' + (open ? " is-open" : "") + '">' +
          '<button type="button" class="v2-mod-head" data-lms-mod="' + esc(m.id) + '" aria-expanded="' + (open ? "true" : "false") + '">' +
            '<span class="v2-mod-num">' + don + "/" + aulas.length + "</span>" +
            '<span class="v2-mod-info"><b>' + esc(m.titulo) + "</b><small>" + aulas.length + (aulas.length === 1 ? " item" : " itens") + (m.duracao ? " · " + esc(m.duracao) : "") + "</small></span>" +
            '<i class="ti ti-chevron-down v2-mod-chev" aria-hidden="true"></i>' +
          "</button>" +
          '<div class="v2-mod-items">' + items + "</div>" +
        "</div>";
      }).join("");
    }
    function renderTree() {
      var h = treeHTML();
      var t1 = $("#v2-lms-tree"); if (t1) t1.innerHTML = h;
      var t2 = $("#v2-lms-pane-modulos"); if (t2) t2.innerHTML = h;
    }

    function renderAll() { renderTopbar(); renderStage(); renderConteudo(); renderTree(); }

    function openLesson(aid, push) {
      var l = findLesson(aid); if (!l || l.aula.bloqueadaDemo) return;
      current = l; openMods[l.mod.id] = true; player = { playing: false, prog: 0 };
      renderAll();
      setURL(aid, push);
      window.scrollTo({ top: 0, behavior: "smooth" });
    }

    function markDone(aid, silent) {
      completed[aid] = true; saveDone();
      renderTopbar(); renderConteudo(); renderTree();
      if (!silent) {
        var fb = $("#v2-lms-feedback");
        if (fb) { fb.style.display = "flex"; fb.innerHTML = '<i class="ti ti-circle-check"></i><span>Progresso atualizado nesta demonstração.</span>'; }
      }
    }

    /* ---------- Modal demonstrativo ---------- */
    var ov = $("#v2-lms-modal-overlay"), mTitle = $("#v2-lms-modal-title"), mBody = $("#v2-lms-modal-body"), mX = $("#v2-lms-modal-x");
    var lastFocus = null;
    function openModal(title, html) {
      lastFocus = document.activeElement;
      if (mTitle) mTitle.textContent = title;
      if (mBody) mBody.innerHTML = html;
      if (ov) { ov.classList.add("is-open"); ov.setAttribute("aria-hidden", "false"); }
      document.body.classList.add("v2-modal-open");
      if (mX) mX.focus();
    }
    function closeModal() {
      if (ov) { ov.classList.remove("is-open"); ov.setAttribute("aria-hidden", "true"); }
      document.body.classList.remove("v2-modal-open");
      if (lastFocus && lastFocus.focus) lastFocus.focus();
    }
    if (ov) { ov.setAttribute("aria-hidden", "true"); ov.addEventListener("click", function (e) { if (e.target === ov) closeModal(); }); }
    if (mX) mX.addEventListener("click", closeModal);
    document.addEventListener("keydown", function (e) { if ((e.key === "Escape" || e.key === "Esc") && ov && ov.classList.contains("is-open")) closeModal(); });

    function materialModal(nome, descricao) {
      openModal(nome || "Material de apoio",
        '<div class="v2-callout v2-callout-info"><i class="ti ti-info-circle"></i><span>Demonstração V2: este material é ilustrativo. Nenhum arquivo é aberto ou baixado e nada é enviado ao sistema.</span></div>' +
        (descricao ? '<p class="v2-muted" style="margin:0;">' + esc(descricao) + "</p>" : ""));
    }

    /* ---------- Player visual ---------- */
    function playToggle() {
      var btn = $("#v2-lms-play"); if (!btn) return;
      player.playing = !player.playing;
      var ic = btn.querySelector("i");
      if (ic) ic.className = player.playing ? "ti ti-player-pause-filled" : "ti ti-player-play-filled";
      if (player.playing) { player.prog = Math.min(100, player.prog + 18); var bar = $("#v2-lms-pbar"); if (bar) bar.style.width = player.prog + "%"; }
      var time = $("#v2-lms-time"); if (time) time.textContent = (player.playing ? "Reproduzindo…" : "Pausado") + " (demonstração)";
    }

    /* ---------- Quiz ---------- */
    function quizAnswer() {
      var a = current.aula; if (!a.quiz) return;
      var sel = $('input[name="v2-lms-quiz"]:checked', root);
      var res = $("#v2-lms-quiz-result");
      if (!sel) { if (res) { res.style.display = "block"; res.className = "v2-quiz-result"; res.innerHTML = '<p class="v2-muted">Selecione uma alternativa para responder.</p>'; } return; }
      var val = parseInt(sel.value, 10);
      var ok = val === a.quiz.correta;
      $$(".v2-quiz-alt", root).forEach(function (alt, idx) {
        alt.classList.remove("is-correct", "is-wrong");
        if (idx === a.quiz.correta) alt.classList.add("is-correct");
        else if (idx === val) alt.classList.add("is-wrong");
      });
      if (res) {
        res.style.display = "block";
        res.innerHTML = '<div class="v2-quiz-score ' + (ok ? "ok" : "no") + '">' + (ok ? "Correto!" : "Quase lá!") + "</div>" +
          "<p>" + esc(a.quiz.explicacao || "") + "</p>" +
          (ok ? "" : '<button type="button" class="v2-btn v2-btn-outline" id="v2-lms-quiz-retry"><i class="ti ti-rotate"></i> Tentar novamente</button>');
      }
      var go = $("#v2-lms-quiz-go"); if (go) go.style.display = "none";
      if (ok) markDone(a.id, false);
    }
    function quizRetry() {
      $$(".v2-quiz-alt", root).forEach(function (alt) { alt.classList.remove("is-correct", "is-wrong"); var inp = alt.querySelector("input"); if (inp) inp.checked = false; });
      var res = $("#v2-lms-quiz-result"); if (res) { res.style.display = "none"; res.innerHTML = ""; }
      var go = $("#v2-lms-quiz-go"); if (go) go.style.display = "";
    }

    /* ---------- Tabs (mobile) ---------- */
    var tabs = $("#v2-lms-tabs");
    function setTab(tab) {
      $$(".v2-lms-tab", tabs).forEach(function (t) { var on = t.getAttribute("data-tab") === tab; t.classList.toggle("is-on", on); t.setAttribute("aria-selected", on ? "true" : "false"); });
      var pc = $("#v2-lms-pane-conteudo"), pm = $("#v2-lms-pane-modulos");
      if (pc) pc.hidden = (tab !== "conteudo");
      if (pm) pm.hidden = (tab !== "modulos");
    }
    if (tabs) tabs.addEventListener("click", function (e) { var b = e.target.closest(".v2-lms-tab"); if (b) setTab(b.getAttribute("data-tab")); });

    /* ---------- Delegação de cliques ---------- */
    root.addEventListener("click", function (e) {
      var lesson = e.target.closest("[data-lms-lesson]");
      if (lesson) { openLesson(lesson.getAttribute("data-lms-lesson"), true); return; }
      var mod = e.target.closest("[data-lms-mod]");
      if (mod) { var id = mod.getAttribute("data-lms-mod"); openMods[id] = !openMods[id]; renderTree(); return; }
      if (e.target.closest("#v2-lms-play")) { playToggle(); return; }
      if (e.target.closest("[data-lms-mark]")) { markDone(current.aula.id, false); return; }
      var matBtn = e.target.closest("[data-lms-mat]");
      if (matBtn) { var mi = parseInt(matBtn.getAttribute("data-lms-mat"), 10); var mt = (current.aula.materiais || [])[mi]; if (mt) materialModal(mt.nome, mt.descricao); return; }
      var openMat = e.target.closest("[data-lms-openmat]");
      if (openMat) { materialModal(current.aula.titulo, current.aula.descricao); return; }
      if (e.target.closest("#v2-lms-quiz-go")) { quizAnswer(); return; }
      if (e.target.closest("#v2-lms-quiz-retry")) { quizRetry(); return; }
      if (e.target.closest("#v2-lms-more")) { openModal("Opções da aula", '<div class="v2-callout v2-callout-info"><i class="ti ti-info-circle"></i><span>Menu demonstrativo. Nesta versão não há ações reais como velocidade de reprodução, legendas ou denúncia.</span></div><a href="/v2/aluno/" class="v2-btn v2-btn-ghost v2-btn-block">Voltar à minha área</a>'); return; }
      var nav = e.target.closest("[data-lms-nav]");
      if (nav) {
        if (nav.hasAttribute("disabled")) return;
        var dir = nav.getAttribute("data-lms-nav");
        var idx = -1; for (var k = 0; k < navList.length; k++) if (navList[k].aula.id === current.aula.id) { idx = k; break; }
        var target = dir === "next" ? navList[idx + 1] : navList[idx - 1];
        if (target) openLesson(target.aula.id, true);
        return;
      }
    });

    /* ---------- popstate (voltar/avançar do navegador) ---------- */
    window.addEventListener("popstate", function () {
      var qs = new URLSearchParams(location.search);
      var aid = qs.get("aula");
      var l = aid ? findLesson(aid) : null;
      if (l && !l.aula.bloqueadaDemo) { current = l; openMods[l.mod.id] = true; player = { playing: false, prog: 0 }; renderAll(); }
    });

    /* ---------- Render inicial ---------- */
    renderAll();
    setTab("conteudo");
  }

  /* ============================================================
     AUTENTICAÇÃO V2 (/v2/login/ /v2/cadastro/ /v2/recuperar-senha/)
     Demonstração: sem rede, sem backend, sem dados pessoais salvos.
     ============================================================ */
  function safeRedirect(raw) {
    if (!raw) return "/v2/aluno/";
    raw = String(raw).trim();
    var map = { checkout: "/v2/checkout/", curso: "/v2/curso/", catalogo: "/v2/catalogo/", aluno: "/v2/aluno/", aula: "/v2/aula/" };
    if (map[raw.toLowerCase()]) return map[raw.toLowerCase()];
    // Apenas caminhos internos absolutos /v2/… (rejeita externo, protocolo e protocolo-relativo)
    if (/^\/v2\//.test(raw) && raw.indexOf("//") === -1 && raw.indexOf(":") === -1 && raw.indexOf("\\") === -1) {
      var allow = ["/v2/aluno/", "/v2/checkout/", "/v2/curso/", "/v2/catalogo/", "/v2/aula/", "/v2/login/", "/v2/cadastro/"];
      for (var i = 0; i < allow.length; i++) { if (raw === allow[i] || raw.indexOf(allow[i]) === 0) return raw; }
    }
    return "/v2/aluno/";
  }

  function passwordStrength(pw) {
    var s = 0;
    if (pw.length >= 8) s++;
    if (/[a-z]/.test(pw) && /[A-Z]/.test(pw)) s++;
    if (/\d/.test(pw)) s++;
    if (/[^A-Za-z0-9]/.test(pw)) s++;
    if (pw.length < 8) s = Math.min(s, 1);
    if (s <= 1) return { score: 1, label: "Fraca", cls: "is-weak" };
    if (s === 2) return { score: 2, label: "Média", cls: "is-medium" };
    if (s === 3) return { score: 3, label: "Boa", cls: "is-good" };
    return { score: 4, label: "Forte", cls: "is-strong" };
  }

  function authLoading(btn, txt) { btn.disabled = true; btn.dataset.label = btn.innerHTML; btn.innerHTML = '<i class="ti ti-loader-2"></i> ' + txt; }
  function authFeedback(el, cls, msg) {
    if (!el) return;
    el.className = "v2-callout " + cls;
    el.style.display = "flex";
    el.innerHTML = '<i class="ti ' + (cls.indexOf("success") !== -1 ? "ti-circle-check" : cls.indexOf("danger") !== -1 ? "ti-alert-circle" : "ti-info-circle") + '"></i><span>' + esc(msg) + "</span>";
  }

  function initLoginV2() {
    var form = $("#v2-login-v2"); if (!form) return;
    var fLogin = $("#lg-f-login"), fSenha = $("#lg-f-senha"), fb = $("#lg-feedback");
    var redir = safeRedirect(new URLSearchParams(location.search).get("redirect"));
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      clearError(fLogin); clearError(fSenha);
      var ok = true;
      var login = ($("#lg-login").value || "").trim();
      var senha = $("#lg-senha").value || "";
      if (!login) { setError(fLogin, "Informe seu e-mail ou CPF."); ok = false; }
      else if (login.indexOf("@") >= 0) { if (!validEmail(login)) { setError(fLogin, "E-mail inválido."); ok = false; } }
      else if (/\d/.test(login) && login.replace(/\D/g, "").length < 11) { setError(fLogin, "CPF incompleto (11 dígitos)."); ok = false; }
      if (!senha) { setError(fSenha, "Informe sua senha."); ok = false; }
      if (!ok) { var ef = $(".has-error .v2-input", form); if (ef) ef.focus(); return; }
      var btn = form.querySelector('button[type="submit"]');
      authLoading(btn, "Entrando…");
      authFeedback(fb, "v2-callout-success", "Acesso demonstrativo liberado.");
      setTimeout(function () { location.href = redir; }, 1100);
    });
  }

  function initCadastroV2() {
    var form = $("#v2-cadastro-v2"); if (!form) return;
    var redir = safeRedirect(new URLSearchParams(location.search).get("redirect"));
    var cpf = $("#cd-cpf");
    if (cpf) cpf.addEventListener("input", function () { this.value = maskCPF(this.value); });
    var senha = $("#cd-senha"), bar = $("#cd-strength-bar"), lbl = $("#cd-strength-label");
    if (senha) senha.addEventListener("input", function () {
      var st = passwordStrength(this.value);
      if (bar) { bar.className = "v2-strength-bar " + st.cls; bar.style.width = (st.score * 25) + "%"; }
      if (lbl) lbl.textContent = this.value ? st.label : "";
    });
    var fb = $("#cd-feedback");
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var ok = true;
      [["cd-nome", "cd-f-nome", function (v) { return v.trim().split(/\s+/).filter(Boolean).length >= 2 && v.trim().length >= 5; }, "Informe nome e sobrenome."],
       ["cd-email", "cd-f-email", function (v) { return validEmail(v.trim()); }, "E-mail inválido."],
       ["cd-cpf", "cd-f-cpf", function (v) { return v.replace(/\D/g, "").length === 11; }, "CPF deve ter 11 dígitos."],
       ["cd-senha", "cd-f-senha", function (v) { return v.length >= 8; }, "A senha precisa de ao menos 8 caracteres."]
      ].forEach(function (r) {
        var field = $("#" + r[1]); var val = $("#" + r[0]).value; clearError(field);
        if (!r[2](val)) { setError(field, r[3]); ok = false; }
      });
      var s1 = $("#cd-senha").value, s2 = $("#cd-senha2").value, fc = $("#cd-f-senha2"); clearError(fc);
      if (s1 !== s2 || !s2) { setError(fc, "As senhas não coincidem."); ok = false; }
      var termos = $("#cd-termos"), ft = $("#cd-f-termos"); if (ft) clearError(ft);
      if (termos && !termos.checked) { if (ft) setError(ft, "É necessário aceitar os termos desta demonstração."); ok = false; }
      if (!ok) { var ef = $(".has-error .v2-input", form); if (ef) ef.focus(); return; }
      var btn = form.querySelector('button[type="submit"]');
      authLoading(btn, "Criando…");
      authFeedback(fb, "v2-callout-success", "Conta demonstrativa criada com sucesso. Nenhum dado foi cadastrado no sistema real.");
      setTimeout(function () { location.href = redir; }, 1300);
    });
  }

  function initRecuperarV2() {
    var form = $("#v2-recuperar-v2"); if (!form) return;
    var fEmail = $("#rp-f-email"), fb = $("#rp-feedback");
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      clearError(fEmail);
      var email = ($("#rp-email").value || "").trim();
      if (!validEmail(email)) { setError(fEmail, "Informe um e-mail válido."); var ef = $(".v2-input", fEmail); if (ef) ef.focus(); return; }
      var btn = form.querySelector('button[type="submit"]');
      authLoading(btn, "Enviando…");
      authFeedback(fb, "v2-callout-info", "Se esta fosse uma conta ativa, as instruções seriam enviadas para o e-mail informado.");
      setTimeout(function () { btn.disabled = false; btn.innerHTML = btn.dataset.label || "Enviar instruções"; }, 1200);
    });
  }

  /* ============================================================
     EVENTOS V2 (/v2/eventos/) e CATEGORIAS V2 (/v2/categorias/)
     Dados demonstrativos locais. Sem rede/backend.
     ============================================================ */
  function v2CatId(it) { return it.categoriaId || (window.V2_CATEGORIA_DE || {})[it.categoria] || "outros"; }

  var EV_STATUS = {
    abertas: { label: "Inscrições abertas", badge: "v2-badge-gratis" },
    ultimas: { label: "Últimas vagas", badge: "v2-badge-destaque" },
    encerrado: { label: "Encerrado", badge: "v2-badge-esgotado" }
  };
  function evData(e) { return e.dataFimDemo ? (e.dataInicioDemo + " a " + e.dataFimDemo) : (e.dataInicioDemo || ""); }
  function evModBadge(m) { return '<span class="v2-badge v2-badge-mod">' + esc(m) + "</span>"; }

  function initEventosV2() {
    var grid = $("#v2-ev-grid"); if (!grid) return;
    var EVENTOS = CURSOS.filter(function (c) { return c.tipo === "evento"; });
    var INFO = window.V2_CATEGORIAS_INFO || [];
    function catNome(id) { for (var i = 0; i < INFO.length; i++) if (INFO[i].id === id) return INFO[i].nome; return id; }

    var state = { q: "", cat: "", mod: "", periodo: "", status: "", ordem: "relevancia" };

    // Radios de categoria (a partir dos eventos)
    var catsBox = $("#v2-ev-categorias");
    var catIds = [];
    EVENTOS.forEach(function (e) { var id = v2CatId(e); if (catIds.indexOf(id) === -1) catIds.push(id); });
    if (catsBox) {
      var h = '<label class="v2-fopt"><input type="radio" name="v2-ev-cat" value="" checked> Todas</label>';
      catIds.forEach(function (id) { h += '<label class="v2-fopt"><input type="radio" name="v2-ev-cat" value="' + esc(id) + '"> ' + esc(catNome(id)) + "</label>"; });
      catsBox.innerHTML = h;
    }

    function hrefEvento(e) { return "/v2/curso/?id=" + encodeURIComponent(e.cursoBase || e.id); }

    function heroHTML(e) {
      var c = catStyle(e.categoria), st = EV_STATUS[e.statusDemo] || EV_STATUS.abertas;
      return '<div class="v2-ev-hero">' +
        '<div class="v2-ev-hero-thumb" style="background:linear-gradient(135deg,' + c.g1 + ',' + c.g2 + ');"><i class="ti ' + c.icon + '" style="color:' + c.cor + ';"></i>' +
          '<span class="v2-badge v2-badge-destaque v2-ev-hero-badge"><i class="ti ti-flame"></i> Destaque</span></div>' +
        '<div class="v2-ev-hero-body">' +
          '<span class="v2-card-cat">' + esc(e.categoria) + " · " + esc(e.modalidade) + "</span>" +
          '<h2 class="v2-h2">' + esc(e.titulo) + "</h2>" +
          '<p class="v2-muted">' + esc(e.resumo || "") + "</p>" +
          '<div class="v2-course-meta"><span><i class="ti ti-calendar"></i> ' + esc(evData(e)) + "</span><span><i class=\"ti ti-map-pin\"></i> " + esc(e.localDemo || "Online") + '</span><span class="v2-badge ' + st.badge + '">' + st.label + "</span></div>" +
          '<a href="' + hrefEvento(e) + '" class="v2-btn v2-btn-primary">Ver evento <i class="ti ti-arrow-right"></i></a>' +
        "</div></div>";
    }
    var destBox = $("#v2-ev-destaque");
    var dest = EVENTOS.filter(function (e) { return e.destaqueEvento; })[0] || EVENTOS[0];
    if (destBox && dest) destBox.innerHTML = heroHTML(dest);

    function cardHTML(e) {
      var c = catStyle(e.categoria), st = EV_STATUS[e.statusDemo] || EV_STATUS.abertas;
      var vagasTxt = e.statusDemo === "encerrado" ? "Inscrições encerradas" : (e.vagasDemo > 0 ? (e.vagasDemo + " vagas") : "Vagas abertas");
      return '<a class="v2-card" href="' + hrefEvento(e) + '" aria-label="' + esc(e.titulo) + '">' +
        '<div class="v2-thumb" style="background:linear-gradient(135deg,' + c.g1 + ',' + c.g2 + ');"><div class="v2-thumb-badges">' + evModBadge(e.modalidade) + "</div><i class=\"ti " + c.icon + '" style="color:' + c.cor + ';"></i></div>' +
        '<div class="v2-card-body">' +
          '<span class="v2-card-cat">' + esc(e.categoria) + "</span>" +
          '<h3 class="v2-card-title">' + esc(e.titulo) + "</h3>" +
          '<div class="v2-card-meta"><span><i class="ti ti-calendar"></i> ' + esc(evData(e)) + "</span><span><i class=\"ti ti-map-pin\"></i> " + esc(e.localDemo || "Online") + "</span></div>" +
          '<div class="v2-card-meta"><span class="v2-badge ' + st.badge + '">' + st.label + '</span><span class="v2-muted v2-sm">' + esc(vagasTxt) + "</span></div>" +
          '<div class="v2-card-foot"><span class="v2-price">' + BRL(e.preco) + '</span><span class="v2-btn v2-btn-outline v2-btn-sm">Ver detalhes</span></div>' +
        "</div></a>";
    }

    function matches(e) {
      if (state.q) { var hay = (e.titulo + " " + e.categoria + " " + (e.resumo || "")).toLowerCase(); if (hay.indexOf(state.q.toLowerCase()) === -1) return false; }
      if (state.cat && v2CatId(e) !== state.cat) return false;
      if (state.mod && e.modalidade.toLowerCase() !== state.mod) return false;
      if (state.periodo && e.periodoDemo !== state.periodo) return false;
      if (state.status && e.statusDemo !== state.status) return false;
      return true;
    }
    function sortFn(a, b) {
      if (state.ordem === "preco-asc") return a.preco - b.preco;
      if (state.ordem === "preco-desc") return b.preco - a.preco;
      if (state.ordem === "alfabetica") return a.titulo.localeCompare(b.titulo, "pt-BR");
      return (b.destaqueEvento ? 1 : 0) - (a.destaqueEvento ? 1 : 0);
    }

    var countEl = $("#v2-ev-count"), emptyEl = $("#v2-ev-empty");
    function render() {
      var list = EVENTOS.filter(matches).sort(sortFn);
      if (countEl) countEl.textContent = list.length + (list.length === 1 ? " evento encontrado" : " eventos encontrados");
      if (!list.length) { grid.innerHTML = ""; if (emptyEl) emptyEl.style.display = "block"; return; }
      if (emptyEl) emptyEl.style.display = "none";
      grid.innerHTML = list.map(cardHTML).join("");
    }

    var search = $("#v2-ev-search"); if (search) search.addEventListener("input", function () { state.q = this.value; render(); });
    var order = $("#v2-ev-order"); if (order) order.addEventListener("change", function () { state.ordem = this.value; render(); });
    $$('input[name="v2-ev-cat"]').forEach(function (r) { r.addEventListener("change", function () { state.cat = this.value; render(); }); });
    $$('input[name="v2-ev-mod"]').forEach(function (r) { r.addEventListener("change", function () { state.mod = this.value; render(); }); });
    $$('input[name="v2-ev-periodo"]').forEach(function (r) { r.addEventListener("change", function () { state.periodo = this.value; render(); }); });
    $$('input[name="v2-ev-status"]').forEach(function (r) { r.addEventListener("change", function () { state.status = this.value; render(); }); });
    $$("[data-clear-filters-e]").forEach(function (b) {
      b.addEventListener("click", function () {
        state = { q: "", cat: "", mod: "", periodo: "", status: "", ordem: "relevancia" };
        if (search) search.value = ""; if (order) order.value = "relevancia";
        $$('input[name="v2-ev-cat"],input[name="v2-ev-mod"],input[name="v2-ev-periodo"],input[name="v2-ev-status"]').forEach(function (r) { r.checked = r.value === ""; });
        render();
      });
    });

    var filters = $("#v2-ev-filters"), overlay = $("#v2-ev-overlay");
    function openF() { if (filters) filters.classList.add("is-open"); if (overlay) overlay.classList.add("is-open"); }
    function closeF() { if (filters) filters.classList.remove("is-open"); if (overlay) overlay.classList.remove("is-open"); }
    $$("[data-open-filters-e]").forEach(function (b) { b.addEventListener("click", openF); });
    $$("[data-close-filters-e]").forEach(function (b) { b.addEventListener("click", closeF); });
    if (overlay) overlay.addEventListener("click", closeF);
    document.addEventListener("keydown", function (e) { if ((e.key === "Escape" || e.key === "Esc") && filters && filters.classList.contains("is-open")) closeF(); });

    render();
  }

  function initCategoriasV2() {
    var rootEl = $("#v2-cat-root"); if (!rootEl) return;
    var INFO = window.V2_CATEGORIAS_INFO || [];
    var emptyEl = $("#v2-cat-empty"), crumb = $("#v2-cat-breadcrumb");
    function infoById(id) { for (var i = 0; i < INFO.length; i++) if (INFO[i].id === id) return INFO[i]; return null; }
    function countCursos(id) { return CURSOS.filter(function (it) { return it.tipo !== "evento" && v2CatId(it) === id; }).length; }
    function countEventos(id) { return CURSOS.filter(function (it) { return it.tipo === "evento" && v2CatId(it) === id; }).length; }

    var id = new URLSearchParams(location.search).get("id");

    if (!id) {
      rootEl.innerHTML =
        '<header class="v2-catalog-header"><h1 class="v2-h1">Encontre seu próximo caminho</h1><p class="v2-muted">Explore formações organizadas por áreas de conhecimento.</p></header>' +
        '<div class="v2-cats-grid">' + INFO.map(function (cat) {
          var nc = countCursos(cat.id), ne = countEventos(cat.id);
          return '<a class="v2-cathub" href="/v2/categorias/?id=' + encodeURIComponent(cat.id) + '">' +
            '<span class="v2-cathub-ic" style="background:linear-gradient(135deg,' + cat.g1 + ',' + cat.g2 + ');color:' + cat.cor + ';"><i class="ti ' + cat.icon + '"></i></span>' +
            '<b class="v2-cathub-nome">' + esc(cat.nome) + "</b>" +
            '<span class="v2-cathub-q">' + nc + (nc === 1 ? " curso" : " cursos") + " · " + ne + (ne === 1 ? " evento" : " eventos") + "</span>" +
            '<span class="v2-muted v2-sm v2-cathub-desc">' + esc(cat.descricao) + "</span>" +
            '<span class="v2-btn v2-btn-outline v2-btn-sm v2-cathub-cta">Explorar categoria <i class="ti ti-arrow-right"></i></span>' +
          "</a>";
        }).join("") + "</div>";
      return;
    }

    var info = infoById(id);
    if (!info) {
      rootEl.innerHTML = "";
      if (emptyEl) emptyEl.style.display = "block";
      document.title = "Categoria não encontrada — Desbloqueia Cursos";
      if (crumb) crumb.innerHTML = '<a href="/v2/">Início</a><i class="ti ti-chevron-right" aria-hidden="true"></i><a href="/v2/categorias/">Categorias</a>';
      return;
    }

    document.title = info.nome + " — Categorias";
    if (crumb) crumb.innerHTML = '<a href="/v2/">Início</a><i class="ti ti-chevron-right" aria-hidden="true"></i><a href="/v2/categorias/">Categorias</a><i class="ti ti-chevron-right" aria-hidden="true"></i><span aria-current="page">' + esc(info.nome) + "</span>";

    var all = CURSOS.filter(function (it) { return v2CatId(it) === id; });
    var state = { tipo: "todos", q: "" };

    function itemCard(it) {
      var c = catStyle(it.categoria), ev = it.tipo === "evento";
      var sub = ev ? (it.dataInicioDemo ? ("Evento · " + it.dataInicioDemo) : "Evento") : ((it.cargaHoraria ? it.cargaHoraria + "h" : "") + (it.modalidade ? " · " + it.modalidade : ""));
      var badge = ev ? '<span class="v2-badge v2-badge-destaque">Evento</span>' : '<span class="v2-badge v2-badge-mod">Curso</span>';
      return '<a class="v2-card" href="/v2/curso/?id=' + encodeURIComponent(ev ? (it.cursoBase || it.id) : it.id) + '" aria-label="' + esc(it.titulo) + '">' +
        '<div class="v2-thumb" style="background:linear-gradient(135deg,' + c.g1 + ',' + c.g2 + ');"><div class="v2-thumb-badges">' + badge + "</div><i class=\"ti " + c.icon + '" style="color:' + c.cor + ';"></i></div>' +
        '<div class="v2-card-body"><span class="v2-card-cat">' + esc(it.categoria) + "</span><h3 class=\"v2-card-title\">" + esc(it.titulo) + "</h3>" +
          '<div class="v2-card-meta"><span>' + esc(sub) + "</span></div>" +
          '<div class="v2-card-foot"><span class="v2-price">' + BRL(it.preco) + '</span><span class="v2-btn v2-btn-outline v2-btn-sm">Ver detalhes</span></div>' +
        "</div></a>";
    }

    rootEl.innerHTML =
      '<header class="v2-cat-detail-head">' +
        '<span class="v2-cathub-ic v2-cat-detail-ic" style="background:linear-gradient(135deg,' + info.g1 + ',' + info.g2 + ');color:' + info.cor + ';"><i class="ti ' + info.icon + '"></i></span>' +
        '<div class="v2-cat-detail-text"><h1 class="v2-h1">' + esc(info.nome) + '</h1><p class="v2-muted">' + esc(info.descricao) + "</p></div>" +
      "</header>" +
      '<div class="v2-search-wrap v2-cat-search"><i class="ti ti-search"></i><input type="search" id="v2-cat-q" class="v2-input v2-search-input" placeholder="Buscar em ' + esc(info.nome) + '…" aria-label="Buscar nesta categoria" autocomplete="off"></div>' +
      '<div class="v2-chips v2-chips-wrap" id="v2-cat-chips" role="list" aria-label="Tipo de conteúdo">' +
        '<button type="button" class="v2-chip is-on" data-tipo="todos">Todos</button>' +
        '<button type="button" class="v2-chip" data-tipo="curso">Cursos</button>' +
        '<button type="button" class="v2-chip" data-tipo="evento">Eventos</button>' +
      "</div>" +
      '<span class="v2-muted v2-sm" id="v2-cat-count" aria-live="polite" style="display:block;margin:6px 0 12px;"></span>' +
      '<div class="v2-grid" id="v2-cat-grid"></div>' +
      '<div class="v2-empty" id="v2-cat-list-empty" style="display:none;"><i class="ti ti-search-off"></i><p>Nenhum item nesta categoria para o filtro atual.</p></div>';

    var grid = $("#v2-cat-grid"), countEl = $("#v2-cat-count"), listEmpty = $("#v2-cat-list-empty"), chips = $("#v2-cat-chips"), q = $("#v2-cat-q");
    function render() {
      var list = all.filter(function (it) {
        if (state.tipo === "curso" && it.tipo === "evento") return false;
        if (state.tipo === "evento" && it.tipo !== "evento") return false;
        if (state.q) { var hay = (it.titulo + " " + it.categoria + " " + (it.resumo || it.descricaoCurta || "")).toLowerCase(); if (hay.indexOf(state.q.toLowerCase()) === -1) return false; }
        return true;
      });
      if (countEl) countEl.textContent = list.length + (list.length === 1 ? " item encontrado" : " itens encontrados");
      if (!list.length) { grid.innerHTML = ""; if (listEmpty) listEmpty.style.display = "block"; return; }
      if (listEmpty) listEmpty.style.display = "none";
      grid.innerHTML = list.map(itemCard).join("");
    }
    if (chips) chips.addEventListener("click", function (e) {
      var b = e.target.closest(".v2-chip"); if (!b) return;
      state.tipo = b.getAttribute("data-tipo");
      $$(".v2-chip", chips).forEach(function (x) { x.classList.toggle("is-on", x === b); });
      render();
    });
    if (q) q.addEventListener("input", function () { state.q = this.value; render(); });
    render();
  }

  /* ============================================================
     PÁGINAS INSTITUCIONAIS / UTILITÁRIAS V2
     Contato (demo), Validação de certificado (demo), âncoras suaves.
     ============================================================ */
  function initContatoV2() {
    var form = $("#v2-contato-form"); if (!form) return;
    var fb = $("#ct-feedback");
    function f(id) { return $("#" + id); }
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var ok = true;
      [["ct-nome", "ct-f-nome", function (v) { return v.trim().length >= 3; }, "Informe seu nome."],
       ["ct-email", "ct-f-email", function (v) { return validEmail(v.trim()); }, "E-mail inválido."],
       ["ct-assunto", "ct-f-assunto", function (v) { return v.trim().length >= 2; }, "Informe o assunto."],
       ["ct-msg", "ct-f-msg", function (v) { return v.trim().length >= 10; }, "Escreva uma mensagem com ao menos 10 caracteres."]
      ].forEach(function (r) {
        var field = f(r[1]); var val = f(r[0]).value; clearError(field);
        if (!r[2](val)) { setError(field, r[3]); ok = false; }
      });
      var aceite = f("ct-aceite"), fa = f("ct-f-aceite"); if (fa) clearError(fa);
      if (aceite && !aceite.checked) { if (fa) setError(fa, "Confirme que entende que esta é uma demonstração."); ok = false; }
      if (!ok) { var ef = $(".has-error .v2-input, .has-error .v2-textarea", form); if (ef) ef.focus(); return; }
      if (fb) {
        fb.className = "v2-callout v2-callout-success";
        fb.style.display = "flex";
        fb.innerHTML = '<i class="ti ti-circle-check"></i><span><b>Mensagem simulada enviada com sucesso.</b> Nenhuma informação foi encaminhada ao sistema real.</span>';
      }
      form.reset();
    });
  }

  function initCertValidarV2() {
    var form = $("#v2-cert-form"); if (!form) return;
    var DEMO = "V2-DEMO-2026-001";
    var input = $("#cv-codigo"), fCod = $("#cv-f-codigo"), result = $("#cv-result");

    function renderValido() {
      result.innerHTML =
        '<div class="v2-cert-result is-ok">' +
          '<div class="v2-cert-result-head"><span class="v2-cert-seal"><i class="ti ti-rosette-discount-check-filled"></i></span>' +
            '<div><strong>Certificado demonstrativo validado</strong><p class="v2-muted v2-sm" style="margin:2px 0 0;">Selo de autenticidade demonstrativa da V2.</p></div></div>' +
          '<div class="v2-cert-result-grid">' +
            '<div><span class="v2-muted v2-sm">Participante</span><b>Ana Souza</b></div>' +
            '<div><span class="v2-muted v2-sm">Curso</span><b>Marketing Digital na Prática</b></div>' +
            '<div><span class="v2-muted v2-sm">Carga horária</span><b>20 horas</b></div>' +
            '<div><span class="v2-muted v2-sm">Emissão</span><b>Demonstração V2</b></div>' +
            '<div><span class="v2-muted v2-sm">Código</span><b>' + DEMO + '</b></div>' +
          "</div>" +
          '<a href="/v2/" class="v2-btn v2-btn-ghost"><i class="ti ti-arrow-left"></i> Voltar à demonstração</a>' +
        "</div>";
    }
    function renderNaoEncontrado() {
      result.innerHTML =
        '<div class="v2-cert-result is-empty">' +
          '<i class="ti ti-search-off"></i>' +
          '<p><b>Não localizamos esse código na demonstração.</b></p>' +
          '<p class="v2-muted v2-sm">A consulta oficial continua disponível somente na plataforma principal.</p>' +
        "</div>";
    }
    function validar(scroll) {
      var code = (input.value || "").trim().toUpperCase();
      if (fCod) clearError(fCod);
      if (!code) { if (fCod) setError(fCod, "Informe o código do certificado."); input.focus(); return; }
      result.style.display = "block";
      if (code === DEMO) renderValido(); else renderNaoEncontrado();
      if (scroll) result.scrollIntoView({ behavior: "smooth", block: "start" });
    }

    form.addEventListener("submit", function (e) { e.preventDefault(); validar(true); });

    var qcod = new URLSearchParams(location.search).get("codigo");
    if (qcod) { input.value = qcod; validar(false); }
  }

  function initAnchorsV2() {
    var scope = $(".v2-legal"); if (!scope) return;
    scope.addEventListener("click", function (e) {
      var a = e.target.closest('a[href^="#"]'); if (!a) return;
      var id = a.getAttribute("href").slice(1); if (!id) return;
      var target = document.getElementById(id); if (!target) return;
      e.preventDefault();
      target.scrollIntoView({ behavior: "smooth", block: "start" });
      target.setAttribute("tabindex", "-1");
      target.focus({ preventScroll: true });
      try { history.replaceState(null, "", location.pathname + "#" + id); } catch (err) {}
    });
  }

  /* ---------------- Filtros: bottom sheet -------------------- */
  function initFilterSheet() {
    var openers = $$("[data-open-filters]");
    var sheet = $(".v2-filters");
    var overlay = $(".v2-overlay");
    if (!sheet) return;
    function open() { sheet.classList.add("is-open"); if (overlay) overlay.classList.add("is-open"); }
    function close() { sheet.classList.remove("is-open"); if (overlay) overlay.classList.remove("is-open"); }
    openers.forEach(function (b) { b.addEventListener("click", open); });
    if (overlay) overlay.addEventListener("click", close);
    $$("[data-close-filters]").forEach(function (b) { b.addEventListener("click", close); });
  }

  /* ---------------- Toggle de senha -------------------------- */
  function initPassToggles() {
    $$("[data-toggle-pass]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        var inp = $(btn.getAttribute("data-toggle-pass"));
        if (!inp) return;
        var show = inp.type === "password";
        inp.type = show ? "text" : "password";
        var ic = btn.querySelector("i");
        if (ic) ic.className = show ? "ti ti-eye-off" : "ti ti-eye";
        btn.setAttribute("aria-label", show ? "Ocultar senha" : "Mostrar senha");
      });
    });
  }

  /* ---------------- Tabs genéricas (data-tab) ---------------- */
  function initTabs() {
    $$("[data-tabgroup]").forEach(function (group) {
      var gid = group.getAttribute("data-tabgroup");
      group.addEventListener("click", function (e) {
        var t = e.target.closest("[data-tab]"); if (!t) return;
        var target = t.getAttribute("data-tab");
        $$('[data-tabgroup="' + gid + '"] [data-tab]').forEach(function (x) { x.classList.remove("is-on"); });
        t.classList.add("is-on");
        $$('[data-pane-group="' + gid + '"]').forEach(function (p) { p.style.display = "none"; p.classList.remove("is-on"); });
        var pane = $('[data-pane="' + target + '"]'); if (pane) { pane.style.display = "block"; pane.classList.add("is-on"); }
      });
    });
  }

  /* ---------------- Accordion de módulos -------------------- */
  function initAccordion() {
    $$(".v2-mod-head").forEach(function (h) {
      h.addEventListener("click", function () { h.parentElement.classList.toggle("is-open"); });
    });
  }

  /* ---------------- LMS V2: progresso/conclusão (envio nativo) -----
     Apenas melhorias visuais sobre formulários POST reais (CSRF). Não usa
     fetch/XHR/API/localStorage e NÃO calcula progresso no cliente. O formulário
     funciona integralmente sem JavaScript (o botão é o fallback). */
  function initLmsV2() {
    // Foco no feedback real (sucesso/erro) após o redirect.
    var fb = $("#v2-aula-feedback");
    if (fb && fb.textContent && fb.textContent.trim() !== "") {
      try { fb.focus(); } catch (e) {}
    }

    // Prevenção de clique duplo + rótulo "Concluindo…" (sem bloquear o envio real).
    $$("form.v2-lms-complete, form.v2-lms-complete-form").forEach(function (form) {
      form.addEventListener("submit", function () {
        var btn = form.querySelector("[data-complete-btn]");
        if (!btn) return;
        if (btn.getAttribute("data-submitting") === "1") return;
        btn.setAttribute("data-submitting", "1");
        var label = btn.getAttribute("data-loading-label");
        if (label) { btn.textContent = label; }
        // Desabilita após o envio já ter sido capturado pelo navegador.
        setTimeout(function () { btn.setAttribute("disabled", "disabled"); }, 0);
      });
    });

    // Texto: leitura automática via envio nativo (POST → Redirect → GET).
    // Só existe para o item de texto atual ainda não concluído; idempotente no
    // servidor; após concluir, o formulário deixa de ser renderizado (sem loop).
    var auto = $("form[data-v2-autocomplete]");
    if (auto) {
      setTimeout(function () {
        if (typeof auto.requestSubmit === "function") { auto.requestSubmit(); }
        else { auto.submit(); }
      }, 150);
    }
  }

  /* ---------------- QUIZ V2 (Fase 2.9) ----------------------- */
  // UX visual apenas: foco no feedback real após o redirect e prevenção de
  // clique duplo no envio NATIVO. Nenhuma correção, nota ou estado de quiz é
  // calculado no cliente; o formulário continua funcional sem JavaScript.
  function initQuizV2() {
    var fb = $("#v2-quiz-feedback");
    if (fb && fb.textContent && fb.textContent.trim() !== "") {
      try { fb.focus(); } catch (e) {}
    }

    $$("form.v2-quiz-form").forEach(function (form) {
      form.addEventListener("submit", function () {
        var btn = form.querySelector("[data-quiz-btn]");
        if (!btn) return;
        if (btn.getAttribute("data-submitting") === "1") return;
        btn.setAttribute("data-submitting", "1");
        var label = btn.getAttribute("data-loading-label");
        if (label) { btn.textContent = label; }
        // Desabilita só após o envio nativo já ter sido capturado pelo navegador.
        setTimeout(function () { btn.setAttribute("disabled", "disabled"); }, 0);
      });
    });

    initQuizWizardV2();
    initQuizCronometroV2();
    // O autosave vem antes da prova: ela usa o v2QuizSalvarAgora que ele expoe.
    initQuizAutosaveV2();
    initQuizProvaV2();
  }

  // Descarrega imediatamente o que estiver na fila do autosave. Fica exposto
  // aqui porque a navegacao da prova (avancar/voltar/pular) precisa gravar
  // antes de trocar de questao, sem esperar o debounce.
  var v2QuizSalvarAgora = null;

  // Salva a resposta assim que o aluno responde, para que sair da pagina (ou
  // perder a conexao) nunca custe o trabalho ja feito. Envia so o que mudou;
  // a correcao continua inteiramente no servidor.
  function initQuizAutosaveV2() {
    var form = $("#v2-quiz-answer-form");
    if (!form) { return; }

    var campoTentativa = form.querySelector('input[name="tentativa_id"]');
    var tentativaId = campoTentativa ? parseInt(campoTentativa.value, 10) : 0;
    if (!tentativaId) { return; }

    var aviso = $("#v2-quiz-autosave");
    var pendentes = { respostas: {}, discursivas: {} };
    var timer = null;
    var enviando = false;
    var textoPadrao = aviso ? aviso.textContent : "";

    function valorDe(nome) {
      var campo = form.querySelector('input[name="' + nome + '"]');
      return campo ? campo.value : "";
    }

    function mostrar(texto, erro) {
      if (!aviso) { return; }
      aviso.textContent = texto;
      aviso.style.color = erro ? "#dc2626" : "";
    }

    function vazio(obj) {
      for (var k in obj) { if (Object.prototype.hasOwnProperty.call(obj, k)) { return false; } }
      return true;
    }

    function devolver(destino, origem) {
      for (var k in origem) {
        if (Object.prototype.hasOwnProperty.call(origem, k) && !Object.prototype.hasOwnProperty.call(destino, k)) {
          destino[k] = origem[k];
        }
      }
    }

    function enviarPendentes(aoSair) {
      if (enviando) { return; }
      if (vazio(pendentes.respostas) && vazio(pendentes.discursivas)) { return; }

      var lote = pendentes;
      pendentes = { respostas: {}, discursivas: {} };
      enviando = true;
      mostrar("Salvando…");

      var opcoes = {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          tentativa_id: tentativaId,
          item_id: parseInt(valorDe("item_id"), 10) || 0,
          inscricao_id: parseInt(valorDe("inscricao_id"), 10) || 0,
          curso_id: parseInt(valorDe("curso_id"), 10) || 0,
          turma_id: parseInt(valorDe("turma_id"), 10) || 0,
          respostas: lote.respostas,
          discursivas: lote.discursivas,
          _token: valorDe("_token")
        })
      };
      // keepalive garante o envio mesmo se a aba estiver sendo fechada.
      if (aoSair) { opcoes.keepalive = true; }

      fetch("/aluno/cursos/quiz/rascunho", opcoes)
        .then(function (r) { return r.json(); })
        .then(function (data) {
          enviando = false;
          if (data && data.ok) {
            mostrar("Respostas salvas");
            return;
          }
          if (data && data.expirada) { window.location.reload(); return; }
          // Falhou: devolve para a fila e tenta de novo na proxima alteracao.
          devolver(pendentes.respostas, lote.respostas);
          devolver(pendentes.discursivas, lote.discursivas);
          mostrar("Não foi possível salvar agora. Tentaremos de novo.", true);
        })
        ["catch"](function () {
          enviando = false;
          devolver(pendentes.respostas, lote.respostas);
          devolver(pendentes.discursivas, lote.discursivas);
          mostrar("Sem conexão. Suas respostas serão salvas assim que voltar.", true);
        });
    }

    function agendar(atraso) {
      if (timer) { clearTimeout(timer); }
      timer = setTimeout(function () { enviarPendentes(false); }, atraso);
    }

    v2QuizSalvarAgora = function () {
      if (timer) { clearTimeout(timer); timer = null; }
      enviarPendentes(false);
    };

    // Objetivas: salva quase imediatamente apos a escolha.
    form.addEventListener("change", function (evento) {
      var alvo = evento.target;
      if (!alvo || alvo.type !== "radio") { return; }
      var m = String(alvo.name || "").match(/^respostas\[(\d+)\]$/);
      if (!m) { return; }
      pendentes.respostas[m[1]] = alvo.value;
      agendar(600);
    });

    // Discursiva: espera a digitacao parar para nao salvar a cada tecla.
    form.addEventListener("input", function (evento) {
      var alvo = evento.target;
      if (!alvo || String(alvo.tagName).toLowerCase() !== "textarea") { return; }
      var m = String(alvo.name || "").match(/^discursivas\[(\d+)\]$/);
      if (!m) { return; }
      pendentes.discursivas[m[1]] = alvo.value;
      agendar(2000);
    });

    // Sair da aba, minimizar ou fechar: grava o que ainda estiver pendente.
    document.addEventListener("visibilitychange", function () {
      if (document.hidden) { enviarPendentes(true); }
    });
    window.addEventListener("pagehide", function () { enviarPendentes(true); });

    // No envio final nao ha o que avisar: o proprio POST leva tudo.
    form.addEventListener("submit", function () {
      if (timer) { clearTimeout(timer); }
      mostrar(textoPadrao);
    });
  }

  // Cronometro do simulado. O prazo e do servidor: aqui so exibimos a
  // contagem e, ao zerar, avisamos o servidor para aplicar a regra
  // configurada (envio automatico do que estiver salvo).
  function initQuizCronometroV2() {
    var box = $("#v2-quiz-cronometro");
    if (!box) { return; }

    var valor = $("#v2-quiz-cronometro-valor");
    var form = $("#v2-quiz-answer-form");
    if (!valor || !form) { return; }

    var restante = parseInt(box.getAttribute("data-restante"), 10) || 0;
    var tentativaId = parseInt(box.getAttribute("data-tentativa"), 10) || 0;
    var encerrando = false;

    function token() {
      var campo = form.querySelector('input[name="_token"], input[name="csrf_token"]');
      return campo ? campo.value : "";
    }

    function formatar(s) {
      if (s < 0) { s = 0; }
      var h = Math.floor(s / 3600);
      var m = Math.floor((s % 3600) / 60);
      var seg = s % 60;
      return [h, m, seg].map(function (n) { return String(n).padStart(2, "0"); }).join(":");
    }

    function conferirNoServidor() {
      return fetch("/aluno/cursos/quiz/tempo", {
        method: "POST",
        headers: { "Content-Type": "application/json", "X-CSRF-TOKEN": token() },
        body: JSON.stringify({ tentativa_id: tentativaId, _token: token() })
      }).then(function (r) { return r.json(); });
    }

    function encerrarPorTempo() {
      if (encerrando) { return; }
      encerrando = true;
      valor.textContent = "00:00:00";
      conferirNoServidor()["catch"](function () {})
        .then(function () {
          alert("O tempo da prova terminou. Suas respostas salvas foram enviadas automaticamente.");
          window.location.reload();
        });
    }

    valor.textContent = formatar(restante);
    setInterval(function () {
      if (encerrando) { return; }
      restante--;
      valor.textContent = formatar(restante);
      if (restante <= 300) { box.style.borderColor = "#dc2626"; }
      if (restante <= 0) { encerrarPorTempo(); }
    }, 1000);

    // O relogio do navegador e apenas visual: a cada 60s o servidor confirma
    // o tempo real restante e informa se a tentativa ja foi encerrada.
    setInterval(function () {
      if (encerrando) { return; }
      conferirNoServidor().then(function (data) {
        if (!data || !data.ok) { return; }
        if (data.encerrada) {
          encerrando = true;
          window.location.reload();
          return;
        }
        if (data.tempo && typeof data.tempo.segundos_restantes === "number") {
          restante = data.tempo.segundos_restantes;
        }
      })["catch"](function () {});
    }, 60000);
  }

  // Uma pergunta por vez (progressive enhancement puro): sem JS, o form
  // continua com todas as perguntas visíveis e envia normalmente. Nenhuma
  // correção/nota é calculada no cliente; só esconde/mostra fieldsets.
  function initQuizWizardV2() {
    var perguntasWrap = $("#v2-quiz-perguntas");
    var progress = $("#v2-quiz-progress");
    var progressFill = $("#v2-quiz-progress-fill");
    var progressText = $("#v2-quiz-progress-text");
    var dotsWrap = $("#v2-quiz-progress-dots");
    var prevBtn = $("#v2-quiz-prev-btn");
    var nextBtn = $("#v2-quiz-next-btn");
    var submitBtn = $("#v2-quiz-submit-btn");
    if (!perguntasWrap || !progress || !prevBtn || !nextBtn) { return; }

    // Simulado por blocos (prova longa com discursiva): a navegacao e por
    // rolagem, com os titulos de bloco servindo de guia. Uma questao por vez
    // atrapalharia numa prova de 81 questoes.
    if (perguntasWrap.getAttribute("data-modo-prova") === "1") { return; }

    var steps = $$(".v2-quiz-pergunta", perguntasWrap);
    if (steps.length <= 1) { return; }

    function isAnswered(step) {
      if (step.getAttribute("data-tipo") === "discursiva") {
        var area = step.querySelector("textarea");
        return !!(area && area.value.trim().length >= 3);
      }
      return !!step.querySelector('input[type="radio"]:checked');
    }

    var current = steps.length - 1;
    for (var i = 0; i < steps.length; i++) {
      if (!isAnswered(steps[i])) { current = i; break; }
    }
    var maxReached = current;

    var dots = steps.map(function (step, i) {
      var dot = document.createElement("button");
      dot.type = "button";
      dot.className = "v2-quiz-progress__dot";
      dot.textContent = String(i + 1);
      dot.setAttribute("aria-label", "Ir para a pergunta " + (i + 1));
      dot.addEventListener("click", function () {
        if (i <= maxReached) {
          current = i;
          render();
        }
      });
      dotsWrap.appendChild(dot);
      return dot;
    });

    function render() {
      steps.forEach(function (step, i) {
        step.style.display = i === current ? "" : "none";
      });
      progressFill.style.width = (((current + 1) / steps.length) * 100) + "%";
      progressText.textContent = "Pergunta " + (current + 1) + " de " + steps.length;
      dots.forEach(function (dot, i) {
        dot.disabled = i > maxReached;
        dot.classList.toggle("is-current", i === current);
        dot.classList.toggle("is-answered", i !== current && isAnswered(steps[i]));
      });
      // .v2-btn define display:inline-flex no proprio seletor de classe, que
      // sobrescreve o [hidden] nativo do navegador (CSS de autor vence CSS de
      // user-agent mesmo com especificidade igual) - por isso alterna via
      // style.display em vez da propriedade hidden nesses botoes especificos.
      prevBtn.style.display = current === 0 ? "none" : "";
      var isLast = current === steps.length - 1;
      nextBtn.style.display = isLast ? "none" : "";
      if (submitBtn) { submitBtn.style.display = isLast ? "" : "none"; }
    }

    prevBtn.addEventListener("click", function () {
      if (current > 0) {
        current--;
        render();
      }
    });

    nextBtn.addEventListener("click", function () {
      if (!isAnswered(steps[current])) {
        alert("Selecione uma alternativa antes de continuar.");
        return;
      }
      if (current < steps.length - 1) {
        current++;
        if (current > maxReached) { maxReached = current; }
        render();
      }
    });

    progress.hidden = false;
    prevBtn.hidden = false;
    nextBtn.hidden = false;
    render();
  }

  // Simulado: uma questao por vez, navegacao livre (pode pular e voltar, como
  // em prova de verdade), gravacao a cada troca de questao e conferencia antes
  // do envio. Progressive enhancement: sem JS a prova continua rolavel e o
  // envio direto segue funcionando.
  function initQuizProvaV2() {
    var wrap = $("#v2-quiz-perguntas");
    if (!wrap || wrap.getAttribute("data-modo-prova") !== "1") { return; }

    var nav = $("#v2-quiz-prova-nav");
    var painel = $("#v2-quiz-prova-revisao");
    var form = $("#v2-quiz-answer-form");
    if (!nav || !painel || !form) { return; }

    var steps = $$(".v2-quiz-pergunta", wrap);
    if (steps.length <= 1) { return; }

    var elBloco = $("#v2-quiz-prova-bloco");
    var elPos = $("#v2-quiz-prova-pos");
    var elFill = $("#v2-quiz-prova-fill");
    var elResumo = $("#v2-quiz-prova-resumo");
    var indice = $("#v2-quiz-prova-indice");
    var indiceBtn = $("#v2-quiz-prova-indice-btn");
    var prevBtn = $("#v2-quiz-prova-prev-btn");
    var nextBtn = $("#v2-quiz-prova-next-btn");
    var revisarBtn = $("#v2-quiz-prova-revisar-btn");
    var voltarBtn = $("#v2-quiz-prova-voltar-btn");
    var submitGenerico = $("#v2-quiz-submit-btn");
    var resumoRevisao = $("#v2-quiz-prova-revisao-resumo");
    var listasRevisao = $("#v2-quiz-prova-revisao-listas");

    // Os titulos de bloco impressos entre as questoes so fazem sentido na
    // rolagem; aqui o bloco aparece no cabecalho da navegacao.
    $$(".v2-quiz-bloco-titulo", wrap).forEach(function (h) { h.style.display = "none"; });

    var atual = 0;
    var emRevisao = false;

    function respondida(step) {
      if (step.getAttribute("data-tipo") === "discursiva") {
        var area = step.querySelector("textarea");
        return !!(area && area.value.trim().length >= 3);
      }
      return !!step.querySelector('input[type="radio"]:checked');
    }

    function marcada(step) { return step.getAttribute("data-revisao") === "1"; }

    function contar() {
      var r = 0, m = 0, faltaDiscursiva = false;
      steps.forEach(function (s) {
        if (respondida(s)) { r++; } else if (s.getAttribute("data-tipo") === "discursiva") { faltaDiscursiva = true; }
        if (marcada(s)) { m++; }
      });
      return { respondidas: r, pendentes: steps.length - r, marcadas: m, faltaDiscursiva: faltaDiscursiva };
    }

    function valorDe(nome) {
      var campo = form.querySelector('input[name="' + nome + '"]');
      return campo ? campo.value : "";
    }

    // Grava o que estiver pendente antes de trocar de questao. Sem isso, o
    // aluno que responde e avanca rapido dependeria do debounce de 600ms.
    function gravarPendencias() {
      if (typeof v2QuizSalvarAgora === "function") {
        try { v2QuizSalvarAgora(); } catch (e) {}
      }
    }

    var botoesIndice = [];

    function montarIndice() {
      var blocoCorrente = null;
      var grade = null;
      steps.forEach(function (step, i) {
        var codigo = step.getAttribute("data-bloco") || "";
        if (codigo !== blocoCorrente) {
          blocoCorrente = codigo;
          var titulo = document.createElement("p");
          titulo.className = "v2-quiz-prova-indice__titulo";
          titulo.textContent = step.getAttribute("data-bloco-titulo") || "Questões";
          indice.appendChild(titulo);
          grade = document.createElement("div");
          grade.className = "v2-quiz-prova-indice__grade";
          indice.appendChild(grade);
        }
        var b = document.createElement("button");
        b.type = "button";
        b.className = "v2-quiz-prova-indice__item";
        b.textContent = String(i + 1);
        b.addEventListener("click", function () { irPara(i); });
        grade.appendChild(b);
        botoesIndice.push(b);
      });

      var legenda = document.createElement("p");
      legenda.className = "v2-muted v2-sm v2-quiz-prova-indice__legenda";
      legenda.textContent = "Cheia: respondida · Vazia: pendente · Bandeira: marcada para revisão";
      indice.appendChild(legenda);
    }

    function pintarIndice() {
      botoesIndice.forEach(function (b, i) {
        b.classList.toggle("is-answered", respondida(steps[i]));
        b.classList.toggle("is-flagged", marcada(steps[i]));
        b.classList.toggle("is-current", i === atual && !emRevisao);
        b.setAttribute("aria-label", "Questão " + (i + 1) + (respondida(steps[i]) ? ", respondida" : ", pendente") + (marcada(steps[i]) ? ", marcada para revisão" : ""));
      });
    }

    function render() {
      steps.forEach(function (s, i) { s.style.display = (!emRevisao && i === atual) ? "" : "none"; });

      var c = contar();
      if (elResumo) {
        elResumo.textContent = c.respondidas + " de " + steps.length + " respondidas"
          + (c.pendentes ? " · " + c.pendentes + " pendente" + (c.pendentes > 1 ? "s" : "") : "")
          + (c.marcadas ? " · " + c.marcadas + " marcada" + (c.marcadas > 1 ? "s" : "") : "");
      }
      if (elFill) { elFill.style.width = ((c.respondidas / steps.length) * 100) + "%"; }

      if (!emRevisao) {
        var step = steps[atual];
        if (elBloco) { elBloco.textContent = step.getAttribute("data-bloco-titulo") || ""; }
        if (elPos) { elPos.textContent = "Questão " + (atual + 1) + " de " + steps.length; }
      }

      // .v2-btn usa display:inline-flex, que vence o [hidden] do navegador;
      // por isso a alternancia e por style.display nestes botoes.
      if (prevBtn) { prevBtn.style.display = (emRevisao || atual === 0) ? "none" : ""; }
      if (nextBtn) { nextBtn.style.display = (emRevisao || atual >= steps.length - 1) ? "none" : ""; }
      if (revisarBtn) { revisarBtn.style.display = emRevisao ? "none" : ""; }
      if (submitGenerico) { submitGenerico.style.display = "none"; }

      nav.hidden = emRevisao;
      painel.hidden = !emRevisao;
      pintarIndice();
    }

    function irPara(i) {
      if (i < 0 || i >= steps.length) { return; }
      gravarPendencias();
      atual = i;
      emRevisao = false;
      if (indice) { indice.hidden = true; }
      if (indiceBtn) { indiceBtn.setAttribute("aria-expanded", "false"); }
      render();
      nav.scrollIntoView({ block: "start" });
      var foco = steps[i].querySelector('input[type="radio"], textarea');
      if (foco) { foco.focus({ preventScroll: true }); }
    }

    function linhaQuestao(i) {
      var b = document.createElement("button");
      b.type = "button";
      b.className = "v2-btn v2-btn-ghost v2-btn-sm";
      b.style.margin = "0 6px 6px 0";
      b.textContent = "Questão " + (i + 1);
      b.addEventListener("click", function () { irPara(i); });
      return b;
    }

    function abrirRevisao() {
      gravarPendencias();
      emRevisao = true;

      var c = contar();
      resumoRevisao.innerHTML = "";
      var p = document.createElement("p");
      p.style.margin = "0 0 10px";
      p.innerHTML = "<strong>" + c.respondidas + " de " + steps.length + "</strong> questões respondidas.";
      resumoRevisao.appendChild(p);

      if (c.faltaDiscursiva) {
        var aviso = document.createElement("p");
        aviso.className = "v2-callout v2-callout-danger";
        aviso.setAttribute("role", "alert");
        aviso.textContent = "A questão discursiva é obrigatória para o envio. Responda antes de enviar a prova.";
        resumoRevisao.appendChild(aviso);
      } else if (c.pendentes > 0) {
        var alerta = document.createElement("p");
        alerta.className = "v2-callout v2-callout-warning";
        alerta.setAttribute("role", "status");
        alerta.textContent = "Você ainda tem " + c.pendentes + " questão(ões) sem resposta. Questão em branco conta como erro.";
        resumoRevisao.appendChild(alerta);
      }

      listasRevisao.innerHTML = "";
      var pendentes = [], marcadas = [];
      steps.forEach(function (s, i) {
        if (!respondida(s)) { pendentes.push(i); }
        if (marcada(s)) { marcadas.push(i); }
      });

      [["Sem resposta", pendentes], ["Marcadas para revisão", marcadas]].forEach(function (par) {
        if (!par[1].length) { return; }
        var t = document.createElement("p");
        t.style.cssText = "font-weight:700;margin:12px 0 6px;";
        t.textContent = par[0] + " (" + par[1].length + ")";
        listasRevisao.appendChild(t);
        var box = document.createElement("div");
        par[1].forEach(function (i) { box.appendChild(linhaQuestao(i)); });
        listasRevisao.appendChild(box);
      });

      if (!pendentes.length && !marcadas.length) {
        var ok = document.createElement("p");
        ok.className = "v2-callout v2-callout-success";
        ok.setAttribute("role", "status");
        ok.textContent = "Tudo respondido e nada marcado para revisão.";
        listasRevisao.appendChild(ok);
      }

      render();
      painel.scrollIntoView({ block: "start" });
    }

    // Marcar para revisao usa a rota que ja existe; o estado fica no servidor,
    // entao sobrevive a recarregar a pagina ou trocar de aparelho.
    function alternarMarcacao(step, botao) {
      var novo = !marcada(step);
      step.setAttribute("data-revisao", novo ? "1" : "0");
      botao.setAttribute("aria-pressed", novo ? "true" : "false");
      var texto = botao.querySelector(".v2-quiz-flag__texto");
      if (texto) { texto.textContent = novo ? "Marcada para revisão" : "Marcar para revisão"; }
      pintarIndice();
      render();

      fetch("/aluno/cursos/quiz/revisao", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          tentativa_id: parseInt(valorDe("tentativa_id"), 10) || 0,
          pergunta_id: parseInt(step.getAttribute("data-pergunta-id"), 10) || 0,
          marcada: novo ? 1 : 0,
          _token: valorDe("_token")
        })
      })["catch"](function () {
        // A marcacao e auxiliar: se a rede falhar, o valor local continua
        // valendo para esta sessao e nao vale interromper a prova por isso.
      });
    }

    steps.forEach(function (step) {
      var botao = step.querySelector("[data-flag-pergunta]");
      if (!botao) { return; }
      botao.hidden = false;
      if (marcada(step)) {
        var t = botao.querySelector(".v2-quiz-flag__texto");
        if (t) { t.textContent = "Marcada para revisão"; }
      }
      botao.addEventListener("click", function () { alternarMarcacao(step, botao); });
    });

    form.addEventListener("change", function () { render(); });
    form.addEventListener("input", function (e) {
      if (e.target && String(e.target.tagName).toLowerCase() === "textarea") { render(); }
    });

    if (prevBtn) { prevBtn.addEventListener("click", function () { irPara(atual - 1); }); }
    if (nextBtn) { nextBtn.addEventListener("click", function () { irPara(atual + 1); }); }
    if (revisarBtn) { revisarBtn.addEventListener("click", abrirRevisao); }
    if (voltarBtn) { voltarBtn.addEventListener("click", function () { irPara(atual); }); }

    if (indiceBtn) {
      indiceBtn.addEventListener("click", function () {
        var abrir = indice.hidden;
        indice.hidden = !abrir;
        indiceBtn.setAttribute("aria-expanded", abrir ? "true" : "false");
      });
    }

    // O handler generico de "Enviando…" roda antes deste (foi registrado
    // antes) e ja marcou o botao como enviando, agendando o disabled. Quando
    // barramos o envio aqui, e preciso desfazer isso, senao o aluno fica com
    // um botao morto escrito "Enviando…". O setTimeout garante a ordem: entra
    // na fila depois do deles.
    var botoesEnvio = $$("[data-quiz-btn]", form);
    botoesEnvio.forEach(function (b) { b.setAttribute("data-rotulo-original", b.innerHTML); });

    function liberarBotoesEnvio() {
      botoesEnvio.forEach(function (b) {
        setTimeout(function () {
          b.removeAttribute("data-submitting");
          b.removeAttribute("disabled");
          var original = b.getAttribute("data-rotulo-original");
          if (original) { b.innerHTML = original; }
        }, 0);
      });
    }

    // A discursiva em branco faz o servidor recusar o envio; avisar aqui evita
    // que o aluno descubra isso so depois de clicar em enviar.
    form.addEventListener("submit", function (evento) {
      var c = contar();
      if (c.faltaDiscursiva) {
        evento.preventDefault();
        liberarBotoesEnvio();
        abrirRevisao();
        return;
      }
      if (c.pendentes > 0 && !window.confirm("Você tem " + c.pendentes + " questão(ões) sem resposta. Enviar mesmo assim?")) {
        evento.preventDefault();
        liberarBotoesEnvio();
      }
    });

    montarIndice();

    // Abre na primeira pendente: quem volta de uma sessao interrompida
    // reencontra a prova onde parou, e nao no comeco.
    for (var i = 0; i < steps.length; i++) {
      if (!respondida(steps[i])) { atual = i; break; }
    }

    nav.hidden = false;
    if (prevBtn) { prevBtn.hidden = false; }
    if (nextBtn) { nextBtn.hidden = false; }
    if (revisarBtn) { revisarBtn.hidden = false; }
    render();
  }

  /* ---------------- ATIVIDADE DISCURSIVA V2 (Fase 2.10) ------ */
  // UX apenas: contador visual de caracteres, prevenção de clique duplo no
  // envio NATIVO e foco no feedback. Sem rascunho local, sem storage, sem AJAX.
  // A atividade continua funcional sem JavaScript.
  function initAtividadeV2() {
    var fb = $("#v2-atividade-feedback");
    if (fb && fb.textContent && fb.textContent.trim() !== "") {
      try { fb.focus(); } catch (e) {}
    }

    $$("textarea[data-char-counter]").forEach(function (ta) {
      var form = ta.form;
      var out = form ? form.querySelector("[data-char-count]") : null;
      if (!out) return;
      var upd = function () { out.textContent = String(ta.value.length); };
      upd();
      ta.addEventListener("input", upd);
    });

    // Validação client-side do limite de 5 imagens (o servidor valida de
    // novo - isso é só UX, evita o aluno preencher tudo e só descobrir o
    // limite depois do POST).
    var MAX_IMAGENS = 5;
    $$('input[type="file"][name="imagens[]"]').forEach(function (input) {
      var erro = document.getElementById(input.getAttribute("id") + "-erro") || $("[data-imagens-erro]", input.form);
      input.addEventListener("change", function () {
        if (input.files && input.files.length > MAX_IMAGENS) {
          if (erro) {
            erro.textContent = "Selecione no máximo " + MAX_IMAGENS + " imagens.";
            erro.hidden = false;
          }
          input.value = "";
        } else if (erro) {
          erro.hidden = true;
        }
      });
    });

    $$("form.v2-atv-form").forEach(function (form) {
      form.addEventListener("submit", function () {
        var btn = form.querySelector("[data-atv-btn]");
        if (!btn) return;
        if (btn.getAttribute("data-submitting") === "1") return;
        btn.setAttribute("data-submitting", "1");
        var label = btn.getAttribute("data-loading-label");
        if (label) { btn.textContent = label; }
        setTimeout(function () { btn.setAttribute("disabled", "disabled"); }, 0);
      });
    });
  }

  /* ---------------- VALIDAÇÃO DE CERTIFICADO V2 (Fase 2.11) -- */
  // UX apenas: foco acessível no resultado/erro após o POST → Redirect → GET e
  // prevenção de clique duplo no envio NATIVO. Sem fetch/AJAX, sem dados
  // demonstrativos. A validação real acontece 100% no backend. Seletores
  // próprios (não colidem com o validador demonstrativo `initCertValidarV2`).
  function initCertValidacaoV2() {
    var foco = $("#v2-cert-resultado") || $("#v2-cert-erro");
    if (foco) { try { foco.focus(); } catch (e) {} }

    var form = $("#v2-cert-validacao-form");
    if (!form) return;
    form.addEventListener("submit", function () {
      var btn = form.querySelector("[data-cert-btn]");
      if (!btn) return;
      if (btn.getAttribute("data-submitting") === "1") return;
      btn.setAttribute("data-submitting", "1");
      var label = btn.getAttribute("data-loading-label");
      if (label) { btn.textContent = label; }
      setTimeout(function () { btn.setAttribute("disabled", "disabled"); }, 0);
    });
  }

  /* ---------------- CHECKOUT V2 PRÉ-PAGAMENTO (Fase 2.12A) --- */
  // UX apenas: foco no feedback após PRG e prevenção de clique duplo no envio
  // NATIVO. Sem cálculo de preço, sem persistência (storage), sem POST por JS.
  // As telas continuam funcionais sem JavaScript. Seletores próprios (não
  // colidem com o checkout demonstrativo `initCheckoutV2`/`initCheckout`).
  function initCheckoutPreV2() {
    var fb = $("#v2-checkout-feedback");
    if (fb && fb.textContent && fb.textContent.trim() !== "") {
      try { fb.focus(); } catch (e) {}
    }

    $$("form.v2-checkout-form").forEach(function (form) {
      form.addEventListener("submit", function () {
        var btn = form.querySelector("[data-checkout-btn]");
        if (!btn) return;
        if (btn.getAttribute("data-submitting") === "1") return;
        btn.setAttribute("data-submitting", "1");
        var label = btn.getAttribute("data-loading-label");
        if (label) { btn.textContent = label; }
        setTimeout(function () { btn.setAttribute("disabled", "disabled"); }, 0);
      });
    });
  }

  /* ---------------- LOGIN / CADASTRO ------------------------- */
  function validEmail(v) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v); }
  function validCPF(v) { return /^\d{3}\.?\d{3}\.?\d{3}-?\d{2}$/.test(v.replace(/\s/g, "")); }

  function setError(field, msg) {
    field.classList.add("has-error");
    var e = field.querySelector(".v2-field-error"); if (e) e.textContent = msg;
  }
  function clearError(field) { field.classList.remove("has-error"); }

  function initLogin() {
    var form = $("#v2-login-form"); if (!form) return;
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var ok = true;
      var fLogin = $("#f-login"), fSenha = $("#f-senha");
      clearError(fLogin); clearError(fSenha);
      var login = $("#login").value.trim();
      var senha = $("#senha").value;
      if (!login) { setError(fLogin, "Informe seu e-mail ou CPF."); ok = false; }
      else if (login.indexOf("@") >= 0 && !validEmail(login)) { setError(fLogin, "E-mail inválido."); ok = false; }
      if (senha.length < 6) { setError(fSenha, "A senha precisa de ao menos 6 caracteres."); ok = false; }
      if (!ok) return;
      var btn = form.querySelector('button[type="submit"]');
      btn.disabled = true; btn.innerHTML = '<i class="ti ti-loader-2"></i> Entrando...';
      toast("Login simulado com sucesso!");
      setTimeout(function () { location.href = "/v2/aluno/"; }, 1100);
    });
  }

  function maskCPF(v) {
    var d = v.replace(/\D/g, "").slice(0, 11);
    if (d.length <= 3) return d;
    if (d.length <= 6) return d.slice(0, 3) + "." + d.slice(3);
    if (d.length <= 9) return d.slice(0, 3) + "." + d.slice(3, 6) + "." + d.slice(6);
    return d.slice(0, 3) + "." + d.slice(3, 6) + "." + d.slice(6, 9) + "-" + d.slice(9);
  }

  /* Máscara de CPF para os formulários reais V2 (Cadastro V2 integrado).
     Apenas formata visualmente o input marcado com [data-mask-cpf]; a validação
     real continua no servidor (que aceita CPF com ou sem máscara). */
  function initAuthMasksV2() {
    $$("[data-mask-cpf]").forEach(function (inp) {
      inp.value = maskCPF(inp.value);
      inp.addEventListener("input", function () { this.value = maskCPF(this.value); });
    });
  }

  function initCadastro() {
    var form = $("#v2-cadastro-form"); if (!form) return;
    var cpf = $("#cpf");
    if (cpf) cpf.addEventListener("input", function () { this.value = maskCPF(this.value); });
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var ok = true;
      [["nome", "f-nome", function (v) { return v.trim().length >= 3; }, "Informe seu nome completo."],
       ["email", "f-email", function (v) { return validEmail(v.trim()); }, "E-mail inválido."],
       ["cpf", "f-cpf", function (v) { return validCPF(v); }, "CPF inválido."],
       ["senha2", "f-senha2", function (v) { return v.length >= 6; }, "Senha de ao menos 6 caracteres."]
      ].forEach(function (r) {
        var field = $("#" + r[1]); var val = $("#" + r[0]).value; clearError(field);
        if (!r[2](val)) { setError(field, r[3]); ok = false; }
      });
      var s1 = $("#senha2").value, s2 = $("#senha2-conf").value, fc = $("#f-conf"); clearError(fc);
      if (s1 !== s2) { setError(fc, "As senhas não conferem."); ok = false; }
      var termos = $("#termos");
      if (termos && !termos.checked) { toast("Aceite os termos para continuar."); ok = false; }
      if (!ok) return;
      var btn = form.querySelector('button[type="submit"]');
      btn.disabled = true; btn.innerHTML = '<i class="ti ti-loader-2"></i> Criando...';
      toast("Conta criada (demonstração)!");
      setTimeout(function () { location.href = "/v2/aluno/"; }, 1100);
    });
  }

  /* ---------------- CHECKOUT --------------------------------- */
  function initCheckout() {
    var wrap = $("#v2-checkout"); if (!wrap) return;
    var step = 1, total = 4;
    var base = 197.0, desconto = 0;

    function paint() {
      $$(".v2-step", wrap).forEach(function (s) {
        var n = parseInt(s.getAttribute("data-step"), 10);
        s.classList.toggle("is-done", n < step);
        s.classList.toggle("is-active", n === step);
      });
      $$(".v2-step-line", wrap).forEach(function (l, i) { l.classList.toggle("is-done", (i + 1) < step); });
      $$(".v2-panel", wrap).forEach(function (p) { p.classList.toggle("is-active", parseInt(p.getAttribute("data-panel"), 10) === step); });
      atualizarResumo();
      window.scrollTo({ top: 0, behavior: "smooth" });
    }
    function go(n) { step = Math.max(1, Math.min(total, n)); paint(); }

    wrap.addEventListener("click", function (e) {
      var nx = e.target.closest("[data-next]"); if (nx) { go(step + 1); return; }
      var pv = e.target.closest("[data-prev]"); if (pv) { go(step - 1); return; }
    });

    // seleção de turma
    $$(".v2-radio-card", wrap).forEach(function (rc) {
      rc.addEventListener("click", function () {
        $$(".v2-radio-card", wrap).forEach(function (x) { x.classList.remove("is-sel"); });
        rc.classList.add("is-sel"); var inp = rc.querySelector("input"); if (inp) inp.checked = true;
      });
    });
    // segmento compra para
    var seg = $("#v2-compra-seg");
    if (seg) seg.addEventListener("click", function (e) { var b = e.target.closest("button"); if (!b) return; $$("button", seg).forEach(function (x) { x.classList.remove("is-on"); }); b.classList.add("is-on"); });

    // cupom
    var cupomBtn = $("#v2-cupom-btn");
    if (cupomBtn) cupomBtn.addEventListener("click", function () {
      var code = ($("#v2-cupom").value || "").trim().toUpperCase();
      var msg = $("#v2-cupom-msg");
      if (code === "DESBLOQUEIA10") { desconto = base * 0.10; msg.className = "v2-callout v2-callout-success"; msg.innerHTML = '<i class="ti ti-circle-check"></i><span>Cupom aplicado: 10% de desconto.</span>'; }
      else { desconto = 0; msg.className = "v2-callout v2-callout-danger"; msg.innerHTML = '<i class="ti ti-alert-circle"></i><span>Cupom inválido ou expirado.</span>'; }
      msg.style.display = "flex"; atualizarResumo();
    });

    function atualizarResumo() {
      var t = base - desconto;
      var elB = $("#v2-res-base"), elD = $("#v2-res-desc-row"), elDv = $("#v2-res-desc"), elT = $("#v2-res-total"), elP = $("#v2-pix-valor");
      if (elB) elB.textContent = BRL(base);
      if (elDv) elDv.textContent = "− " + BRL(desconto);
      if (elD) elD.style.display = desconto > 0 ? "flex" : "none";
      if (elT) elT.textContent = BRL(t);
      if (elP) elP.textContent = BRL(t);
    }

    // copiar PIX
    var copyBtn = $("#v2-copy-pix");
    if (copyBtn) copyBtn.addEventListener("click", function () {
      var code = copyBtn.getAttribute("data-pix");
      function done() { toast("Código PIX copiado!"); }
      if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(code).then(done).catch(done); }
      else { done(); }
    });

    // upload visual
    var up = $("#v2-upload"), upInput = $("#v2-upload-input"), prev = $("#v2-upload-prev"), finalBtn = $("#v2-finalizar");
    if (up && upInput) {
      up.addEventListener("click", function () { upInput.click(); });
      up.addEventListener("keydown", function (e) { if (e.key === "Enter" || e.key === " ") { e.preventDefault(); upInput.click(); } });
      upInput.addEventListener("change", function () {
        var f = upInput.files[0]; if (!f) return;
        up.style.display = "none"; prev.classList.add("is-on");
        $("#v2-upload-name").textContent = f.name;
        $("#v2-upload-size").textContent = (f.size / 1024).toFixed(0) + " KB";
        if (finalBtn) finalBtn.disabled = false;
      });
      var rm = $("#v2-upload-remove");
      if (rm) rm.addEventListener("click", function () { upInput.value = ""; up.style.display = "flex"; prev.classList.remove("is-on"); if (finalBtn) finalBtn.disabled = true; });
    }
    if (finalBtn) finalBtn.addEventListener("click", function () {
      var ok = $("#v2-checkout-ok"); if (ok) ok.style.display = "block";
      $$(".v2-panel", wrap).forEach(function (p) { p.classList.remove("is-active"); });
      var done = $('[data-panel="done"]'); if (done) done.classList.add("is-active");
      window.scrollTo({ top: 0, behavior: "smooth" });
    });

    paint();
  }

  /* ---------------- ÁREA DO ALUNO ---------------------------- */
  function renderAluno() {
    var box = $("#v2-aluno-cursos"); if (!box) return;
    var state = loadState();
    var meus = CURSOS.slice(0, 4);
    box.innerHTML = meus.map(function (c) {
      var prog = state["prog_" + c.id] != null ? state["prog_" + c.id] : c.progresso;
      var cs = catStyle(c.categoria);
      var href = c.id === "direito-consumidor" ? "aula.html" : "aula.html";
      return '<a class="v2-list-item" href="' + href + '">' +
        '<span class="v2-list-thumb" style="background:linear-gradient(135deg,' + cs.g1 + ',' + cs.g2 + ');"><i class="ti ' + cs.icon + '" style="color:' + cs.cor + ';"></i></span>' +
        '<span class="v2-list-body">' +
          '<b class="v2-card-title" style="display:block">' + esc(c.titulo) + "</b>" +
          '<span class="v2-progress"><span class="v2-progress-track"><span class="v2-progress-fill" style="width:' + prog + '%"></span></span>' +
          '<span class="v2-progress-row"><span class="v2-progress-pct">' + prog + '%</span><span class="v2-muted">' + (prog >= 100 ? "Concluído" : prog > 0 ? "Em andamento" : "Não iniciado") + "</span></span></span>" +
        "</span></a>";
    }).join("");

    // continue aprendendo (primeiro curso)
    var cont = $("#v2-continue-prog");
    if (cont) { var p0 = state["prog_direito-consumidor"] != null ? state["prog_direito-consumidor"] : 35; cont.style.width = p0 + "%"; var pl = $("#v2-continue-pct"); if (pl) pl.textContent = p0 + "%"; }
  }

  /* ---------------- LMS (aula) ------------------------------- */
  function initAula() {
    var page = $("#v2-aula"); if (!page) return;
    var state = loadState();
    var key = "prog_direito-consumidor";
    var prog = state[key] != null ? state[key] : 35;

    function setProg(p) {
      prog = Math.max(0, Math.min(100, p));
      state[key] = prog; saveState(state);
      var f = $("#v2-aula-progress"); if (f) f.style.width = prog + "%";
      var l = $("#v2-aula-progress-pct"); if (l) l.textContent = prog + "%";
    }
    setProg(prog);

    // player
    var play = $("#v2-player-play");
    if (play) play.addEventListener("click", function () {
      var ic = play.querySelector("i");
      var playing = ic.classList.contains("ti-player-pause-filled");
      ic.className = playing ? "ti ti-player-play-filled" : "ti ti-player-pause-filled";
      toast(playing ? "Vídeo pausado (demo)" : "Reproduzindo (demo)");
    });

    // marcar concluído
    var mark = $("#v2-mark");
    if (mark) mark.addEventListener("click", function () {
      mark.classList.add("v2-concluido"); mark.innerHTML = '<i class="ti ti-circle-check"></i> Concluído';
      var item = $('.v2-mod-item.is-current');
      if (item) { item.classList.add("is-done"); var mi = item.querySelector(".v2-mi-ic"); if (mi) mi.className = "ti ti-circle-check-filled v2-mi-ic"; }
      setProg(prog + 10);
      toast("Aula marcada como concluída!");
    });

    // quiz
    var quizForm = $("#v2-quiz");
    if (quizForm) {
      quizForm.addEventListener("submit", function (e) {
        e.preventDefault();
        var perguntas = $$(".v2-quiz-q", quizForm);
        var acertos = 0, respondidas = 0;
        perguntas.forEach(function (q) {
          var sel = q.querySelector('input:checked');
          if (sel) { respondidas++; }
          q.querySelectorAll(".v2-quiz-alt").forEach(function (alt) {
            var inp = alt.querySelector("input");
            alt.classList.remove("is-correct", "is-wrong");
            if (inp.value === "c") alt.classList.add("is-correct");
            else if (inp.checked) alt.classList.add("is-wrong");
          });
          if (sel && sel.value === "c") acertos++;
        });
        if (respondidas < perguntas.length) { toast("Responda todas as perguntas."); return; }
        var pct = Math.round((acertos / perguntas.length) * 100);
        var res = $("#v2-quiz-result");
        var aprov = pct >= 60;
        res.innerHTML = '<div class="v2-quiz-score ' + (aprov ? "ok" : "no") + '">' + pct + "%</div>" +
          "<p><b>" + (aprov ? "Aprovado!" : "Quase lá!") + "</b> Você acertou " + acertos + " de " + perguntas.length + " questões.</p>" +
          (aprov ? "" : '<button type="button" class="v2-btn v2-btn-outline" id="v2-quiz-retry">Tentar novamente</button>');
        res.style.display = "block";
        quizForm.querySelector('button[type="submit"]').style.display = "none";
        if (aprov) setProg(prog + 10);
        var retry = $("#v2-quiz-retry");
        if (retry) retry.addEventListener("click", function () {
          quizForm.reset();
          $$(".v2-quiz-alt", quizForm).forEach(function (a) { a.classList.remove("is-correct", "is-wrong"); });
          res.style.display = "none";
          quizForm.querySelector('button[type="submit"]').style.display = "";
        });
      });
    }

    // navegação anterior/próxima (visual)
    $$("[data-aula-nav]").forEach(function (b) {
      b.addEventListener("click", function () {
        if (b.hasAttribute("disabled")) return;
        toast(b.getAttribute("data-aula-nav") === "next" ? "Próxima aula (demo)" : "Aula anterior (demo)");
      });
    });
  }

  /* ---------------- Menu / nav ativa ------------------------- */
  function initNav() {
    var path = (location.pathname.split("/").pop() || "index.html");
    $$("[data-navfor]").forEach(function (a) {
      if (a.getAttribute("data-navfor") === path) a.classList.add("is-active");
    });
  }

  /* ---------------- Forms genéricos: preventDefault ---------- */
  function initFormGuard() {
    $$("form").forEach(function (f) {
      if (f.id === "v2-login-form" || f.id === "v2-cadastro-form" || f.id === "v2-quiz") return;
      if (f.hasAttribute("data-native-submit")) return; // formulários GET reais (ex.: catálogo integrado)
      f.addEventListener("submit", function (e) { e.preventDefault(); });
    });
  }

  /* ---------------- Boot ------------------------------------- */
  document.addEventListener("DOMContentLoaded", function () {
    if (!window.V2_DISABLE_AUTORENDER_HOME) {
      renderHome();
    }
    renderCatalogo();
    if (!window.V2_DISABLE_AUTORENDER_CATALOGO) {
      renderCatalogoV2();
    }
    initCatalogoIntegradoV2();
    if (!window.V2_DISABLE_AUTORENDER_CURSO) {
      renderCursoV2();
    }
    initCursoIntegradoV2();
    initCheckoutV2();
    initAlunoV2();
    initAulaV2();
    initLoginV2();
    initCadastroV2();
    initRecuperarV2();
    initEventosV2();
    initCategoriasV2();
    initContatoV2();
    initCertValidarV2();
    initAnchorsV2();
    initFilterSheet();
    initPassToggles();
    initAuthMasksV2();
    initTabs();
    initAccordion();
    initLmsV2();
    initQuizV2();
    initAtividadeV2();
    initCertValidacaoV2();
    initCheckoutPreV2();
    initLogin();
    initCadastro();
    initCheckout();
    renderAluno();
    initAula();
    initNav();
    initFormGuard();
  });
})();
