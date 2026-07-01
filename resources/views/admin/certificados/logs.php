<?php
use App\Core\Helpers;

$logsResultado = isset($logs) && is_array($logs) ? $logs : array('ok' => false, 'entries' => array(), 'message' => 'Não foi possível carregar os logs.');
$logsLista = isset($logsResultado['entries']) && is_array($logsResultado['entries']) ? $logsResultado['entries'] : array();
$logsOk = !empty($logsResultado['ok']);
$buscaAtual = isset($busca) ? (string) $busca : '';

$formatDate = function ($value) {
    if (empty($value)) {
        return '—';
    }

    try {
        return (new DateTime((string) $value))->format('d/m/Y H:i:s');
    } catch (Exception $exception) {
        return (string) $value;
    }
};

$formatContext = function ($context) {
    if (empty($context)) {
        return '—';
    }

    $encoded = json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return $encoded !== false ? $encoded : '—';
};
?>

<div class="admin-page admin-certificados admin-certificados-logs">
    <section class="admin-page__header admin-certificados__header">
        <div>
            <h1 class="admin-page__title">Logs de certificados</h1>
            <p class="admin-page__subtitle">Registro técnico recente dos eventos e erros do módulo de certificados.</p>
        </div>
        <div class="area-curso-actions admin-certificados__actions">
            <a class="button-link button-link--ghost" href="/admin/certificados">Voltar</a>
            <a class="button-link button-link--ghost" href="/admin/certificados/emitir">Emitir certificado</a>
        </div>
    </section>

    <?php if (!empty($success)): ?>
        <section class="auth-message auth-message-success">
            <p><?php echo Helpers::e($success); ?></p>
        </section>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <section class="auth-message auth-message-error">
            <?php foreach ((array) $errors as $error): ?>
                <p><?php echo Helpers::e($error); ?></p>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <section class="status-card admin-certificados__detail admin-mt-16">
        <div class="panel-header">
            <div>
                <h2>Filtro rápido</h2>
                <p>Filtre os registros por termo técnico ou mantenha o padrão para certificados.</p>
            </div>
        </div>

        <form method="get" action="/admin/certificados/logs" class="form-grid admin-mt-12">
            <label class="full">
                Buscar no log
                <input type="text" name="busca" value="<?php echo Helpers::e($buscaAtual); ?>" placeholder="certificado, emitir, falhou...">
            </label>
            <div class="full cta-group">
                <button type="submit" class="button-link button-link--primary">Filtrar</button>
                <a class="button-link button-link--ghost" href="/admin/certificados/logs">Limpar</a>
            </div>
        </form>
    </section>

    <section class="status-card admin-certificados__detail admin-mt-16">
        <div class="panel-header">
            <div>
                <h2>Últimos registros</h2>
                <p>Arquivo: <?php echo Helpers::e(isset($logsResultado['path']) ? (string) $logsResultado['path'] : 'Não informado'); ?></p>
            </div>
            <span class="badge badge--soft"><?php echo (int) count($logsLista); ?> registros</span>
        </div>

        <?php if (!$logsOk): ?>
            <p class="muted admin-mt-12"><?php echo Helpers::e((string) ($logsResultado['message'] ?? 'Não foi possível carregar os logs.')); ?></p>
        <?php elseif (empty($logsLista)): ?>
            <p class="muted admin-mt-12">Nenhum registro encontrado com o filtro atual.</p>
        <?php else: ?>
            <div class="table-wrap admin-mt-12">
                <table class="admin-table admin-table--certificados-logs">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Nível</th>
                            <th>Mensagem</th>
                            <th>Contexto</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logsLista as $item): ?>
                            <tr>
                                <td><?php echo Helpers::e($formatDate($item['timestamp'] ?? '')); ?></td>
                                <td><?php echo Helpers::e((string) ($item['level'] ?? '—')); ?></td>
                                <td><?php echo Helpers::e((string) ($item['message'] ?? '—')); ?></td>
                                <td><pre class="log-excerpt"><?php echo Helpers::e($formatContext(isset($item['context']) ? $item['context'] : array())); ?></pre></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
