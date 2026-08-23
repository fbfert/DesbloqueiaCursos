<?php
use App\Core\Helpers;

/**
 * Norminha — inteligência artificial.
 *
 * O QUE ESTA TELA NUNCA FAZ: devolver a chave da OpenAI ao navegador, nem
 * parcialmente. Um prefixo já é informação para quem chegou aqui sem dever. Por
 * isso o campo chega sempre vazio, e "está configurada?" é respondido por
 * origem + botão de teste — que diz algo melhor que quatro dígitos: se funciona.
 *
 * Como o campo chega vazio a cada carregamento, salvar com ele em branco NÃO
 * apaga a chave guardada. Para remover existe uma caixa explícita.
 */

$origemChave = isset($origem_chave) ? (string) $origem_chave : 'nenhuma';
$origemModelo = isset($origem_modelo) ? (string) $origem_modelo : 'banco';
$podeGuardar = !empty($pode_guardar);
$modeloAtual = isset($modelo) ? (string) $modelo : '';
$catalogo = isset($catalogo) && is_array($catalogo) ? $catalogo : array();
$tetoMensal = isset($teto_mensal) ? (float) $teto_mensal : 0.0;
$tetoEstado = isset($teto) && is_array($teto) ? $teto : array();
$consumo = isset($consumo) && is_array($consumo) ? $consumo : array();
$porModelo = isset($por_modelo) && is_array($por_modelo) ? $por_modelo : array();
$iaAtiva = !empty($ia_ativa);
$conferidoEm = isset($precos_conferidos_em) ? (string) $precos_conferidos_em : '';
$teste = isset($testeResultado) && is_array($testeResultado) ? $testeResultado : null;

$usd = function ($v) {
    return $v === null ? '—' : 'US$ ' . number_format((float) $v, (abs((float) $v) < 1 && (float) $v != 0) ? 4 : 2, ',', '.');
};
$num = function ($v) {
    return number_format((int) $v, 0, ',', '.');
};

