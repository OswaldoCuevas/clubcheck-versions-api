<?php
$title = 'Aplicaciones';
$flashSuccess = $_SESSION['admin_flash_success'] ?? null;
$flashError = $_SESSION['admin_flash_error'] ?? null;
unset($_SESSION['admin_flash_success'], $_SESSION['admin_flash_error']);

ob_start();
?>

<div class="apps-page">
    <?php if ($flashSuccess): ?>
        <div class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></div>
    <?php endif; ?>
    <?php if ($flashError): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($flashError) ?></div>
    <?php endif; ?>
    <?php if (empty($multiAppReady)): ?>
        <div class="alert alert-warning">
            Faltan tablas multi-app: <?= htmlspecialchars(implode(', ', $missingMultiAppTables ?? [])) ?>.
            Ejecuta completa la migracion <code>database/migrations/014_create_multi_application_support.sql</code>.
        </div>
    <?php endif; ?>

    <section class="apps-grid">
        <div class="apps-panel">
            <div class="panel-title">
                <span>Apps</span>
                <strong><?= count($apps ?? []) ?></strong>
            </div>

            <div class="app-list">
                <?php foreach (($apps ?? []) as $app): ?>
                    <a class="app-row <?= ($selectedApp['id'] ?? null) === $app['id'] ? 'active' : '' ?>" href="<?= app_url('/admin/app/select?appId=' . urlencode($app['id']) . '&redirect=/admin/applications') ?>">
                        <span class="app-row-icon" style="color: <?= htmlspecialchars($app['color'] ?? '#2f80ed') ?>">
                            <i class="<?= htmlspecialchars($app['iconClass'] ?? 'fa-solid fa-layer-group') ?>"></i>
                        </span>
                        <span>
                            <strong><?= htmlspecialchars($app['name']) ?></strong>
                            <small><?= htmlspecialchars($app['slug']) ?></small>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="apps-panel">
            <div class="panel-title">
                <span>Nueva app</span>
                <strong><i class="fa-solid fa-plus"></i></strong>
            </div>

            <form method="post" action="<?= app_url('/admin/applications/save') ?>" class="app-form">
                <label>Nombre</label>
                <input name="name" class="form-control" placeholder="Mi nueva app" required>

                <label>Slug</label>
                <input name="slug" class="form-control" placeholder="mi-nueva-app">

                <label>Icono Font Awesome</label>
                <input name="iconClass" class="form-control" value="fa-solid fa-layer-group">

                <label>Color</label>
                <input name="color" class="form-control" value="#2f80ed">

                <label>Descripcion</label>
                <textarea name="description" class="form-control" rows="3"></textarea>

                <label class="check-line">
                    <input type="checkbox" name="isActive" checked>
                    Activa
                </label>

                <button class="btn btn-primary" type="submit">
                    <i class="fa-solid fa-floppy-disk me-2"></i>Crear app
                </button>
            </form>
        </div>
    </section>

    <section class="apps-panel wide-panel">
        <div class="panel-title">
            <span>Configuracion de <?= htmlspecialchars($selectedApp['name'] ?? 'app') ?></span>
            <strong><i class="fa-solid fa-sliders"></i></strong>
        </div>

        <form method="post" action="<?= app_url('/admin/applications/settings') ?>" class="settings-grid">
            <input type="hidden" name="appId" value="<?= htmlspecialchars($selectedApp['id'] ?? '') ?>">
            <?php foreach (($settings ?? []) as $key => $setting): ?>
                <label class="setting-field">
                    <span><?= htmlspecialchars($key) ?></span>
                    <small><?= htmlspecialchars($setting['description'] ?? '') ?></small>
                    <input
                        class="form-control"
                        name="settings[<?= htmlspecialchars($key) ?>]"
                        value="<?= htmlspecialchars((string)($setting['value'] ?? '')) ?>"
                        placeholder="Usar .env"
                        <?= !empty($setting['isSecret']) ? 'autocomplete="off"' : '' ?>
                    >
                </label>
            <?php endforeach; ?>
            <div class="form-actions">
                <button class="btn btn-primary" type="submit">
                    <i class="fa-solid fa-check me-2"></i>Guardar configuracion
                </button>
            </div>
        </form>
    </section>

    <section class="apps-panel wide-panel">
        <div class="panel-title">
            <span>Tablas de sincronizacion</span>
            <strong><?= count($syncTables ?? []) ?></strong>
        </div>

        <form method="post" action="<?= app_url('/admin/applications/sync-tables') ?>">
            <input type="hidden" name="appId" value="<?= htmlspecialchars($selectedApp['id'] ?? '') ?>">
            <div class="sync-table">
                <div class="sync-head">
                    <span>Bulk</span>
                    <span>Tabla</span>
                    <span>Pull</span>
                    <span>Push</span>
                    <span>Activa</span>
                </div>
                <?php foreach (($syncTables ?? []) as $row): ?>
                    <div class="sync-row">
                        <input type="hidden" name="sync[<?= htmlspecialchars($row['bulkKey']) ?>][present]" value="1">
                        <strong><?= htmlspecialchars($row['bulkKey']) ?></strong>
                        <span><?= htmlspecialchars($row['tableName']) ?></span>
                        <label><input type="checkbox" name="sync[<?= htmlspecialchars($row['bulkKey']) ?>][pull]" <?= $row['isPullEnabled'] ? 'checked' : '' ?>></label>
                        <label><input type="checkbox" name="sync[<?= htmlspecialchars($row['bulkKey']) ?>][push]" <?= $row['isPushEnabled'] ? 'checked' : '' ?>></label>
                        <label><input type="checkbox" name="sync[<?= htmlspecialchars($row['bulkKey']) ?>][active]" <?= $row['isActive'] ? 'checked' : '' ?>></label>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="form-actions">
                <button class="btn btn-primary" type="submit">
                    <i class="fa-solid fa-diagram-project me-2"></i>Guardar tablas
                </button>
            </div>
        </form>
    </section>
