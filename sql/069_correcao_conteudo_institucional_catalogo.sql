-- Migration 069
-- Corrige conteudo publico de paginas institucionais e do catalogo de cursos.
-- Idempotente: cada UPDATE usa REPLACE() ou um WHERE que so casa com o valor antigo,
-- entao reexecutar este script apos a primeira aplicacao nao produz efeito colateral.
-- Faca backup do banco antes de aplicar (ver docs/deploy.md).

-- 1) Termos de Uso: remove a referencia a "Polo Rainbow" no resumo e o <h1> redundante
--    (o template ja imprime um <h1> proprio com o titulo da pagina).
UPDATE paginas
SET resumo = REPLACE(resumo, 'Regras de uso da plataforma Polo Rainbow.', 'Regras de uso da plataforma Desbloqueia Cursos.'),
    conteudo_html = REPLACE(conteudo_html, '<h1>Termos de Uso</h1>', ''),
    updated_at = NOW()
WHERE rota = '/termos-de-uso'
  AND deleted_at IS NULL;

-- 2) Quem Somos: rebaixa o <h1> interno ("Desbloqueia Cursos") para <h2>, preservando
--    o subtitulo em vez de deixar duas manchetes <h1> na mesma pagina.
UPDATE paginas
SET conteudo_html = REPLACE(
        conteudo_html,
        '<h1 class="quem-somos__titulo">Desbloqueia Cursos</h1>',
        '<h2 class="quem-somos__titulo">Desbloqueia Cursos</h2>'
    ),
    updated_at = NOW()
WHERE rota = '/quem-somos'
  AND deleted_at IS NULL;

-- 3) Onde Estamos: remove o <h1> redundante e distingue a unidade de atendimento em
--    Lages/SC da sede juridica da CP Educa Cursos LTDA em Correia Pinto/SC.
UPDATE paginas
SET conteudo_html = '<section class="page-content onde-estamos">
    <p>
        O <b>Desbloqueia Cursos</b> atende seus alunos e realiza cursos presenciais em Lages,
        Santa Catarina. A empresa mantenedora, <b>CP Educa Cursos LTDA</b>, tem sede jurídica
        registrada em Correia Pinto, Santa Catarina.
    </p>

    <div class="info-localizacao">
        <h2>Unidade de atendimento — Lages/SC</h2>

        <p>
            <b>Rua Correia Pinto, nº 534</b><br>
            Centro<br>
            Lages/SC
        </p>

        <p>
            Este é o endereço utilizado para atendimento aos alunos, atividades presenciais, cursos, encontros e demais ações vinculadas ao Desbloqueia Cursos.
        </p>
    </div>

    <div class="info-localizacao">
        <h2>Sede jurídica — Correia Pinto/SC</h2>

        <p>
            <b>Av. Vitória Régia, nº 1782</b><br>
            Bairro Pro Flor<br>
            Correia Pinto/SC — CEP 88.535-000
        </p>

        <p>
            Endereço de registro da CP Educa Cursos LTDA (CNPJ 65.513.089/0001-35), mantenedora do Desbloqueia Cursos. O atendimento a alunos e as atividades presenciais acontecem na unidade de Lages/SC, indicada acima.
        </p>
    </div>

    <div class="contato-localizacao">
        <h2>Contato</h2>

        <p>
            Para dúvidas sobre atendimento, cursos presenciais ou informações sobre a plataforma, entre em contato:
        </p>

        <ul>
            <li>
                <b>WhatsApp:</b>
                <a href="https://wa.me/5549991581411" target="_blank" rel="noopener">
                    +55 49 99158-1411
                </a>
            </li>
            <li>
                <b>E-mail:</b>
                <a href="mailto:desbloqueiacursos@gmail.com">
                    desbloqueiacursos@gmail.com
                </a>
            </li>
        </ul>
    </div>

    <div class="mapa-localizacao">
        <h2>Localização no mapa (unidade de atendimento em Lages)</h2>

        <p>
            Veja abaixo a localização aproximada da unidade de atendimento no Google Maps:
        </p>

        <div style="width: 100%; height: 420px; border-radius: 16px; overflow: hidden; border: 1px solid #ddd;">
            <iframe
                src="https://www.google.com/maps?q=Rua%20Correia%20Pinto%2C%20534%2C%20Centro%2C%20Lages%2C%20SC&output=embed"
                width="100%"
                height="420"
                style="border:0;"
                allowfullscreen=""
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade">
            </iframe>
        </div>

        <p style="margin-top: 16px;">
            <a href="https://www.google.com/maps/search/?api=1&query=Rua%20Correia%20Pinto%2C%20534%2C%20Centro%2C%20Lages%2C%20SC"
               target="_blank"
               rel="noopener">
                Abrir localização no Google Maps
            </a>
        </p>
    </div>
