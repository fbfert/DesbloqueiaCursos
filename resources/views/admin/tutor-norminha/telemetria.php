<?php
use App\Core\Helpers;

/**
 * Painel de telemetria da Norminha.
 *
 * Sem biblioteca de gráfico: barras em CSS puro, como o resto do admin. O que
 * decide a Onda 1 é a LISTA DE PERGUNTAS, e lista não precisa de gráfico.
 *
 * O texto da pergunta é do aluno — conteúdo NÃO CONFIÁVEL. Todo ele passa por
 * Helpers::e(). Nada aqui é renderizado como HTML.
 */

$dias = isset($dias) ? (int) $dias : 14;
$panorama = isset($panorama) && is_array($panorama) ? $panorama : array();
$perguntas = isset($perguntas) && is_array($perguntas) ? $perguntas : array();
$cursosComDuvida = isset($cursosComDuvida) && is_array($cursosComDuvida) ? $cursosComDuvida : array();
$cursoId = isset($cursoId) ? $cursoId : null;

$volume = isset($panorama['volume']) ? $panorama['volume'] : array();
$resolucao = isset($panorama['resolucao']) ? $panorama['resolucao'] : array();
$intencoes = isset($panorama['intencoes']) ? $panorama['intencoes'] : array();
$feedback = isset($panorama['feedback']) ? $panorama['feedback'] : array();
$bloqueios = isset($panorama['bloqueios']) ? $panorama['bloqueios'] : array();

$pctUnresolved = isset($resolucao['percentual']['unresolved']) ? (float) $resolucao['percentual']['unresolved'] : 0.0;
$totalResolucao = isset($resolucao['total']) ? (int) $resolucao['total'] : 0;

$rotulosIntencao = array(
    'resume_course' => 'Continuar de onde parei',
    'show_progress' => 'Ver meu progresso',
    'next_step' => 'Próximo passo',
    'certificate_status' => 'Situação do certificado',
    'explain_current_lesson' => 'Explicar a aula',
    'summarize_current_lesson' => 'Resumir a aula',
);
$rotuloIntencao = function ($chave) use ($rotulosIntencao) {
    return isset($rotulosIntencao[$chave]) ? $rotulosIntencao[$chave] : (string) $chave;
};
?>

