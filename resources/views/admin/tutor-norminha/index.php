<?php
use App\Core\Helpers;

$falas = isset($falas) && is_array($falas) ? $falas : array();
$filtros = isset($filtros) && is_array($filtros) ? $filtros : array();
$contextos = isset($contextos) && is_array($contextos) ? $contextos : array('home', 'institucional', 'area_aluno', 'cursos', 'curso', 'aula', 'checkout');
$contextosLabels = array(
    'home' => 'Início',
    'institucional' => 'Institucional',
    'area_aluno' => 'Área do aluno',
    'cursos' => 'Cursos',
    'curso' => 'Curso',
    'aula' => 'Aula',
    'checkout' => 'Finalização',
);
$busca = isset($filtros['busca']) ? (string) $filtros['busca'] : '';
$contextoSelecionado = isset($filtros['contexto']) ? (string) $filtros['contexto'] : '';
$ativoSelecionado = isset($filtros['ativo']) ? (string) $filtros['ativo'] : '';

$formatarValor = function ($valor) {
    $valor = trim((string) $valor);
    return $valor === '' ? '-' : $valor;
};

$audioPermitido = function ($valor) {
    $valor = trim((string) $valor);
    if ($valor === '') {
        return false;
    }

    if (strpos($valor, '/uploads/tutor-norminha/audio/') !== 0) {
        return false;
    }

    if (strpos($valor, '..') !== false || strpos($valor, '?') !== false || strpos($valor, '#') !== false) {
        return false;
    }

    $caminhoFisico = BASE_PATH . $valor;
    return is_file($caminhoFisico) && is_readable($caminhoFisico) && (int) @filesize($caminhoFisico) > 0;
};
$audioMimeType = function ($url) {
    $path = parse_url((string) $url, PHP_URL_PATH);
    $ext = strtolower(pathinfo($path ?: (string) $url, PATHINFO_EXTENSION));
    if ($ext === 'ogg') {
        return 'audio/ogg';
    }
    if ($ext === 'wav') {
        return 'audio/wav';
    }
    return 'audio/mpeg';
};
?>
<section class="admin-page tutor-norminha-admin">
    <header class="admin-page__header">
        <div>
            <h1 class="admin-page__title"><?php echo Helpers::e($title); ?></h1>
            <p class="admin-page__subtitle">Cadastro e manutenção das falas públicas da Norminha. O áudio continua sendo enviado manualmente nesta fase.</p>
        </div>
        <div class="admin-page__actions">
            <a class="button-link button-link--ghost" href="/admin/tutor-norminha/configuracoes">Configurações</a>
            <a class="button-link button-link--primary" href="/admin/tutor-norminha/criar">Nova fala</a>
        </div>
    </header>

    <?php if (!empty($success)): ?>
        <section class="alert alert-success"><?php echo Helpers::e($success); ?></section>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <section class="alert alert-danger">
            <?php foreach ($errors as $error): ?>
                <p><?php echo Helpers::e($error); ?></p>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <section class="status-card tutor-norminha-admin__notice">
        <strong>Orientação operacional</strong>
        <p>Envie áudios apenas em <code>/uploads/tutor-norminha/audio/</code>. Nesta fase não há geração automática por API.</p>
        <p class="muted">Filtros por título, texto, contexto e status ajudam a localizar rapidamente a fala ativa em cada rota.</p>
    </section>

    <section class="status-card tutor-norminha-admin__filters">
        <form method="get" action="/admin/tutor-norminha" class="admin-form-grid">
            <label class="admin-form-grid__full">
                <span>Busca</span>
                <input type="search" name="busca" value="<?php echo Helpers::e($busca); ?>" placeholder="Título ou texto da fala">
            </label>
            <label>
                <span>Contexto</span>
                <select name="contexto">
                    <option value="">Todos</option>
                    <?php foreach ($contextos as $contexto): ?>
                        <option value="<?php echo Helpers::e($contexto); ?>" <?php echo $contextoSelecionado === $contexto ? 'selected' : ''; ?>><?php echo Helpers::e(isset($contextosLabels[$contexto]) ? $contextosLabels[$contexto] : $contexto); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Status</span>
                <select name="ativo">
                    <option value="">Todos</option>
                    <option value="1" <?php echo $ativoSelecionado === '1' ? 'selected' : ''; ?>>Ativas</option>
                    <option value="0" <?php echo $ativoSelecionado === '0' ? 'selected' : ''; ?>>Inativas</option>
                </select>
            </label>
            <div class="admin-filter-grid__actions">
                <button type="submit" class="button-link button-link--primary">Filtrar</button>
                <a class="button-link button-link--ghost" href="/admin/tutor-norminha">Limpar</a>
            </div>
        </form>
    </section>

    <section class="status-card">
        <div class="admin-page__content">
            <div>
                <h2 style="margin-top:0;">Falas cadastradas</h2>
                <p class="muted">Use a edição para alterar texto, contexto, rota, estado do avatar e áudio enviado manualmente.</p>
            </div>
        </div>

        <div style="overflow-x:auto;">
            <table class="admin-table admin-table--tutor-norminha">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Título</th>
                        <th>Contexto</th>
                        <th>Rota</th>
                        <th>Curso</th>
                        <th>Módulo</th>
                        <th>Aula</th>
                        <th>Áudio</th>
                        <th>Ativo</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($falas)): ?>
                        <tr>
                            <td colspan="10">Nenhuma fala cadastrada.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($falas as $fala): ?>
                            <?php
                            $audioUrl = isset($fala['audio_url']) ? trim((string) $fala['audio_url']) : '';
                            $audioValido = $audioPermitido($audioUrl);
                            $ativo = !empty($fala['ativo']) ? 1 : 0;
                            $textoResumo = isset($fala['texto']) ? trim((string) $fala['texto']) : '';
                            $textoResumo = function_exists('mb_substr') ? mb_substr($textoResumo, 0, 140, 'UTF-8') : substr($textoResumo, 0, 140);
                            if (strlen((string) $textoResumo) >= 140) {
                                $textoResumo .= '...';
                            }
                            ?>
                            <tr>
                                <td><?php echo (int) $fala['id']; ?></td>
                                <td>
                                    <strong><?php echo Helpers::e(isset($fala['titulo']) ? $fala['titulo'] : ''); ?></strong>
                                    <small><?php echo Helpers::e($textoResumo); ?></small>
                                </td>
                                <td><?php echo Helpers::e(isset($contextosLabels[isset($fala['contexto']) ? $fala['contexto'] : '']) ? $contextosLabels[isset($fala['contexto']) ? $fala['contexto'] : ''] : (isset($fala['contexto']) ? $fala['contexto'] : '')); ?></td>
                                <td><?php echo Helpers::e($formatarValor(isset($fala['rota']) ? $fala['rota'] : '')); ?></td>
                                <td><?php echo (int) (isset($fala['curso_id']) ? $fala['curso_id'] : 0) > 0 ? '#' . (int) $fala['curso_id'] : '-'; ?></td>
                                <td><?php echo (int) (isset($fala['modulo_id']) ? $fala['modulo_id'] : 0) > 0 ? '#' . (int) $fala['modulo_id'] : '-'; ?></td>
                                <td><?php echo (int) (isset($fala['aula_id']) ? $fala['aula_id'] : 0) > 0 ? '#' . (int) $fala['aula_id'] : '-'; ?></td>
                                <td>
                                    <?php if ($audioValido): ?>
                                        <audio class="tutor-norminha-admin__audio-player" controls preload="metadata">
                                            <source src="<?php echo Helpers::e($audioUrl); ?>" type="<?php echo Helpers::e($audioMimeType($audioUrl)); ?>">
                                        </audio>
                                        <small><?php echo Helpers::e($audioUrl); ?></small>
                                    <?php else: ?>
                                        <?php if ($audioUrl !== ''): ?>
                                            <span class="muted">Áudio cadastrado, mas arquivo não encontrado no servidor.</span>
                                        <?php else: ?>
                                            <span class="muted">Áudio indisponível</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo $ativo ? 'badge--status badge--status-aprovado' : 'badge--status badge--status-pendente'; ?>">
                                        <?php echo $ativo ? 'Ativa' : 'Inativa'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="tutor-norminha-admin__actions">
                                        <a class="button-link button-link--ghost" href="/admin/tutor-norminha/editar?id=<?php echo (int) $fala['id']; ?>">Editar</a>
                                        <form method="post" action="/admin/tutor-norminha/status" class="tutor-norminha-admin__status-form">
                                            <?php echo $csrfField; ?>
                                            <input type="hidden" name="id" value="<?php echo (int) $fala['id']; ?>">
                                            <input type="hidden" name="ativo" value="<?php echo $ativo ? 0 : 1; ?>">
                                            <button type="submit" class="button-link" onclick="return confirm('<?php echo $ativo ? 'Desativar' : 'Ativar'; ?> esta fala?');"><?php echo $ativo ? 'Desativar' : 'Ativar'; ?></button>
                                        </form>
                                        <details class="tutor-norminha-admin__preview">
                                            <summary>Prévia</summary>
                                            <div>
                                                <strong><?php echo Helpers::e(isset($fala['titulo']) ? $fala['titulo'] : ''); ?></strong>
                                                <p><?php echo Helpers::e(isset($fala['texto']) ? $fala['texto'] : ''); ?></p>
                                            </div>
                                        </details>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</section>
