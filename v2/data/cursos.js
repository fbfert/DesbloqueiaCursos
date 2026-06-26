/* ============================================================
   Desbloqueia Cursos — V2 (demonstração estática)
   Dados fictícios. Nenhuma integração com backend.
   Exposto como window.V2_CURSOS e window.V2_CATEGORIAS.

   Campos antigos preservados (catálogo/home): titulo, categoria,
   modalidade, cargaHoraria, preco, nota, avaliacoes, alunos,
   destaque, novo, descricaoCurta, topicos, pagina, progresso.

   Campos novos (ficha /v2/curso/?id=…): precoFormatado,
   totalAvaliacoes, totalAlunos, resumo, descricao, objetivos,
   conteudosProgramaticos, instrutor, turmas (com id), nivel,
   certificado, acesso, imagem.
   ============================================================ */
(function () {
  "use strict";

  // Paletas de placeholder por categoria (gradiente suave + ícone Tabler)
  var CATEGORIAS = {
    "Direito":      { icon: "ti-scale",         g1: "#fff4ec", g2: "#ffe4d3", cor: "#cc5500" },
    "Marketing":    { icon: "ti-speakerphone",  g1: "#f0e8ff", g2: "#e4d6ff", cor: "#4B008E" },
    "Gestão":       { icon: "ti-chart-bar",     g1: "#d1faf5", g2: "#b8f2ea", cor: "#007a6a" },
    "Tecnologia":   { icon: "ti-device-laptop", g1: "#e3f0ff", g2: "#cfe4ff", cor: "#1d4ed8" },
    "Comunicação":  { icon: "ti-microphone",    g1: "#ffe9f0", g2: "#ffd6e3", cor: "#c00057" },
    "Negócios":     { icon: "ti-briefcase",     g1: "#eef7df", g2: "#dcefc0", cor: "#3b6d11" }
  };

  var CURSOS = [
    {
      id: "direito-consumidor",
      progressoDemo: 50,
      modulos: [
        { id: "fundamentos-cdc", titulo: "Fundamentos do CDC", duracao: "2h", aulas: [
          { id: "introducao-cdc", titulo: "Introdução ao Código de Defesa do Consumidor", tipo: "video", duracao: "14 min", concluidaDemo: true,
            descricao: "Panorama do CDC apresentado de forma didática nesta demonstração da interface.",
            materiais: [ { nome: "Mapa do CDC", descricao: "Resumo visual (demonstração)", tipo: "pdf" } ] },
          { id: "principios", titulo: "Princípios do CDC", tipo: "texto", duracao: "12 min", concluidaDemo: true,
            descricao: "Princípios gerais apresentados de forma introdutória.",
            conteudo: [
              { h: "Princípios em destaque" },
              { p: "Conteúdo de demonstração da V2. Este texto é ilustrativo da interface e não constitui orientação jurídica." },
              { ul: ["Boa-fé nas relações de consumo", "Transparência nas informações", "Equilíbrio entre as partes"] },
              { callout: "Texto demonstrativo — não é aconselhamento profissional." }
            ] }
        ]},
        { id: "direitos-basicos", titulo: "Direitos básicos do consumidor", duracao: "2h 20min", aulas: [
          { id: "direitos", titulo: "Direitos básicos na prática", tipo: "video", duracao: "18 min", concluidaDemo: false,
            descricao: "Apresentação dos direitos básicos do consumidor (conteúdo demonstrativo).",
            materiais: [ { nome: "Lista de direitos", descricao: "Resumo em PDF (demonstração)", tipo: "arquivo" } ] },
          { id: "quiz-cdc", titulo: "Quiz: Direitos básicos", tipo: "quiz", duracao: "3 min", concluidaDemo: false,
            descricao: "Teste rápido sobre o conteúdo apresentado.",
            quiz: { enunciado: "Nesta demonstração, qual é um princípio destacado do CDC?", alternativas: ["Sigilo bancário", "Boa-fé", "Anterioridade tributária", "Livre concorrência"], correta: 1, explicacao: "A boa-fé é destacada como princípio nesta demonstração da interface." } }
        ]}
      ],
      titulo: "Direito do Consumidor na Prática",
      categoria: "Direito",
      modalidade: "Online",
      nivel: "Intermediário",
      cargaHoraria: 40,
      preco: 197.0,
      precoFormatado: "R$ 197,00",
      nota: 4.9,
      avaliacoes: 312,
      totalAvaliacoes: 312,
      alunos: 1840,
      totalAlunos: 1840,
      destaque: true,
      novo: false,
      certificado: true,
      acesso: "Acesso vitalício",
      imagem: null,
      resumo: "Domine o Código de Defesa do Consumidor e aprenda a aplicá-lo em casos reais — do atendimento ao consumidor até a ação judicial.",
      descricaoCurta: "Domine os direitos do consumidor e aprenda a aplicá-los em casos reais, do atendimento à ação judicial.",
      descricao: "Este curso conduz você por toda a jornada do Direito do Consumidor, partindo dos princípios do Código de Defesa do Consumidor até a elaboração de petições e a atuação em casos concretos. A proposta é unir teoria e prática com linguagem acessível, estudos de caso comentados e modelos prontos para usar no dia a dia.\n\nAo longo das aulas, você aprende a identificar práticas abusivas, conduzir negociações no Procon, calcular indenizações e estruturar teses de consumo. Conteúdo de demonstração da V2 — os textos, números e materiais são ilustrativos.",
      objetivos: [
        "Código de Defesa do Consumidor aplicado ao dia a dia",
        "Práticas abusivas e como identificá-las",
        "Garantia legal e contratual na prática",
        "Negociação e procedimentos no Procon",
        "Petições e teses de consumo mais usadas",
        "Indenização por danos morais e materiais"
      ],
      topicos: [
        "Código de Defesa do Consumidor aplicado ao dia a dia",
        "Práticas abusivas e como identificá-las",
        "Garantia legal e contratual na prática",
        "Negociação e procedimentos no Procon",
        "Petições e teses de consumo mais usadas",
        "Indenização por danos morais e materiais"
      ],
      conteudosProgramaticos: [
        { titulo: "Fundamentos do Direito do Consumidor", duracao: "3h20", aulas: [
          { titulo: "Apresentação do curso e metodologia", tipo: "video", duracao: "6 min" },
          { titulo: "O que é relação de consumo", tipo: "video", duracao: "18 min" },
          { titulo: "Princípios do CDC", tipo: "texto", duracao: "12 min" },
          { titulo: "CDC comentado (material de apoio)", tipo: "pdf", duracao: "" }
        ]},
        { titulo: "Direitos básicos do consumidor", duracao: "2h40", aulas: [
          { titulo: "Os direitos básicos na prática", tipo: "video", duracao: "22 min" },
          { titulo: "Informação, oferta e publicidade", tipo: "video", duracao: "16 min" },
          { titulo: "Quiz do módulo", tipo: "quiz", duracao: "" }
        ]},
        { titulo: "Práticas abusivas e garantias", duracao: "3h10", aulas: [
          { titulo: "Identificando práticas abusivas", tipo: "video", duracao: "20 min" },
          { titulo: "Garantia legal x garantia contratual", tipo: "video", duracao: "19 min" },
          { titulo: "Estudo de caso comentado", tipo: "texto", duracao: "14 min" },
          { titulo: "Atividade prática: analisando um contrato", tipo: "atividade", duracao: "" }
        ]},
        { titulo: "Procon e resolução de conflitos", duracao: "2h30", aulas: [
          { titulo: "Como atuar junto ao Procon", tipo: "video", duracao: "17 min" },
          { titulo: "Modelos de reclamação", tipo: "pdf", duracao: "" },
          { titulo: "Técnicas de negociação", tipo: "video", duracao: "15 min" }
        ]},
        { titulo: "Atuação judicial em consumo", duracao: "3h40", aulas: [
          { titulo: "Estruturando a petição inicial", tipo: "video", duracao: "24 min" },
          { titulo: "Teses de consumo mais usadas", tipo: "texto", duracao: "16 min" },
          { titulo: "Danos morais e materiais", tipo: "video", duracao: "21 min" },
          { titulo: "Avaliação final", tipo: "quiz", duracao: "" }
        ]}
      ],
      instrutor: { nome: "Dra. Helena Martins", iniciais: "HM", area: "Direito do Consumidor", bio: "Advogada e professora, dedica-se à formação prática de profissionais do Direito do Consumidor. Perfil fictício — conteúdo de demonstração da V2." },
      turmas: [
        { id: "online", nome: "Turma Online — Acesso imediato", modalidade: "Online", inicio: "Acesso imediato", vagas: null, local: null, preco: 197.0, formato: "Gravado + ao vivo mensal" },
        { id: "aovivo", nome: "Turma ao vivo — Noturna", modalidade: "Ao vivo", inicio: "Início em 12/08", vagas: 24, local: "Encontros online às terças", preco: 247.0, formato: "Encontros ao vivo às terças" }
      ],
      pagina: "curso-direito-consumidor.html",
      progresso: 35
    },
    {
      id: "oficinas-ace",
      titulo: "Oficinas de ACE",
      categoria: "Gestão",
      modalidade: "Presencial",
      nivel: "Iniciante",
      cargaHoraria: 20,
      preco: 100.0,
      precoFormatado: "R$ 100,00",
      nota: 4.7,
      avaliacoes: 86,
      totalAvaliacoes: 86,
      alunos: 420,
      totalAlunos: 420,
      destaque: true,
      novo: false,
      certificado: true,
      acesso: "Material disponível por 12 meses",
      imagem: null,
      resumo: "Desenvolva, aplique e apresente seu projeto de Atividade Curricular de Extensão com orientação de especialistas.",
      descricaoCurta: "Desenvolva e apresente seus projetos de Atividade Curricular de Extensão com orientação de especialistas.",
      descricao: "As Oficinas de ACE acompanham você na construção do seu projeto de extensão, da concepção da ideia à apresentação final. O foco é a aplicação prática junto à comunidade, com mentoria em cada etapa.\n\nÉ um curso presencial, pensado para quem prefere encontros e trabalho em grupo. Conteúdo de demonstração da V2 — datas, locais e vagas são ilustrativos.",
      objetivos: [
        "Estruturar um projeto de extensão do zero",
        "Aplicar o projeto junto à comunidade",
        "Preparar a apresentação e a defesa",
        "Elaborar o relatório final"
      ],
      topicos: [
        "Desenvolvimento do projeto de extensão",
        "Aplicação prática junto à comunidade",
        "Apresentação e defesa do projeto",
        "Elaboração do relatório final"
      ],
      conteudosProgramaticos: [
        { titulo: "Concepção do projeto", duracao: "6h", aulas: [
          { titulo: "Boas-vindas e organização das oficinas", tipo: "texto", duracao: "10 min" },
          { titulo: "Escolha do tema e da comunidade", tipo: "atividade", duracao: "" },
          { titulo: "Roteiro do projeto (material)", tipo: "pdf", duracao: "" }
        ]},
        { titulo: "Aplicação prática", duracao: "8h", aulas: [
          { titulo: "Planejamento da intervenção", tipo: "atividade", duracao: "" },
          { titulo: "Registro e acompanhamento", tipo: "texto", duracao: "14 min" }
        ]},
        { titulo: "Apresentação e relatório", duracao: "6h", aulas: [
          { titulo: "Como apresentar e defender o projeto", tipo: "atividade", duracao: "" },
          { titulo: "Modelo de relatório final", tipo: "pdf", duracao: "" }
        ]}
      ],
      instrutor: { nome: "Prof. Ricardo Alves", iniciais: "RA", area: "Projetos de Extensão", bio: "Orienta projetos de extensão e atividades práticas com a comunidade. Perfil fictício — conteúdo de demonstração da V2." },
      turmas: [
        { id: "presencial-lages", nome: "Turma presencial — Polo Rainbow", modalidade: "Presencial", inicio: "Datas a definir", vagas: 18, local: "Polo Rainbow — Lages/SC", preco: 100.0, formato: "Encontros presenciais" }
      ],
      pagina: "curso-direito-consumidor.html",
      progresso: 0
    },
    {
      id: "marketing-digital",
      progressoDemo: 67,
      modulos: [
        { id: "fundamentos", titulo: "Fundamentos do marketing digital", duracao: "2h 30min", aulas: [
          { id: "introducao", titulo: "Introdução ao marketing digital", tipo: "video", duracao: "12 min", concluidaDemo: true,
            descricao: "Visão geral do que é marketing digital e de como esta demonstração está organizada.",
            materiais: [ { nome: "Slides da aula", descricao: "Resumo visual em PDF (demonstração)", tipo: "pdf" } ] },
          { id: "funil", titulo: "Funil de vendas e jornada do cliente", tipo: "texto", duracao: "15 min", concluidaDemo: true,
            descricao: "Como pensar as etapas da jornada do cliente, do primeiro contato à compra.",
            conteudo: [
              { h: "O que é um funil de vendas" },
              { p: "Conteúdo de demonstração da V2: o funil organiza a jornada do cliente em etapas, ajudando a planejar conteúdos e ofertas para cada momento." },
              { ul: ["Topo: descoberta e atração", "Meio: consideração e relacionamento", "Fundo: decisão e compra"] },
              { callout: "Os exemplos e números desta tela são ilustrativos." }
            ],
            materiais: [ { nome: "Modelo de funil", descricao: "Planilha de exemplo (demonstração)", tipo: "arquivo" } ] },
          { id: "quiz-fundamentos", titulo: "Quiz: Fundamentos", tipo: "quiz", duracao: "3 min", concluidaDemo: false,
            descricao: "Teste rápido para fixar os fundamentos.",
            quiz: { enunciado: "Qual etapa do funil representa a decisão de compra?", alternativas: ["Topo do funil", "Meio do funil", "Fundo do funil", "Fora do funil"], correta: 2, explicacao: "O fundo do funil concentra a etapa de decisão e compra." } }
        ]},
        { id: "trafego-pago", titulo: "Tráfego pago", duracao: "3h", aulas: [
          { id: "trafego-pago", titulo: "Tráfego pago: conceitos e estratégias", tipo: "video", duracao: "24 min", concluidaDemo: true,
            descricao: "Conceitos de tráfego pago e estratégias de campanha, apresentados de forma demonstrativa.",
            materiais: [
              { nome: "Checklist de campanha", descricao: "Lista de verificação (demonstração)", tipo: "pdf" },
              { nome: "Glossário de métricas", descricao: "Termos essenciais (demonstração)", tipo: "link" }
            ] },
          { id: "otimizacao", titulo: "Otimização de campanhas", tipo: "texto", duracao: "16 min", concluidaDemo: false,
            descricao: "Como ler métricas e ajustar campanhas ao longo do tempo.",
            conteudo: [
              { h: "Métricas que importam" },
              { p: "Conteúdo de demonstração da V2: acompanhar as métricas certas ajuda a decidir onde investir." },
              { ul: ["CTR — taxa de cliques", "CPC — custo por clique", "ROAS — retorno sobre o investimento em anúncios"] },
              { callout: "Valores citados são ilustrativos desta demonstração." }
            ] },
          { id: "quiz-trafego", titulo: "Quiz: Tráfego pago", tipo: "quiz", duracao: "4 min", concluidaDemo: false,
            descricao: "Fixe os conceitos de tráfego pago.",
            quiz: { enunciado: "O que a métrica ROAS indica?", alternativas: ["Custo por clique", "Retorno sobre o investimento em anúncios", "Número de seguidores", "Taxa de rejeição"], correta: 1, explicacao: "ROAS significa retorno sobre o investimento em anúncios." } }
        ]},
        { id: "relatorios", titulo: "Relatórios e análise", duracao: "1h", aulas: [
          { id: "dashboard", titulo: "Montando um dashboard", tipo: "texto", duracao: "14 min", bloqueadaDemo: true, descricao: "Disponível ao avançar na jornada." },
          { id: "fechamento", titulo: "Fechamento do curso", tipo: "texto", duracao: "8 min", bloqueadaDemo: true, descricao: "Disponível ao avançar na jornada." }
        ]}
      ],
      titulo: "Marketing Digital na Prática",
      categoria: "Marketing",
      modalidade: "Online",
      nivel: "Intermediário",
      cargaHoraria: 32,
      preco: 247.0,
      precoFormatado: "R$ 247,00",
      nota: 4.8,
      avaliacoes: 540,
      totalAvaliacoes: 540,
      alunos: 3120,
      totalAlunos: 3120,
      destaque: true,
      novo: true,
      certificado: true,
      acesso: "Acesso vitalício",
      imagem: null,
      resumo: "Planeje e execute campanhas digitais que vendem: tráfego, conteúdo, redes sociais e métricas.",
      descricaoCurta: "Planeje e execute campanhas digitais que vendem: tráfego, conteúdo, redes sociais e métricas.",
      descricao: "Um curso prático para tirar campanhas do papel: do planejamento e do funil de vendas até o tráfego pago, o conteúdo para redes sociais e a leitura de métricas. Você acompanha exemplos reais de estrutura de campanha.\n\nConteúdo de demonstração da V2 — exemplos, marcas e resultados citados são ilustrativos.",
      objetivos: [
        "Planejamento de marketing e funil de vendas",
        "Tráfego pago no Meta e no Google Ads",
        "Conteúdo para redes sociais",
        "Copywriting que converte",
        "Métricas e otimização de campanhas"
      ],
      topicos: [
        "Planejamento de marketing e funil de vendas",
        "Tráfego pago no Meta e Google Ads",
        "Conteúdo para redes sociais",
        "Copywriting que converte",
        "Métricas e otimização de campanhas"
      ],
      conteudosProgramaticos: [
        { titulo: "Estratégia e planejamento", duracao: "4h", aulas: [
          { titulo: "Visão geral do marketing digital", tipo: "video", duracao: "15 min" },
          { titulo: "Funil de vendas e jornada", tipo: "video", duracao: "18 min" },
          { titulo: "Planilha de planejamento", tipo: "pdf", duracao: "" }
        ]},
        { titulo: "Tráfego e conteúdo", duracao: "6h", aulas: [
          { titulo: "Tráfego pago no Meta Ads", tipo: "video", duracao: "24 min" },
          { titulo: "Tráfego no Google Ads", tipo: "video", duracao: "22 min" },
          { titulo: "Conteúdo para redes sociais", tipo: "texto", duracao: "16 min" },
          { titulo: "Quiz do módulo", tipo: "quiz", duracao: "" }
        ]},
        { titulo: "Conversão e métricas", duracao: "4h", aulas: [
          { titulo: "Copywriting que converte", tipo: "video", duracao: "20 min" },
          { titulo: "Métricas que importam", tipo: "video", duracao: "17 min" },
          { titulo: "Atividade: montando um relatório", tipo: "atividade", duracao: "" }
        ]}
      ],
      instrutor: { nome: "Marina Costa", iniciais: "MC", area: "Marketing Digital", bio: "Atua com campanhas digitais e conteúdo para redes sociais. Perfil fictício — conteúdo de demonstração da V2." },
      turmas: [
        { id: "online", nome: "Turma Online — Acesso imediato", modalidade: "Online", inicio: "Acesso imediato", vagas: null, local: null, preco: 247.0, formato: "100% gravado" }
      ],
      pagina: "curso-direito-consumidor.html",
      progresso: 0
    },
    {
      id: "gestao-projetos",
      titulo: "Gestão de Projetos",
      categoria: "Gestão",
      modalidade: "Online",
      nivel: "Intermediário",
      cargaHoraria: 36,
      preco: 220.0,
      precoFormatado: "R$ 220,00",
      nota: 4.6,
      avaliacoes: 198,
      totalAvaliacoes: 198,
      alunos: 1490,
      totalAlunos: 1490,
      destaque: false,
      novo: false,
      certificado: true,
      acesso: "Acesso vitalício",
      imagem: null,
      resumo: "Conduza projetos do início ao fim com métodos ágeis e tradicionais, prazos e equipes sob controle.",
      descricaoCurta: "Conduza projetos do início ao fim com métodos ágeis e tradicionais, prazos e equipes sob controle.",
      descricao: "Aprenda a planejar, executar e encerrar projetos com método. O curso percorre o ciclo de vida do projeto, escopo, cronograma e custos, além dos frameworks ágeis Scrum e Kanban e da gestão de riscos e stakeholders.\n\nConteúdo de demonstração da V2 — ferramentas e indicadores citados são ilustrativos.",
      objetivos: [
        "Ciclo de vida do projeto",
        "Escopo, cronograma e custos",
        "Métodos ágeis: Scrum e Kanban",
        "Gestão de riscos e stakeholders",
        "Ferramentas e indicadores"
      ],
      topicos: [
        "Ciclo de vida do projeto",
        "Escopo, cronograma e custos",
        "Métodos ágeis: Scrum e Kanban",
        "Gestão de riscos e stakeholders",
        "Ferramentas e indicadores"
      ],
      conteudosProgramaticos: [
        { titulo: "Fundamentos de projetos", duracao: "4h", aulas: [
          { titulo: "O que é um projeto", tipo: "video", duracao: "14 min" },
          { titulo: "Ciclo de vida e fases", tipo: "video", duracao: "18 min" },
          { titulo: "Termo de abertura (modelo)", tipo: "pdf", duracao: "" }
        ]},
        { titulo: "Planejamento", duracao: "6h", aulas: [
          { titulo: "Escopo e EAP", tipo: "video", duracao: "20 min" },
          { titulo: "Cronograma e custos", tipo: "video", duracao: "19 min" },
          { titulo: "Quiz do módulo", tipo: "quiz", duracao: "" }
        ]},
        { titulo: "Ágil e execução", duracao: "5h", aulas: [
          { titulo: "Scrum na prática", tipo: "video", duracao: "22 min" },
          { titulo: "Kanban e fluxo", tipo: "texto", duracao: "13 min" },
          { titulo: "Atividade: montando o board", tipo: "atividade", duracao: "" }
        ]}
      ],
      instrutor: { nome: "Eng. Paulo Tavares", iniciais: "PT", area: "Gestão de Projetos", bio: "Trabalha com gestão de projetos em ambientes ágeis e tradicionais. Perfil fictício — conteúdo de demonstração da V2." },
      turmas: [
        { id: "online", nome: "Turma Online — Acesso imediato", modalidade: "Online", inicio: "Acesso imediato", vagas: null, local: null, preco: 220.0, formato: "Gravado" }
      ],
      pagina: "curso-direito-consumidor.html",
      progresso: 70
    },
    {
      id: "excel-negocios",
      titulo: "Excel para Negócios",
      categoria: "Tecnologia",
      modalidade: "Online",
      nivel: "Iniciante",
      cargaHoraria: 28,
      preco: 159.0,
      precoFormatado: "R$ 159,00",
      nota: 4.9,
      avaliacoes: 712,
      totalAvaliacoes: 712,
      alunos: 5240,
      totalAlunos: 5240,
      destaque: true,
      novo: false,
      certificado: true,
      acesso: "Acesso vitalício",
      imagem: null,
      resumo: "Do básico ao avançado: fórmulas, tabelas dinâmicas e dashboards para decisões melhores.",
      descricaoCurta: "Do básico ao avançado: fórmulas, tabelas dinâmicas e dashboards para decisões melhores.",
      descricao: "Aprenda Excel com foco em negócios: das fórmulas essenciais às tabelas dinâmicas e aos dashboards profissionais. Cada módulo traz exercícios para fixar o aprendizado.\n\nConteúdo de demonstração da V2 — planilhas e exemplos são ilustrativos.",
      objetivos: [
        "Fórmulas essenciais e funções de busca",
        "Tabelas e gráficos dinâmicos",
        "Dashboards profissionais",
        "Introdução à automação com macros"
      ],
      topicos: [
        "Fórmulas essenciais e funções de busca",
        "Tabelas e gráficos dinâmicos",
        "Dashboards profissionais",
        "Automação com macros (introdução)"
      ],
      conteudosProgramaticos: [
        { titulo: "Fundamentos e fórmulas", duracao: "5h", aulas: [
          { titulo: "Interface e primeiros passos", tipo: "video", duracao: "12 min" },
          { titulo: "Fórmulas essenciais", tipo: "video", duracao: "20 min" },
          { titulo: "Funções de busca (PROCV/ÍNDICE)", tipo: "video", duracao: "18 min" }
        ]},
        { titulo: "Análise de dados", duracao: "5h", aulas: [
          { titulo: "Tabelas dinâmicas", tipo: "video", duracao: "22 min" },
          { titulo: "Gráficos dinâmicos", tipo: "texto", duracao: "12 min" },
          { titulo: "Quiz do módulo", tipo: "quiz", duracao: "" }
        ]},
        { titulo: "Dashboards e automação", duracao: "4h", aulas: [
          { titulo: "Montando um dashboard", tipo: "video", duracao: "24 min" },
          { titulo: "Introdução a macros", tipo: "video", duracao: "15 min" },
          { titulo: "Atividade prática final", tipo: "atividade", duracao: "" }
        ]}
      ],
      instrutor: { nome: "Camila Souza", iniciais: "CS", area: "Análise de Dados", bio: "Atua com planilhas, relatórios e visualização de dados. Perfil fictício — conteúdo de demonstração da V2." },
      turmas: [
        { id: "online", nome: "Turma Online — Acesso imediato", modalidade: "Online", inicio: "Acesso imediato", vagas: null, local: null, preco: 159.0, formato: "Gravado" }
      ],
      pagina: "curso-direito-consumidor.html",
      progresso: 100
    },
    {
      id: "direito-trabalhista",
      titulo: "Direito Trabalhista",
      categoria: "Direito",
      modalidade: "Online",
      nivel: "Intermediário",
      cargaHoraria: 44,
      preco: 210.0,
      precoFormatado: "R$ 210,00",
      nota: 4.7,
      avaliacoes: 264,
      totalAvaliacoes: 264,
      alunos: 1310,
      totalAlunos: 1310,
      destaque: false,
      novo: false,
      certificado: true,
      acesso: "Acesso vitalício",
      imagem: null,
      resumo: "Relações de trabalho, rescisões, verbas e processo do trabalho explicados com casos práticos.",
      descricaoCurta: "Relações de trabalho, rescisões, verbas e processo do trabalho explicados com casos práticos.",
      descricao: "Entenda as relações de trabalho na prática: da CLT e da reforma trabalhista ao cálculo de verbas rescisórias e ao processo do trabalho. As aulas usam exemplos comentados.\n\nConteúdo de demonstração da V2 — cálculos e casos são ilustrativos.",
      objetivos: [
        "CLT e reforma trabalhista",
        "Cálculo de verbas rescisórias",
        "Jornada, férias e adicionais",
        "Processo do trabalho na prática"
      ],
      topicos: [
        "CLT e reforma trabalhista",
        "Cálculo de verbas rescisórias",
        "Jornada, férias e adicionais",
        "Processo do trabalho na prática"
      ],
      conteudosProgramaticos: [
        { titulo: "Relações de trabalho", duracao: "6h", aulas: [
          { titulo: "CLT e princípios", tipo: "video", duracao: "18 min" },
          { titulo: "Reforma trabalhista", tipo: "video", duracao: "20 min" },
          { titulo: "Resumo (material)", tipo: "pdf", duracao: "" }
        ]},
        { titulo: "Verbas e direitos", duracao: "6h", aulas: [
          { titulo: "Jornada, férias e adicionais", tipo: "video", duracao: "22 min" },
          { titulo: "Cálculo de verbas rescisórias", tipo: "texto", duracao: "16 min" },
          { titulo: "Quiz do módulo", tipo: "quiz", duracao: "" }
        ]},
        { titulo: "Processo do trabalho", duracao: "5h", aulas: [
          { titulo: "Fases do processo", tipo: "video", duracao: "19 min" },
          { titulo: "Atividade: peça trabalhista", tipo: "atividade", duracao: "" }
        ]}
      ],
      instrutor: { nome: "Dr. André Lima", iniciais: "AL", area: "Direito do Trabalho", bio: "Dedica-se ao Direito do Trabalho e à orientação de equipes jurídicas. Perfil fictício — conteúdo de demonstração da V2." },
      turmas: [
        { id: "online", nome: "Turma Online — Acesso imediato", modalidade: "Online", inicio: "Acesso imediato", vagas: null, local: null, preco: 210.0, formato: "Gravado" }
      ],
      pagina: "curso-direito-consumidor.html",
      progresso: 0
    },
    {
      id: "comunicacao-oratoria",
      titulo: "Comunicação e Oratória",
      categoria: "Comunicação",
      modalidade: "Online",
      nivel: "Iniciante",
      cargaHoraria: 18,
      preco: 129.0,
      precoFormatado: "R$ 129,00",
      nota: 4.8,
      avaliacoes: 421,
      totalAvaliacoes: 421,
      alunos: 2670,
      totalAlunos: 2670,
      destaque: false,
      novo: true,
      certificado: true,
      acesso: "Acesso vitalício",
      imagem: null,
      resumo: "Fale com clareza e confiança em reuniões, apresentações e ao vivo, sem travar.",
      descricaoCurta: "Fale com clareza e confiança em reuniões, apresentações e ao vivo, sem travar.",
      descricao: "Desenvolva a sua comunicação para falar em público com mais segurança. O curso trabalha o medo de falar, a estrutura de uma boa apresentação, voz, ritmo, linguagem corporal e storytelling.\n\nConteúdo de demonstração da V2 — exercícios e exemplos são ilustrativos.",
      objetivos: [
        "Vencer o medo de falar em público",
        "Estruturar uma boa apresentação",
        "Trabalhar voz, ritmo e linguagem corporal",
        "Usar storytelling para engajar"
      ],
      topicos: [
        "Vencer o medo de falar em público",
        "Estrutura de uma boa apresentação",
        "Voz, ritmo e linguagem corporal",
        "Storytelling para engajar"
      ],
      conteudosProgramaticos: [
        { titulo: "Preparação", duracao: "3h", aulas: [
          { titulo: "Vencendo o medo de falar", tipo: "video", duracao: "16 min" },
          { titulo: "Respiração e voz", tipo: "video", duracao: "14 min" }
        ]},
        { titulo: "Estrutura e presença", duracao: "3h", aulas: [
          { titulo: "Estrutura de uma boa apresentação", tipo: "video", duracao: "18 min" },
          { titulo: "Linguagem corporal", tipo: "texto", duracao: "12 min" },
          { titulo: "Quiz do módulo", tipo: "quiz", duracao: "" }
        ]},
        { titulo: "Storytelling", duracao: "2h", aulas: [
          { titulo: "Contando histórias que engajam", tipo: "video", duracao: "20 min" },
          { titulo: "Atividade: seu pitch de 1 minuto", tipo: "atividade", duracao: "" }
        ]}
      ],
      instrutor: { nome: "Letícia Ramos", iniciais: "LR", area: "Comunicação", bio: "Trabalha com comunicação, apresentações e oratória. Perfil fictício — conteúdo de demonstração da V2." },
      turmas: [
        { id: "online", nome: "Turma Online — Acesso imediato", modalidade: "Online", inicio: "Acesso imediato", vagas: null, local: null, preco: 129.0, formato: "Gravado" }
      ],
      pagina: "curso-direito-consumidor.html",
      progresso: 0
    },
    {
      id: "power-bi",
      titulo: "Power BI na Prática",
      categoria: "Tecnologia",
      modalidade: "Online",
      nivel: "Intermediário",
      cargaHoraria: 30,
      preco: 199.0,
      precoFormatado: "R$ 199,00",
      nota: 4.9,
      avaliacoes: 388,
      totalAvaliacoes: 388,
      alunos: 2010,
      totalAlunos: 2010,
      destaque: true,
      novo: true,
      certificado: true,
      acesso: "Acesso vitalício",
      imagem: null,
      resumo: "Transforme dados em decisões com dashboards interativos, modelagem e DAX.",
      descricaoCurta: "Transforme dados em decisões com dashboards interativos, modelagem e DAX.",
      descricao: "Aprenda a conectar, tratar e modelar dados no Power BI e a construir dashboards interativos. O curso cobre Power Query, relacionamentos, medidas em DAX e publicação.\n\nConteúdo de demonstração da V2 — bases e relatórios são ilustrativos.",
      objetivos: [
        "Conexão e tratamento de dados (Power Query)",
        "Modelagem de dados e relacionamentos",
        "Medidas e fórmulas DAX",
        "Dashboards interativos e publicação"
      ],
      topicos: [
        "Conexão e tratamento de dados (Power Query)",
        "Modelagem de dados e relacionamentos",
        "Medidas e fórmulas DAX",
        "Dashboards interativos e publicação"
      ],
      conteudosProgramaticos: [
        { titulo: "Dados e tratamento", duracao: "4h", aulas: [
          { titulo: "Visão geral do Power BI", tipo: "video", duracao: "14 min" },
          { titulo: "Power Query na prática", tipo: "video", duracao: "22 min" },
          { titulo: "Base de exemplo (material)", tipo: "pdf", duracao: "" }
        ]},
        { titulo: "Modelagem e DAX", duracao: "5h", aulas: [
          { titulo: "Relacionamentos e modelo", tipo: "video", duracao: "20 min" },
          { titulo: "Medidas em DAX", tipo: "video", duracao: "24 min" },
          { titulo: "Quiz do módulo", tipo: "quiz", duracao: "" }
        ]},
        { titulo: "Dashboards", duracao: "4h", aulas: [
          { titulo: "Construindo o dashboard", tipo: "video", duracao: "23 min" },
          { titulo: "Publicação e compartilhamento", tipo: "texto", duracao: "12 min" },
          { titulo: "Atividade: seu primeiro relatório", tipo: "atividade", duracao: "" }
        ]}
      ],
      instrutor: { nome: "Bruno Ferreira", iniciais: "BF", area: "Business Intelligence", bio: "Atua com BI, modelagem de dados e dashboards. Perfil fictício — conteúdo de demonstração da V2." },
      turmas: [
        { id: "online", nome: "Turma Online — Acesso imediato", modalidade: "Online", inicio: "Acesso imediato", vagas: null, local: null, preco: 199.0, formato: "Gravado" }
      ],
      pagina: "curso-direito-consumidor.html",
      progresso: 15
    },

    /* ---------- Eventos demonstrativos (tipo: "evento") ----------
       Edições ao vivo de cursos existentes. Datas, locais e vagas
       são fictícios. O CTA aponta para a ficha do curso-base. */
    {
      id: "evento-oficinas-ace", tipo: "evento", cursoBase: "oficinas-ace",
      titulo: "Oficinas de ACE — Encontro presencial", categoria: "Gestão", categoriaId: "negocios",
      modalidade: "Presencial", preco: 100.0,
      dataInicioDemo: "12/07/2026", dataFimDemo: "13/07/2026", localDemo: "Polo Rainbow — Lages/SC",
      vagasDemo: 18, statusDemo: "abertas", periodoDemo: "proximos", destaqueEvento: true,
      resumo: "Dois dias de oficina prática para desenvolver e apresentar seu projeto de extensão, com mentoria ao vivo.",
      descricao: "Encontro presencial demonstrativo das Oficinas de ACE."
    },
    {
      id: "evento-direito-consumidor", tipo: "evento", cursoBase: "direito-consumidor",
      titulo: "Direito do Consumidor — Encontro ao vivo", categoria: "Direito", categoriaId: "direito",
      modalidade: "Online", preco: 197.0,
      dataInicioDemo: "12/08/2026", dataFimDemo: null, localDemo: "Online (ao vivo)",
      vagasDemo: 24, statusDemo: "ultimas", periodoDemo: "proximos", destaqueEvento: false,
      resumo: "Aula ao vivo com estudos de caso e tira-dúvidas sobre direitos do consumidor na prática.",
      descricao: "Encontro online demonstrativo do curso de Direito do Consumidor."
    },
    {
      id: "evento-gestao-projetos", tipo: "evento", cursoBase: "gestao-projetos",
      titulo: "Gestão de Projetos — Workshop prático", categoria: "Gestão", categoriaId: "negocios",
      modalidade: "Híbrido", preco: 220.0,
      dataInicioDemo: "20/08/2026", dataFimDemo: null, localDemo: "Lages/SC + Online",
      vagasDemo: 30, statusDemo: "abertas", periodoDemo: "proximos", destaqueEvento: false,
      resumo: "Workshop com dinâmicas de planejamento, cronograma e métodos ágeis aplicados a um caso real.",
      descricao: "Workshop híbrido demonstrativo de Gestão de Projetos."
    },
    {
      id: "evento-marketing-digital", tipo: "evento", cursoBase: "marketing-digital",
      titulo: "Marketing Digital — Masterclass", categoria: "Marketing", categoriaId: "marketing",
      modalidade: "Online", preco: 247.0,
      dataInicioDemo: "05/09/2026", dataFimDemo: null, localDemo: "Online (ao vivo)",
      vagasDemo: 120, statusDemo: "abertas", periodoDemo: "embreve", destaqueEvento: false,
      resumo: "Masterclass ao vivo sobre tráfego, conteúdo e métricas, com perguntas ao final.",
      descricao: "Masterclass online demonstrativa de Marketing Digital."
    },
    {
      id: "evento-comunicacao", tipo: "evento", cursoBase: "comunicacao-oratoria",
      titulo: "Comunicação e Oratória — Imersão", categoria: "Comunicação", categoriaId: "desenvolvimento-pessoal",
      modalidade: "Presencial", preco: 129.0,
      dataInicioDemo: "18/09/2026", dataFimDemo: "19/09/2026", localDemo: "São Paulo/SP",
      vagasDemo: 0, statusDemo: "encerrado", periodoDemo: "embreve", destaqueEvento: false,
      resumo: "Imersão presencial de dois dias com exercícios de fala em público e storytelling.",
      descricao: "Imersão presencial demonstrativa de Comunicação e Oratória."
    }
  ];

  /* ============================================================
     Área do Aluno (V2) — dados demonstrativos da usuária fictícia.
     Nada vem do sistema real. Referencia cursos por id.
     ============================================================ */
  var ALUNO = {
    perfil: {
      nome: "Ana Souza",
      email: "ana.souza@exemplo.com",
      perfil: "Aluna",
      iniciais: "AS",
      cpf: "123.456.789-09",
      telefone: "(49) 99999-0000",
      cidade: "Lages",
      estado: "SC"
    },
    continueAprendendo: {
      cursoId: "marketing-digital",
      progresso: 75,
      ultimaAula: "Tráfego pago: conceitos e estratégias",
      href: "/v2/aula/?curso=marketing-digital&aula=trafego-pago"
    },
    matriculas: [
      { cursoId: "marketing-digital", progresso: 75, status: "andamento", ultimaAtividade: "Há 2 dias" },
      { cursoId: "direito-consumidor", progresso: 35, status: "andamento", ultimaAtividade: "Há 5 dias" },
      { cursoId: "excel-negocios", progresso: 100, status: "concluido", ultimaAtividade: "Há 1 mês" },
      { cursoId: "power-bi", progresso: 0, status: "nao-iniciado", ultimaAtividade: "Ainda não iniciado" }
    ],
    pedidos: [
      { codigo: "#V2-2026-001", cursoId: "direito-consumidor", turma: "Turma Online — Acesso imediato", data: "02/06/2026", participantes: 1, total: 197.0, metodo: "PIX", status: "pago" },
      { codigo: "#V2-2026-002", cursoId: "excel-negocios", turma: "Turma Online — Acesso imediato", data: "18/05/2026", participantes: 1, total: 159.0, metodo: "Cartão", status: "pago" },
      { codigo: "#V2-2026-003", cursoId: "power-bi", turma: "Turma Online — Acesso imediato", data: "22/06/2026", participantes: 1, total: 199.0, metodo: "PIX", status: "aguardando" },
      { codigo: "#V2-2026-004", cursoId: "marketing-digital", turma: "Turma Online — Acesso imediato", data: "10/06/2026", participantes: 2, total: 494.0, metodo: "PIX", status: "cancelado" }
    ],
    certificados: [
      { cursoId: "excel-negocios", cargaHoraria: 28, dataEmissao: "30/05/2026", codigo: "DBC-EXC-2026-0421", status: "disponivel" },
      { cursoId: "marketing-digital", cargaHoraria: 32, dataEmissao: "—", codigo: "DBC-MKT-2026-0890", status: "analise", progresso: 75 },
      { cursoId: "direito-consumidor", cargaHoraria: 40, dataEmissao: null, codigo: null, status: "bloqueado", progresso: 35 }
    ],
    preferencias: { novidades: true, lembretes: true, temaClaro: true }
  };

  /* ============================================================
     Taxonomia de categorias V2 (hub /v2/categorias/).
     CATEGORIA_DE mapeia a categoria de exibição dos cursos para o
     id canônico da categoria.
     ============================================================ */
  var CATEGORIAS_INFO = [
    { id: "direito", nome: "Direito", icon: "ti-scale", cor: "#cc5500", g1: "#fff4ec", g2: "#ffe4d3", descricao: "Direitos, contratos e prática jurídica do dia a dia." },
    { id: "negocios", nome: "Negócios", icon: "ti-briefcase", cor: "#3b6d11", g1: "#eef7df", g2: "#dcefc0", descricao: "Gestão, projetos e empreendedorismo para crescer." },
    { id: "tecnologia", nome: "Tecnologia", icon: "ti-device-laptop", cor: "#1d4ed8", g1: "#e3f0ff", g2: "#cfe4ff", descricao: "Dados, planilhas e ferramentas digitais na prática." },
    { id: "marketing", nome: "Marketing", icon: "ti-speakerphone", cor: "#4B008E", g1: "#f0e8ff", g2: "#e4d6ff", descricao: "Campanhas, conteúdo e tráfego que geram resultado." },
    { id: "desenvolvimento-pessoal", nome: "Desenvolvimento Pessoal", icon: "ti-bulb", cor: "#c00057", g1: "#ffe9f0", g2: "#ffd6e3", descricao: "Comunicação, oratória e habilidades para a carreira." },
    { id: "educacao", nome: "Educação", icon: "ti-school", cor: "#007a6a", g1: "#d1faf5", g2: "#b8f2ea", descricao: "Formações e oficinas para ensinar e aprender melhor." }
  ];
  var CATEGORIA_DE = {
    "Direito": "direito",
    "Marketing": "marketing",
    "Gestão": "negocios",
    "Negócios": "negocios",
    "Tecnologia": "tecnologia",
    "Comunicação": "desenvolvimento-pessoal"
  };

  window.V2_CATEGORIAS = CATEGORIAS;
  window.V2_CURSOS = CURSOS;
  window.V2_ALUNO = ALUNO;
  window.V2_CATEGORIAS_INFO = CATEGORIAS_INFO;
  window.V2_CATEGORIA_DE = CATEGORIA_DE;
})();