$chaveOk = $origemChave === 'env' || $origemChave === 'banco';
$pronta = $chaveOk && $modeloAtual !== '';
?>
<style>
.ni-aviso { border-radius: 10px; padding: 14px 16px; margin: 14px 0; border: 1px solid; font-size: 0.9rem; }
.ni-aviso--ok { border-color: #86efac; background: #f0fdf4; color: #14532d; }
.ni-aviso--atencao { border-color: #fcd34d; background: #fffbeb; color: #78350f; }
.ni-aviso--erro { border-color: #fca5a5; background: #fef2f2; color: #7f1d1d; }
.ni-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; margin: 16px 0; }
.ni-card { border: 1px solid #e4e4e7; border-radius: 10px; padding: 14px; background: #fff; }
.ni-card__valor { font-size: 1.55rem; font-weight: 700; line-height: 1.15; }
.ni-card__rotulo { font-size: 0.76rem; color: #71717a; margin-top: 4px; }
.ni-barra { height: 12px; border-radius: 999px; background: #f4f4f5; overflow: hidden; margin: 8px 0 4px; }
.ni-barra__parte { height: 100%; background: #22c55e; }
.ni-barra__parte--alto { background: #f59e0b; }
.ni-barra__parte--estourado { background: #ef4444; }
.ni-tabela { width: 100%; border-collapse: collapse; font-size: 0.86rem; }
.ni-tabela th, .ni-tabela td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #f0f0f2; }
.ni-tabela th { font-size: 0.74rem; text-transform: uppercase; color: #71717a; }
.ni-tabela td.num, .ni-tabela th.num { text-align: right; font-variant-numeric: tabular-nums; }
.ni-modelo { display: block; border: 1px solid #e4e4e7; border-radius: 10px; padding: 12px 14px; margin-bottom: 8px; cursor: pointer; }
.ni-modelo:hover { border-color: #a1a1aa; }
.ni-modelo input { margin-right: 8px; }
.ni-modelo__topo { display: flex; justify-content: space-between; gap: 12px; align-items: baseline; flex-wrap: wrap; }
.ni-modelo__nome { font-weight: 600; }
.ni-modelo__faixa { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.04em; color: #71717a; }
.ni-modelo__preco { font-size: 0.8rem; color: #3f3f46; font-variant-numeric: tabular-nums; }
.ni-modelo__nota { font-size: 0.8rem; color: #52525b; margin-top: 6px; }
.ni-selo { display: inline-block; font-size: 0.68rem; background: #dcfce7; color: #14532d; border-radius: 999px; padding: 2px 8px; margin-left: 6px; }
.ni-provedor { margin-top: 8px; padding: 8px 10px; border-radius: 8px; background: rgba(0,0,0,0.05); font-size: 0.82rem; }
.ni-provedor span { display: block; margin-top: 2px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
.ni-nota { font-size: 0.82rem; color: #52525b; background: #fafafa; border-left: 3px solid #d4d4d8; padding: 10px 12px; margin: 12px 0; }
.ni-secao { margin: 26px 0; }
.ni-campo { margin: 14px 0; }
.ni-campo label { display: block; font-weight: 600; font-size: 0.86rem; margin-bottom: 4px; }
.ni-campo input[type="text"], .ni-campo input[type="password"], .ni-campo input[type="number"] { width: 100%; max-width: 460px; padding: 8px 10px; border: 1px solid #d4d4d8; border-radius: 8px; }
.ni-ajuda { font-size: 0.8rem; color: #71717a; margin-top: 4px; }
</style>

<h1>Norminha — inteligência artificial</h1>

<?php if (!empty($success)): ?>
    <div class="ni-aviso ni-aviso--ok"><?php echo Helpers::e($success); ?></div>
<?php endif; ?>
<?php if (!empty($errors)): ?>
    <div class="ni-aviso ni-aviso--erro">
        <?php foreach ((array) $errors as $erro): ?>
            <div><?php echo Helpers::e(is_array($erro) ? implode(' ', $erro) : (string) $erro); ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (!$podeGuardar): ?>
    <div class="ni-aviso ni-aviso--erro">
        <strong>APP_KEY não está definida no <code>.env</code>.</strong>
        Sem ela nenhum segredo pode ser cifrado, e o que você digitar aqui seria
        descartado em silêncio — foi exatamente o que aconteceu com a credencial
        do gateway de pagamento, que passou meses parecendo salva. Defina
        APP_KEY antes de configurar a chave.
    </div>
<?php endif; ?>

<?php if ($origemChave === 'ilegivel'): ?>
    <div class="ni-aviso ni-aviso--erro">
        Existe uma chave guardada, mas ela não pode ser lida: a APP_KEY do
        <code>.env</code> mudou depois de gravá-la. Informe a chave de novo.
    </div>
<?php endif; ?>

<?php /* Verde so quando esta pronta E ligada: pintar de verde um estado em
         que nenhuma chamada acontece faria a tela dizer "tudo certo" sobre
         uma IA que esta parada. */ ?>
<div class="ni-aviso <?php echo ($pronta && $iaAtiva) ? 'ni-aviso--ok' : 'ni-aviso--atencao'; ?>">
    <?php if ($pronta && $iaAtiva): ?>
        A IA está <strong>ligada e configurada</strong>. Perguntas livres dos alunos vão para o modelo.
    <?php elseif ($pronta): ?>
        Credencial e modelo estão prontos, mas a IA está <strong>desligada</strong> em
        <a href="/admin/tutor-norminha/configuracoes">Configurações</a>. Nenhuma chamada é feita.
    <?php elseif (!$chaveOk): ?>
        Sem chave configurada. A Norminha responde só o que é determinístico —
        progresso, retomada e certificado — e diz "ainda não consigo responder isso" ao resto.
    <?php else: ?>
        Chave presente, mas <strong>nenhum modelo escolhido</strong>. Escolha um abaixo.
    <?php endif; ?>
</div>

<?php if ($teste !== null): ?>
    <div class="ni-aviso <?php echo !empty($teste['ok']) ? 'ni-aviso--ok' : 'ni-aviso--erro'; ?>">
        <?php if (!empty($teste['ok'])): ?>
            <strong>A chave funciona.</strong>
            Modelo que respondeu: <code><?php echo Helpers::e((string) $teste['modelo']); ?></code>.
            <?php if (!empty($teste['latencia_ms'])): ?>
                Levou <?php echo (int) $teste['latencia_ms']; ?> ms.
            <?php endif; ?>
            Este teste custou <?php echo Helpers::e($usd(isset($teste['custo']) ? $teste['custo'] : null)); ?>.
        <?php else: ?>
            <strong>O teste falhou.</strong>
            <?php echo Helpers::e((string) (isset($teste['mensagem']) ? $teste['mensagem'] : 'Sem detalhe.')); ?>
            <?php if (!empty($teste['codigo'])): ?>
                (<code><?php echo Helpers::e((string) $teste['codigo']); ?></code><?php echo !empty($teste['status']) ? ', HTTP ' . (int) $teste['status'] : ''; ?>)
            <?php endif; ?>
            <?php if (!empty($teste['provedor'])): ?>
                <?php /* Texto do provedor, cru. E o que diz o que fazer. */ ?>
                <div class="ni-provedor">
                    <strong>A OpenAI respondeu:</strong>
                    <span><?php echo Helpers::e((string) $teste['provedor']); ?></span>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="ni-secao">
    <h2>Consumo do mês</h2>
    <div class="ni-grid">
        <div class="ni-card">
            <div class="ni-card__valor"><?php echo Helpers::e($usd(isset($consumo['custo_mes']) ? $consumo['custo_mes'] : 0)); ?></div>
            <div class="ni-card__rotulo">no mês · <?php echo $num(isset($consumo['respostas_mes']) ? $consumo['respostas_mes'] : 0); ?> respostas</div>
        </div>
        <div class="ni-card">
            <div class="ni-card__valor"><?php echo Helpers::e($usd(isset($consumo['custo_semana']) ? $consumo['custo_semana'] : 0)); ?></div>
            <div class="ni-card__rotulo">7 dias · <?php echo $num(isset($consumo['respostas_semana']) ? $consumo['respostas_semana'] : 0); ?> respostas</div>
        </div>
        <div class="ni-card">
            <div class="ni-card__valor"><?php echo Helpers::e($usd(isset($consumo['custo_hoje']) ? $consumo['custo_hoje'] : 0)); ?></div>
            <div class="ni-card__rotulo">hoje · <?php echo $num(isset($consumo['respostas_hoje']) ? $consumo['respostas_hoje'] : 0); ?> respostas</div>
        </div>
        <div class="ni-card">
            <div class="ni-card__valor"><?php echo $num(isset($consumo['cache_mes']) ? $consumo['cache_mes'] : 0); ?></div>
            <div class="ni-card__rotulo">tokens de entrada vindos do cache (custam 10× menos)</div>
        </div>
    </div>

    <?php if ($tetoMensal > 0): ?>
        <?php
        $pct = isset($tetoEstado['percentual']) ? (float) $tetoEstado['percentual'] : 0.0;
        $classe = $pct >= 100 ? 'ni-barra__parte--estourado' : ($pct >= 75 ? 'ni-barra__parte--alto' : '');
        ?>
        <div class="ni-barra">
            <div class="ni-barra__parte <?php echo $classe; ?>" style="width: <?php echo (float) min(100, max(0, $pct)); ?>%"></div>
        </div>
        <div class="ni-ajuda">
            <?php echo Helpers::e($usd(isset($tetoEstado['gasto']) ? $tetoEstado['gasto'] : 0)); ?>
            de <?php echo Helpers::e($usd($tetoMensal)); ?> do teto mensal
            (<?php echo number_format($pct, 1, ',', '.'); ?>%).
            <?php if ($pct >= 100): ?>
                <strong>Teto atingido: a IA parou de responder e o chat voltou ao modo determinístico.</strong>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="ni-ajuda">Sem teto mensal configurado — nada impede o gasto de crescer.</div>
    <?php endif; ?>

    <?php if (!empty($consumo['sem_preco'])): ?>
        <div class="ni-aviso ni-aviso--atencao">
            <?php echo $num($consumo['sem_preco']); ?> resposta(s) vieram de um modelo fora do catálogo.
            O custo delas <strong>não</strong> entra nas somas acima nem no teto.
        </div>
    <?php endif; ?>

    <?php if (!empty($porModelo)): ?>
        <table class="ni-tabela">
            <thead><tr><th>Modelo</th><th class="num">Respostas</th><th class="num">Custo no mês</th></tr></thead>
            <tbody>
            <?php foreach ($porModelo as $linha): ?>
                <tr>
                    <td><code><?php echo Helpers::e((string) $linha['modelo_ia']); ?></code></td>
                    <td class="num"><?php echo $num($linha['respostas']); ?></td>
                    <td class="num"><?php echo $linha['custo'] === null ? '<em>sem preço</em>' : Helpers::e($usd($linha['custo'])); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<form method="post" action="/admin/tutor-norminha/ia/salvar" class="admin-form">
    <?php echo $csrfField; ?>

    <div class="ni-secao">
        <h2>Credencial da OpenAI</h2>

        <?php if ($origemChave === 'env'): ?>
            <div class="ni-nota">
                A chave vem do <code>.env</code> (<code>OPENAI_API_KEY</code>), que tem
                precedência sobre esta tela. Enquanto ela estiver lá, o campo abaixo não
                tem efeito. É o arranjo mais fechado: quem administra o painel não
                consegue trocar a credencial.
            </div>
        <?php endif; ?>

        <div class="ni-campo">
            <label for="ni-chave">Chave da API</label>
            <input type="password" id="ni-chave" name="tutor_ia_openai_key" autocomplete="new-password"
                   placeholder="<?php echo $chaveOk ? 'Uma chave já está guardada — deixe em branco para mantê-la' : 'sk-...'; ?>"
                   <?php echo (!$podeGuardar || $origemChave === 'env') ? 'disabled' : ''; ?>>
            <div class="ni-ajuda">
                Guardada cifrada no banco. Nunca é devolvida a esta tela, nem em parte:
                um dump do banco não entrega a chave, porque a chave de cifragem mora no
                <code>.env</code>. Em branco significa "manter a que já existe".
            </div>
        </div>

        <?php if ($chaveOk && $origemChave === 'banco'): ?>
            <div class="ni-campo">
                <label style="font-weight: 400;">
                    <input type="checkbox" name="tutor_ia_remover_chave" value="1">
                    Remover a chave guardada
                </label>
                <div class="ni-ajuda">A IA para de responder e o chat volta ao modo determinístico.</div>
            </div>
        <?php endif; ?>
    </div>

    <div class="ni-secao">
        <h2>Modelo</h2>
        <?php if ($origemModelo === 'env'): ?>
            <div class="ni-nota">O modelo vem do <code>.env</code> (<code>OPENAI_MODEL</code>) e tem precedência.</div>
        <?php endif; ?>

        <label class="ni-modelo">
            <input type="radio" name="tutor_ia_modelo" value="" <?php echo $modeloAtual === '' ? 'checked' : ''; ?>>
            <span class="ni-modelo__nome">Nenhum</span>
            <div class="ni-modelo__nota">A IA não é chamada. O chat continua respondendo o que é determinístico.</div>
        </label>

        <?php foreach ($catalogo as $id => $m): ?>
            <label class="ni-modelo">
                <div class="ni-modelo__topo">
                    <span>
                        <input type="radio" name="tutor_ia_modelo" value="<?php echo Helpers::e($id); ?>"
                               <?php echo $modeloAtual === $id ? 'checked' : ''; ?>>
                        <span class="ni-modelo__nome"><?php echo Helpers::e($m['rotulo']); ?></span>
                        <span class="ni-modelo__faixa"><?php echo Helpers::e($m['faixa']); ?></span>
                        <?php if (!empty($m['recomendado'])): ?><span class="ni-selo">recomendado</span><?php endif; ?>
                    </span>
                    <span class="ni-modelo__preco">
                        entrada <?php echo Helpers::e($usd($m['entrada'])); ?> ·
                        cache <?php echo Helpers::e($usd($m['entrada_cache'])); ?> ·
                        saída <?php echo Helpers::e($usd($m['saida'])); ?>
                        <span style="color:#a1a1aa;">/ milhão de tokens</span>
                    </span>
                </div>
                <div class="ni-modelo__nota"><?php echo Helpers::e($m['nota']); ?></div>
            </label>
        <?php endforeach; ?>

        <div class="ni-ajuda">Preços conferidos na documentação oficial em <?php echo Helpers::e($conferidoEm); ?>. Preço muda — vale reconferir de tempos em tempos.</div>
    </div>

    <div class="ni-secao">
        <h2>Teto de gasto mensal</h2>
        <div class="ni-campo">
            <label for="ni-teto">Limite em dólares por mês</label>
            <input type="number" id="ni-teto" name="tutor_ia_teto_mensal_usd" min="0" max="100000" step="0.01"
                   value="<?php echo Helpers::e(number_format($tetoMensal, 2, '.', '')); ?>">
            <div class="ni-ajuda">
                Ao atingir o teto, a Norminha para de chamar a IA e o chat volta ao modo
                determinístico — sem tela de erro para o aluno. Use <code>0</code> para não ter teto.
                <br>
                Este é um freio nosso, calculado a partir dos tokens que registramos.
                Ele <strong>não substitui</strong> o limite de gasto configurado no painel da
                OpenAI, que é o único que o provedor garante.
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-primary">Salvar</button>
</form>

<?php if ($chaveOk && $modeloAtual !== ''): ?>
    <form method="post" action="/admin/tutor-norminha/ia/testar" style="margin-top: 18px;">
        <?php echo $csrfField; ?>
        <button type="submit" class="btn">Testar a chave agora</button>
        <div class="ni-ajuda">Faz uma chamada mínima ao provedor. Gasta alguns tokens — é a única forma honesta de saber se a chave funciona.</div>
    </form>
<?php endif; ?>