</section>
',
    updated_at = NOW()
WHERE rota = '/onde-estamos'
  AND deleted_at IS NULL;

-- 4) Politica de Privacidade: reescreve o conteudo para refletir o tratamento de dados
--    realmente realizado pelo sistema (pedidos, inscricoes, certificados, gateway de
--    pagamento, logs, cookies etc.) e remove o <h1> redundante.
UPDATE paginas
SET conteudo_html = '<section class="page-content politica-privacidade">
    <p>
        Esta Política de Privacidade apresenta, de forma clara e objetiva, como o
        <b>Desbloqueia Cursos</b> realiza o tratamento dos dados pessoais dos usuários
        que acessam e utilizam a plataforma.
    </p>

    <p>
        O Desbloqueia Cursos é operado por <b>CP EDUCA CURSOS LTDA</b>
        (controlador dos dados pessoais, nos termos da LGPD),
        pessoa jurídica inscrita no CNPJ sob nº <b>65.513.089/0001-35</b>,
        com sede na <b>Av. Vitória Régia, nº 1782, Bairro Pro Flor,
        Correia Pinto/SC, CEP 88.535-000</b>.
    </p>

    <p>
        Em caso de dúvidas, solicitações ou exercício de direitos relacionados aos seus dados
        pessoais, você pode entrar em contato pelo e-mail
        <a href="mailto:desbloqueiacursos@gmail.com">desbloqueiacursos@gmail.com</a>
        ou pelo telefone/WhatsApp <a href="tel:+5549991581411">+55 49 99158-1411</a>.
    </p>

    <h2>1. Dados pessoais coletados</h2>

    <p>
        O Desbloqueia Cursos coleta os dados necessários para identificação, cadastro,
        inscrição em cursos/turmas e emissão de documentos vinculados à participação do
        usuário na plataforma.
    </p>

    <p><b>No cadastro da conta</b>, são coletados:</p>
    <ul>
        <li>Nome completo;</li>
        <li>Endereço de e-mail;</li>
        <li>CPF;</li>
        <li>Telefone;</li>
        <li>Cidade;</li>
        <li>Estado (UF);</li>
        <li>Senha de acesso (armazenada de forma criptografada, nunca em texto plano);</li>
        <li>Preferência/aceite de comunicações, quando aplicável.</li>
    </ul>

    <p><b>Ao longo do uso da plataforma</b>, também podem ser tratados:</p>
    <ul>
        <li>Dados de pedidos, itens de pedido e cupons utilizados;</li>
        <li>Dados de participantes vinculados a um pedido (quando a inscrição é feita para terceiros);</li>
        <li>Inscrições e turmas em que o usuário está matriculado;</li>
        <li>Progresso acadêmico (aulas e módulos concluídos, presença, quando exigida pelo curso);</li>
        <li>Respostas de avaliações, quizzes e atividades enviadas nos cursos;</li>
        <li>Certificados emitidos e respectivo código de validação;</li>
        <li>Comprovante de pagamento (PIX) anexado pelo pagador, quando o pedido exigir análise manual;</li>
        <li>Dados da transação de pagamento processados pelo gateway de pagamento (ver seção 4);</li>
        <li>Registros técnicos de acesso e auditoria (data/hora, ação realizada, endereço IP), para segurança e rastreabilidade de operações administrativas;</li>
        <li>Histórico de e-mails transacionais enviados pela plataforma (confirmação de cadastro, pedido, recuperação de senha, avisos de turma, entre outros).</li>
    </ul>

    <p>
        O Desbloqueia Cursos não solicita, em seu cadastro regular, dados sensíveis,
        número de cartão de crédito ou dados biométricos, exceto quando estritamente
        necessário para confirmação de identidade em solicitações específicas feitas
        pelo próprio usuário (por exemplo, para processar um pedido de remoção de conta).
    </p>

    <h2>2. Finalidade da coleta dos dados</h2>

    <p>
        Os dados pessoais informados são utilizados para as seguintes finalidades:
    </p>

    <ul>
        <li>Realizar o cadastro do usuário na plataforma e permitir o login (por e-mail ou CPF);</li>
        <li>Permitir o acesso à área do usuário e aos cursos disponíveis;</li>
        <li>Processar pedidos, inscrições e pagamentos, inclusive a recuperação de pedidos não concluídos;</li>
        <li>Identificar corretamente o usuário e os participantes vinculados a um pedido;</li>
        <li>Registrar progresso acadêmico e viabilizar a emissão de certificados, quando aplicável;</li>
        <li>Emitir comprovantes e permitir a validação pública de certificados por terceiros interessados (empregadores, instituições), mediante código e dados do titular;</li>
        <li>Enviar e-mails transacionais sobre a conta, o pedido, a inscrição ou informações administrativas da plataforma;</li>
        <li>Manter logs de acesso e auditoria para segurança da informação e apuração de incidentes;</li>
        <li>Cumprir obrigações legais, regulatórias, fiscais ou administrativas, quando necessário.</li>
    </ul>

    <h2>3. Base legal para o tratamento</h2>

    <p>
        O tratamento dos dados pessoais é realizado de acordo com a Lei Geral de Proteção
        de Dados Pessoais — LGPD (Lei nº 13.709/2018), com base, principalmente, em:
    </p>

    <ul>
        <li>Execução de contrato ou de procedimentos preliminares, para viabilizar cadastro, inscrição, pagamento e acesso aos cursos;</li>
        <li>Cumprimento de obrigação legal ou regulatória, quando exigido;</li>
        <li>Legítimo interesse do controlador, para segurança da plataforma, prevenção a fraude e auditoria de operações administrativas;</li>
        <li>Consentimento do titular, quando aplicável ao envio de comunicações opcionais.</li>
    </ul>

    <h2>4. Compartilhamento de dados e operadores</h2>

    <p>
        O Desbloqueia Cursos não vende, aluga ou comercializa dados pessoais dos usuários.
        Os dados podem ser compartilhados apenas com operadores estritamente necessários
        à operação da plataforma, ou quando exigido por lei:
    </p>

    <ul>
        <li><b>Gateway de pagamento (AbacatePay):</b> ao gerar cobranças e processar pagamentos on-line, os dados necessários à transação (identificação do pagador e dados do pedido) são compartilhados com o gateway de pagamento contratado;</li>
        <li><b>Envio de e-mails:</b> os e-mails transacionais são enviados por infraestrutura de SMTP própria/contratada para esse fim, sem uso de plataformas externas de marketing por e-mail;</li>
        <li><b>Hospedagem:</b> os dados são armazenados em servidor de hospedagem contratado para a operação da plataforma, com acesso restrito à equipe técnica responsável;</li>
        <li>Cumprimento de obrigação legal ou ordem de autoridade competente;</li>
        <li>Proteção dos direitos do Desbloqueia Cursos, dos usuários ou de terceiros.</li>
    </ul>

    <p>
        <em>Pendência administrativa/jurídica:</em> a designação formal de um encarregado
        pelo tratamento de dados (DPO) e a eventual contratação de fornecedores adicionais
        devem ser documentadas e comunicadas nesta política assim que formalizadas.
    </p>

    <h2>5. Armazenamento e segurança dos dados</h2>

    <p>
        O Desbloqueia Cursos adota medidas técnicas e administrativas para proteger os
        dados pessoais contra acessos não autorizados, perda, alteração, divulgação
        indevida ou uso inadequado. Entre essas medidas:
    </p>

    <ul>
        <li>Senhas armazenadas de forma criptografada, nunca em texto plano;</li>
        <li>Arquivos sensíveis (comprovantes de pagamento, certificados, materiais de curso) ficam fora da área pública do servidor e só são acessíveis por rotas controladas, nunca por link direto;</li>
        <li>Acesso administrativo controlado por perfis e permissões (RBAC): professores, por exemplo, não têm acesso a comprovantes de pagamento;</li>
        <li>Exclusões relevantes passam por um mecanismo de lixeira com justificativa obrigatória e registro em log de auditoria, permitindo rastrear quem alterou ou removeu um dado e por quê.</li>
    </ul>

    <h2>6. Cookies e registros técnicos</h2>

    <p>
        O Desbloqueia Cursos utiliza apenas o cookie de sessão necessário para manter o
        usuário autenticado durante a navegação (login). Esse cookie é técnico/essencial,
        não é utilizado para publicidade, rastreamento entre sites ou perfis de navegação,
        e é removido ao encerrar a sessão (logout).
    </p>

    <p>
        Não há, no momento, cookies de terceiros, ferramentas de analytics ou pixels de
        publicidade instalados na plataforma.
    </p>

    <h2>7. Prazo de conservação dos dados</h2>

    <p>
        Os dados pessoais serão mantidos pelo tempo necessário para cumprir as finalidades
        descritas nesta Política de Privacidade, inclusive para manutenção da conta do
        usuário, histórico de pedidos e inscrições, emissão e validação de certificados,
        cumprimento de obrigações legais/fiscais e proteção de direitos.
    </p>

    <p>
        Quando os dados não forem mais necessários, poderão ser excluídos ou anonimizados,
        observadas as obrigações legais aplicáveis e o prazo mínimo de guarda de
        documentos fiscais e contábeis, quando pertinente.
    </p>

    <h2>8. Dados de crianças e adolescentes</h2>

    <p>
        O cadastro na plataforma é destinado a maiores de 18 anos ou a menores devidamente
        assistidos ou representados por seus responsáveis legais, conforme exigido em lei.
        Cursos ou turmas eventualmente direcionados a público infantojuvenil são
        conduzidos com o consentimento e o acompanhamento do responsável legal, que é
        também o titular da conta utilizada para a inscrição.
    </p>

    <h2>9. Direitos do titular dos dados</h2>

    <p>
        O usuário, como titular dos dados pessoais, pode solicitar:
    </p>

    <ul>
        <li>Confirmação da existência de tratamento de seus dados;</li>
        <li>Acesso aos dados pessoais cadastrados;</li>
        <li>Correção de dados incompletos, inexatos ou desatualizados;</li>
        <li>Exclusão dos dados, quando aplicável;</li>
        <li>Informações sobre eventual compartilhamento de dados;</li>
        <li>Revogação de consentimento, quando o tratamento depender de consentimento.</li>
    </ul>

    <p>
        As solicitações devem ser enviadas para
        <a href="mailto:desbloqueiacursos@gmail.com">desbloqueiacursos@gmail.com</a>.
    </p>

    <h2>10. Remoção de conta</h2>

    <p>
        O usuário pode solicitar a remoção de sua conta enviando um e-mail para
        <a href="mailto:desbloqueiacursos@gmail.com">desbloqueiacursos@gmail.com</a>,
        preferencialmente a partir do mesmo endereço de e-mail cadastrado no Desbloqueia Cursos.
    </p>

    <p>
        Caso o usuário não tenha mais acesso ao e-mail cadastrado, poderá ser solicitada
        uma forma adicional de confirmação de identidade, como fotografia de documento válido,
        exclusivamente para verificar a titularidade da conta e processar o pedido de remoção.
    </p>

    <p>
        Também é possível solicitar a remoção acessando o Desbloqueia Cursos, clicando em
        <b>Minha Conta</b>, no topo direito da página, no ícone de uma pessoa,
        e depois clicando no link <b>Remover Conta</b>.
    </p>

    <p>
        Registros que precisem ser preservados por obrigação legal (por exemplo, documentos
        fiscais de um pedido pago) podem ser mantidos mesmo após a remoção da conta, pelo
        prazo exigido em lei.
    </p>

    <h2>11. Canal de atendimento</h2>

    <ul>
        <li>
            E-mail:
            <a href="mailto:desbloqueiacursos@gmail.com">desbloqueiacursos@gmail.com</a>
        </li>
        <li>
            Telefone/WhatsApp:
            <a href="tel:+5549991581411">+55 49 99158-1411</a>
        </li>
    </ul>

    <h2>12. Alterações nesta Política de Privacidade</h2>

    <p>
        Esta Política de Privacidade poderá ser atualizada para refletir melhorias na
        plataforma, alterações legais ou ajustes nos procedimentos internos do
        Desbloqueia Cursos. Recomenda-se que o usuário consulte esta página
        periodicamente para verificar eventuais atualizações.
    </p>

    <p>
        Este documento é uma política de privacidade operacional e não substitui
        aconselhamento jurídico específico.
    </p>

    <p>
        <b>Versão:</b> 2 — <b>Última atualização:</b> julho de 2026.
    </p>
</section>
',
    updated_at = NOW()
WHERE rota = '/politica-de-privacidade'
  AND deleted_at IS NULL;

-- 5) Corrige o titulo do curso com erro de grafia (confirmado no registro real, id 6).
--    O WHERE casa apenas com o titulo antigo, entao reexecutar este script e seguro.
--    O slug ('oratoria') nao muda: nenhuma URL publica e quebrada.
UPDATE cursos_eventos
SET nome = 'Oratória: da Retórica à Dialética',
    updated_at = NOW()
WHERE nome = 'Oratória: da Retória a Dialética'
  AND deleted_at IS NULL;