</div>

<?php
$customStyles = <<<CSS
.apps-page {
    max-width: 1180px;
    margin: 0 auto 44px;
}

.apps-grid {
    display: grid;
    grid-template-columns: minmax(260px, 0.8fr) minmax(320px, 1fr);
    gap: 16px;
    margin-bottom: 16px;
}

.apps-panel {
    padding: 18px;
    border: 1px solid #d7eafd;
    border-radius: 10px;
    background: rgba(255,255,255,0.94);
    box-shadow: 0 14px 34px rgba(47, 128, 237, 0.08);
}

.wide-panel {
    margin-top: 16px;
}

.panel-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 16px;
    color: #15395b;
    font-weight: 700;
}

.panel-title span {
    color: #6b8299;
    font-size: 12px;
    text-transform: uppercase;
}

.app-list,
.app-form {
    display: grid;
    gap: 10px;
}

.app-row {
    display: flex;
    align-items: center;
    gap: 12px;
    min-height: 58px;
    padding: 9px;
    border: 1px solid #dbeafa;
    border-radius: 9px;
    background: #f8fbff;
    color: #315574;
    text-decoration: none;
}

.app-row:hover,
.app-row.active {
    border-color: #9fcdfa;
    color: #1769aa;
    background: #edf7ff;
    text-decoration: none;
}

.app-row-icon {
    width: 38px;
    height: 38px;
    display: grid;
    place-items: center;
    flex: 0 0 38px;
    border-radius: 8px;
    background: #eaf5ff;
}

.app-row strong,
.app-row small {
    display: block;
}

.app-row small {
    color: #6b8299;
}

.app-form label,
.setting-field span {
    color: #315574;
    font-weight: 700;
}

.check-line {
    display: flex;
    align-items: center;
    gap: 8px;
}

.settings-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}

.setting-field {
    display: grid;
    gap: 6px;
}

.setting-field small {
    min-height: 32px;
    color: #6b8299;
}

.form-actions {
    grid-column: 1 / -1;
    display: flex;
    justify-content: flex-end;
    margin-top: 4px;
}

.sync-table {
    display: grid;
    gap: 7px;
    max-height: 520px;
    overflow: auto;
    padding-right: 4px;
}

.sync-head,
.sync-row {
    display: grid;
    grid-template-columns: 1.1fr 1.2fr 80px 80px 80px;
    align-items: center;
    gap: 10px;
}

.sync-head {
    position: sticky;
    top: 0;
    z-index: 2;
    padding: 8px 10px;
    color: #6b8299;
    font-size: 12px;
    font-weight: 700;
    background: #ffffff;
}

.sync-row {
    min-height: 44px;
    padding: 8px 10px;
    border: 1px solid #dbeafa;
    border-radius: 8px;
    background: #f8fbff;
}

.sync-row strong {
    color: #15395b;
}

.sync-row span {
    color: #315574;
    overflow-wrap: anywhere;
}

@media (max-width: 760px) {
    .apps-grid,
    .settings-grid {
        grid-template-columns: 1fr;
    }

    .sync-table {
        overflow-x: auto;
    }

    .sync-head,
    .sync-row {
        min-width: 720px;
    }
}
CSS;

$content = ob_get_clean();
include __DIR__ . '/../layouts/app.php';
?>