<style>
.nt-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 12px; margin: 16px 0; }
.nt-card { border: 1px solid #e4e4e7; border-radius: 10px; padding: 14px; background: #fff; }
.nt-card__valor { font-size: 1.7rem; font-weight: 700; line-height: 1.1; }
.nt-card__rotulo { font-size: 0.78rem; color: #71717a; margin-top: 4px; }
.nt-card--alerta { border-color: #f59e0b; background: #fffbeb; }
.nt-barra { height: 10px; border-radius: 999px; background: #f4f4f5; overflow: hidden; display: flex; }
.nt-barra__parte { height: 100%; }
.nt-barra__php { background: #22c55e; }
.nt-barra__unresolved { background: #f59e0b; }
.nt-legenda { display: flex; gap: 14px; flex-wrap: wrap; font-size: 0.8rem; margin-top: 8px; }
.nt-legenda__cor { display: inline-block; width: 10px; height: 10px; border-radius: 2px; margin-right: 5px; }
.nt-tabela { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.nt-tabela th, .nt-tabela td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #f0f0f2; vertical-align: top; }
.nt-tabela th { font-size: 0.75rem; text-transform: uppercase; color: #71717a; }
.nt-pergunta { font-weight: 500; }
.nt-meta { font-size: 0.75rem; color: #71717a; }
.nt-vazio { padding: 24px; text-align: center; color: #71717a; border: 1px dashed #d4d4d8; border-radius: 10px; }
.nt-nota { font-size: 0.82rem; color: #52525b; background: #fafafa; border-left: 3px solid #d4d4d8; padding: 10px 12px; margin: 12px 0; }
@media (max-width: 700px) { .nt-tabela th:nth-child(3), .nt-tabela td:nth-child(3) { display: none; } }
</style>

<h1>Norminha — telemetria</h1>

<form method="get" action="/admin/tutor-norminha/telemetria" style="margin: 12px 0;">
    <label for="nt-dias">Período:</label>
    <select id="nt-dias" name="dias" onchange="this.form.submit()">
        <?php foreach (array(7, 14, 30, 60, 90) as $opcao): ?>
            <option value="<?php echo $opcao; ?>" <?php echo $dias === $opcao ? 'selected' : ''; ?>>
                últimos <?php echo $opcao; ?> dias
            </option>
        <?php endforeach; ?>
    </select>
    <?php if ($cursoId): ?>
        <input type="hidden" name="curso_id" value="<?php echo (int) $cursoId; ?>">
        <a href="/admin/tutor-norminha/telemetria?dias=<?php echo $dias; ?>">limpar filtro de curso</a>
    <?php endif; ?>
</form>

<div class="nt-grid">
    <div class="nt-card">
        <div class="nt-card__valor"><?php echo (int) ($volume['alunos'] ?? 0); ?></div>
        <div class="nt-card__rotulo">alunos que conversaram</div>
    </div>
    <div class="nt-card">
        <div class="nt-card__valor"><?php echo (int) ($volume['mensagens'] ?? 0); ?></div>
        <div class="nt-card__rotulo">perguntas enviadas</div>
    </div>
    <div class="nt-card">
        <div class="nt-card__valor"><?php echo Helpers::e(number_format((float) ($volume['mensagens_por_aluno'] ?? 0), 1, ',', '.')); ?></div>
        <div class="nt-card__rotulo">perguntas por aluno ativo</div>
    </div>
    <div class="nt-card">
        <div class="nt-card__valor"><?php echo (int) ($volume['conversas'] ?? 0); ?></div>
        <div class="nt-card__rotulo">conversas abertas</div>
    </div>
    <div class="nt-card <?php echo $pctUnresolved >= 40 ? 'nt-card--alerta' : ''; ?>">
        <div class="nt-card__valor"><?php echo Helpers::e(number_format($pctUnresolved, 1, ',', '.')); ?>%</div>
        <div class="nt-card__rotulo">não resolvidas pelo sistema</div>
    </div>
    <div class="nt-card <?php echo (int) ($bloqueios['total'] ?? 0) > 0 ? 'nt-card--alerta' : ''; ?>">
        <div class="nt-card__valor"><?php echo (int) ($bloqueios['total'] ?? 0); ?></div>
        <div class="nt-card__rotulo">bloqueios por limite (<?php echo (int) ($bloqueios['alunos'] ?? 0); ?> alunos)</div>
    </div>
</div>

<h2>Como as respostas foram resolvidas</h2>
<?php if ($totalResolucao === 0): ?>
    <div class="nt-vazio">Nenhuma resposta no período.</div>
<?php else: ?>
    <div class="nt-barra">
        <div class="nt-barra__parte nt-barra__php" style="width: <?php echo (float) ($resolucao['percentual']['php'] ?? 0); ?>%"></div>
        <div class="nt-barra__parte nt-barra__unresolved" style="width: <?php echo (float) ($resolucao['percentual']['unresolved'] ?? 0); ?>%"></div>
    </div>
    <div class="nt-legenda">
        <span><span class="nt-legenda__cor nt-barra__php"></span>
            Resolvida pelo sistema: <?php echo (int) ($resolucao['contagem']['php'] ?? 0); ?>
            (<?php echo Helpers::e(number_format((float) ($resolucao['percentual']['php'] ?? 0), 1, ',', '.')); ?>%)</span>
        <span><span class="nt-legenda__cor nt-barra__unresolved"></span>
            Não resolvida: <?php echo (int) ($resolucao['contagem']['unresolved'] ?? 0); ?>
            (<?php echo Helpers::e(number_format($pctUnresolved, 1, ',', '.')); ?>%)</span>
    </div>
    <p class="nt-nota">
        Na Onda 0 só existem estes dois valores: a Norminha ainda não usa IA. A fatia laranja é
        o tamanho do problema que a camada de IA resolveria — mas o percentual sozinho não decide
        nada. O que decide é a natureza das perguntas, na lista abaixo.
    </p>
<?php endif; ?>

<h2>O que os atalhos resolveram</h2>
<?php if (!$intencoes): ?>
    <div class="nt-vazio">Nenhuma ação determinística no período.</div>
<?php else: ?>
    <table class="nt-tabela">
        <thead><tr><th>Ação</th><th>Vezes</th></tr></thead>
        <tbody>
        <?php foreach ($intencoes as $linha): ?>
            <tr>
                <td><?php echo Helpers::e($rotuloIntencao($linha['intencao'])); ?></td>
                <td><?php echo (int) $linha['n']; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<h2>Perguntas que o sistema não resolveu</h2>
<p class="nt-nota">
    <strong>Esta lista é a decisão.</strong> Leia as perguntas e classifique cada uma: é dúvida de
    <em>conteúdo</em> ("não entendi este conceito") ou de <em>navegação e suporte</em> ("como emito
    a segunda via")? Se a maioria for navegação, o caminho certo é escrever mais um atalho
    determinístico — mais barato, mais rápido e sem risco de resposta inventada. Ligar a IA só se
    justifica quando a maioria for conteúdo.
</p>

<?php if ($cursosComDuvida): ?>
    <p class="nt-meta">Cursos com mais dúvidas:
        <?php foreach ($cursosComDuvida as $c): ?>
            <a href="/admin/tutor-norminha/telemetria?dias=<?php echo $dias; ?>&amp;curso_id=<?php echo (int) $c['curso_evento_id']; ?>"><?php echo Helpers::e($c['curso_nome'] !== null ? $c['curso_nome'] : ('curso ' . $c['curso_evento_id'])); ?></a>
            (<?php echo (int) $c['n']; ?>)<?php echo $c === end($cursosComDuvida) ? '' : ' ·'; ?>
        <?php endforeach; ?>
    </p>
<?php endif; ?>

<?php if (!$perguntas): ?>
    <div class="nt-vazio">Nenhuma pergunta não resolvida no período. Ou o sistema está dando conta, ou ninguém está perguntando — compare com o número de alunos ativos acima.</div>
<?php else: ?>
    <table class="nt-tabela">
        <thead>
            <tr><th>Pergunta do aluno</th><th>Onde</th><th>Quando</th></tr>
        </thead>
        <tbody>
        <?php foreach ($perguntas as $p): ?>
            <tr>
                <td class="nt-pergunta">
                    <?php // Texto do aluno: conteudo nao confiavel, sempre escapado. ?>
                    <?php echo Helpers::e($p['pergunta'] !== null ? $p['pergunta'] : '(sem texto)'); ?>
                </td>
                <td class="nt-meta">
                    <?php echo Helpers::e($p['curso_nome'] !== null ? $p['curso_nome'] : '—'); ?><br>
                    <?php echo Helpers::e((string) $p['contexto']); ?>
                    <?php if (!empty($p['rota'])): ?> · <?php echo Helpers::e((string) $p['rota']); ?><?php endif; ?>
                </td>
                <td class="nt-meta">
                    <?php echo Helpers::e((string) $p['created_at']); ?><br>
                    aluno #<?php echo (int) $p['usuario_id']; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p class="nt-meta">Mostrando até 50 perguntas mais recentes do período.</p>
<?php endif; ?>

<h2>Feedback dos alunos</h2>
<?php if ((int) ($feedback['total'] ?? 0) === 0): ?>
    <div class="nt-vazio">Nenhum voto no período.</div>
<?php else: ?>
    <p>
        <strong><?php echo Helpers::e(number_format((float) $feedback['percentual_util'], 1, ',', '.')); ?>%</strong>
        acharam útil (<?php echo (int) $feedback['uteis']; ?> de <?php echo (int) $feedback['total']; ?> votos).
    </p>
    <?php if ((float) ($feedback['cobertura'] ?? 0) < 10): ?>
        <p class="nt-nota">
            Só <?php echo Helpers::e(number_format((float) $feedback['cobertura'], 1, ',', '.')); ?>% das
            respostas receberam voto. Com essa amostra, o percentual acima é ruído — trate como
            indício, não como medida.
        </p>
    <?php endif; ?>
<?php endif; ?>

<p style="margin-top: 24px;">
    <a href="/admin/tutor-norminha">Voltar às falas da Norminha</a>
</p>
