<?php
$title = 'Prospectos de WhatsApp - ClubCheck';

ob_start();
?>

<main class="container prospects-page">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <div class="text-success fw-semibold text-uppercase small mb-2">
                <i class="fab fa-whatsapp me-1"></i> CRM de WhatsApp
            </div>
            <h1 class="h2 mb-2">Prospectos</h1>
            <p class="text-muted mb-0">
                Revisa quién escribió, conoce el origen del contacto y da seguimiento a cada oportunidad.
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= app_url('/admin/whatsapp') ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>WhatsApp
            </a>
            <button type="button" class="btn btn-outline-success" id="dispatchConversions">
                <i class="fas fa-paper-plane me-2"></i>Enviar pendientes a Meta
            </button>
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#manualCustomerModal">
                <i class="fas fa-user-plus me-2"></i>Registrar cliente anterior
            </button>
            <button type="button" class="btn btn-success" id="refreshProspects">
                <i class="fas fa-rotate me-2"></i>Actualizar
            </button>
        </div>
    </div>

    <div id="prospectAlerts" aria-live="polite"></div>

    <section class="row g-3 mb-4" aria-label="Resumen de prospectos cargados">
        <div class="col-12 col-sm-4">
            <div class="card summary-card h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="summary-icon summary-icon-blue"><i class="fas fa-users"></i></span>
                    <div>
                        <div class="text-muted small">Prospectos cargados</div>
                        <div class="summary-value" id="totalProspects">—</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-4">
            <div class="card summary-card h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="summary-icon summary-icon-purple"><i class="fas fa-bullhorn"></i></span>
                    <div>
                        <div class="text-muted small">Vienen de anuncios</div>
                        <div class="summary-value" id="adProspects">—</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-4">
            <div class="card summary-card h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="summary-icon summary-icon-green"><i class="fas fa-circle-check"></i></span>
                    <div>
                        <div class="text-muted small">Convertidos en clientes</div>
                        <div class="summary-value" id="customerProspects">—</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="card prospects-card">
        <div class="card-header bg-white border-0 p-3 p-lg-4 pb-0">
            <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                <div>
                    <h2 class="h5 mb-1">Seguimiento comercial</h2>
                    <p class="text-muted small mb-0">
                        Selecciona un prospecto para consultar sus mensajes y actualizar su seguimiento.
                    </p>
                </div>
                <div class="d-flex flex-column flex-sm-row gap-2">
                    <label class="visually-hidden" for="prospectSearch">Buscar prospecto</label>
                    <div class="input-group search-control">
                        <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                        <input type="search" class="form-control" id="prospectSearch" placeholder="Buscar nombre o teléfono">
                    </div>
                    <label class="visually-hidden" for="prospectStatusFilter">Filtrar por estado</label>
                    <select class="form-select status-filter" id="prospectStatusFilter">
                        <option value="">Todos los estados</option>
                        <option value="new">Nuevo</option>
                        <option value="contacted">Contactado</option>
                        <option value="interested">Interesado</option>
                        <option value="demo_scheduled">Demo agendada</option>
                        <option value="demo_completed">Demo realizada</option>
                        <option value="not_interested">No interesado</option>
                        <option value="no_response">Sin respuesta</option>
                        <option value="customer">Cliente</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="table-responsive mt-3">
            <table class="table table-hover align-middle mb-0 prospects-table">
                <thead>
                    <tr>
                        <th scope="col">Prospecto</th>
                        <th scope="col">Origen</th>
                        <th scope="col">Estado</th>
                        <th scope="col">Último contacto</th>
                        <th scope="col" class="text-end">Detalle</th>
                    </tr>
                </thead>
                <tbody id="prospectsTableBody">
                    <tr>
                        <td colspan="5" class="text-center py-5 text-muted">
                            <span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                            Cargando prospectos...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white border-0 d-flex flex-wrap justify-content-between align-items-center gap-2 px-3 px-lg-4 py-3">
            <span class="small text-muted" id="prospectsCount"></span>
            <button type="button" class="btn btn-outline-success btn-sm d-none" id="loadMoreProspects">
                Cargar más
            </button>
        </div>
    </section>

    <div class="small text-muted mt-3">
        <i class="fas fa-circle-info me-1"></i>
        La atribución solo se muestra cuando Meta la incluyó en el mensaje. Los contactos sin ese dato aparecen como origen desconocido.
    </div>
</main>

<div class="modal fade" id="prospectDetailModal" tabindex="-1" aria-labelledby="prospectDetailTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-fullscreen-lg-down">
        <div class="modal-content prospect-modal">
            <div class="modal-header border-0 pb-2">
                <div class="d-flex align-items-center gap-3">
                    <div class="prospect-avatar" id="detailAvatar"><i class="fas fa-user"></i></div>
                    <div>
                        <div class="small text-success fw-semibold">FICHA DEL PROSPECTO</div>
                        <h2 class="modal-title h4 mb-0" id="prospectDetailTitle">Detalle</h2>
                        <div class="small text-muted" id="detailPhone"></div>
                    </div>
                </div>
                <button type="button" class="btn-close align-self-start" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body pt-2">
                <div id="detailAlert" aria-live="polite"></div>
                <div class="row g-4">
                    <div class="col-lg-5">
                        <section class="detail-section mb-4">
                            <h3 class="section-title"><i class="fas fa-address-card me-2"></i>Información de contacto</h3>
                            <dl class="detail-grid mb-0">
                                <div><dt>Primer contacto</dt><dd id="detailFirstContact">—</dd></div>
                                <div><dt>Último contacto</dt><dd id="detailLastContact">—</dd></div>
                                <div><dt>Origen</dt><dd id="detailOrigin">—</dd></div>
                                <div><dt>Anuncio</dt><dd id="detailAdId">No proporcionado</dd></div>
                                <div><dt>Campaña</dt><dd id="detailCampaignId">No proporcionada</dd></div>
                                <div><dt>Conversión</dt><dd id="detailConversion">Aún no es cliente</dd></div>
                            </dl>
                            <details class="attribution-details mt-3 d-none" id="attributionDetails">
                                <summary>Ver datos originales de atribución</summary>
                                <pre class="attribution-json mb-0" id="detailAttribution"></pre>
                            </details>
                        </section>

                        <section class="detail-section">
                            <h3 class="section-title"><i class="fas fa-list-check me-2"></i>Seguimiento comercial</h3>
                            <form id="prospectUpdateForm">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold" for="detailStatus">Estado</label>
                                    <select class="form-select" id="detailStatus" name="status" required>
                                        <option value="new">Nuevo</option>
                                        <option value="contacted">Contactado</option>
                                        <option value="interested">Interesado</option>
                                        <option value="demo_scheduled">Demo agendada</option>
                                        <option value="demo_completed">Demo realizada</option>
                                        <option value="not_interested">No interesado</option>
                                        <option value="no_response">Sin respuesta</option>
                                        <option value="customer">Cliente</option>
                                    </select>
                                </div>
                                <div class="mb-3" id="saleValueGroup">
                                    <label class="form-label fw-semibold" for="detailSaleValue">Valor de venta</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" class="form-control" id="detailSaleValue" min="0" step="0.01" placeholder="Opcional">
                                    </div>
                                    <div class="form-text">Se guarda al marcar el prospecto como Cliente.</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold" for="detailNotes">Notas internas</label>
                                    <textarea class="form-control" id="detailNotes" rows="3" maxlength="5000" placeholder="Agrega contexto para el seguimiento..."></textarea>
                                </div>
                                <div class="form-check consent-check mb-3">
                                    <input class="form-check-input" type="checkbox" value="1" id="detailMarketingConsent">
                                    <label class="form-check-label" for="detailMarketingConsent">
                                        El prospecto otorgó consentimiento para comunicaciones y envío de eventos a Meta
                                    </label>
                                    <div class="form-text">
                                        Márcalo solo si tienes constancia del consentimiento. No se activa automáticamente.
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-success w-100" id="saveProspect">
                                    <i class="fas fa-floppy-disk me-2"></i>Guardar seguimiento
                                </button>
                            </form>

                            <div class="history-block mt-4">
                                <h4 class="h6 fw-semibold mb-3">Cambios de estado</h4>
                                <div id="statusHistory" class="status-history"></div>
                            </div>
                        </section>
                    </div>

                    <div class="col-lg-7">
                        <section class="detail-section conversation-section">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                                <div>
                                    <h3 class="section-title mb-1"><i class="fab fa-whatsapp me-2"></i>Conversación registrada</h3>
                                    <p class="text-muted small mb-0">Los mensajes más recientes aparecen primero.</p>
                                </div>
                                <span class="badge rounded-pill text-bg-light" id="messageCount">0 mensajes</span>
                            </div>
                            <div id="prospectMessages" class="message-list">
                                <div class="text-center text-muted py-5">Cargando conversación...</div>
                            </div>
                        </section>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="manualCustomerModal" tabindex="-1" aria-labelledby="manualCustomerTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content prospect-modal">
            <div class="modal-header border-0">
                <div>
                    <div class="small text-success fw-semibold">IMPORTACIÓN MANUAL</div>
                    <h2 class="modal-title h4 mb-0" id="manualCustomerTitle">Registrar cliente anterior</h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body pt-0 manual-customer-modal-body">
                <form id="manualCustomerForm">
                    <div id="manualCustomerAlert" aria-live="polite"></div>
                    <p class="text-muted">
                        Registra a alguien que ya compró y escribió a tu WhatsApp antes de activar este CRM.
                        No se enviará ningún mensaje de WhatsApp.
                    </p>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="manualCustomerName">Nombre</label>
                            <input type="text" class="form-control" id="manualCustomerName" maxlength="255" autocomplete="name" placeholder="Nombre del cliente">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="manualCountryCode">País y teléfono de WhatsApp <span class="text-danger">*</span></label>
                            <div class="row g-2">
                                <div class="col-5">
                                    <select class="form-select" id="manualCountryCode" autocomplete="country" required>
                                        <option value="52" data-country="MX" selected>México +52</option>
                                        <option value="1" data-country="US">EE. UU. +1</option>
                                        <option value="1" data-country="CA">Canadá +1</option>
                                        <option value="54" data-country="AR">Argentina +54</option>
                                        <option value="591" data-country="BO">Bolivia +591</option>
                                        <option value="55" data-country="BR">Brasil +55</option>
                                        <option value="56" data-country="CL">Chile +56</option>
                                        <option value="57" data-country="CO">Colombia +57</option>
                                        <option value="506" data-country="CR">Costa Rica +506</option>
                                        <option value="53" data-country="CU">Cuba +53</option>
                                        <option value="593" data-country="EC">Ecuador +593</option>
                                        <option value="503" data-country="SV">El Salvador +503</option>
                                        <option value="34" data-country="ES">España +34</option>
                                        <option value="502" data-country="GT">Guatemala +502</option>
                                        <option value="504" data-country="HN">Honduras +504</option>
                                        <option value="353" data-country="IE">Irlanda +353</option>
                                        <option value="39" data-country="IT">Italia +39</option>
                                        <option value="505" data-country="NI">Nicaragua +505</option>
                                        <option value="507" data-country="PA">Panamá +507</option>
                                        <option value="595" data-country="PY">Paraguay +595</option>
                                        <option value="51" data-country="PE">Perú +51</option>
                                        <option value="1" data-country="PR">Puerto Rico +1</option>
                                        <option value="1" data-country="DO">Rep. Dominicana +1</option>
                                        <option value="44" data-country="GB">Reino Unido +44</option>
                                        <option value="598" data-country="UY">Uruguay +598</option>
                                        <option value="58" data-country="VE">Venezuela +58</option>
                                    </select>
                                </div>
                                <div class="col-7">
                                    <input type="tel" class="form-control" id="manualCustomerPhone" maxlength="10" inputmode="numeric" pattern="[0-9]{10}" autocomplete="tel-national" placeholder="10 dígitos" required>
                                </div>
                            </div>
                            <div class="form-text" id="manualPhoneHelp">Escribe los 10 dígitos, sin el código de país ni el 1 de WhatsApp.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="manualPurchaseDate">Fecha de compra <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control" id="manualPurchaseDate" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="manualContactDate">Fecha aproximada del primer mensaje</label>
                            <input type="datetime-local" class="form-control" id="manualContactDate">
                            <div class="form-text">Déjala vacía si no recuerdas la fecha; no se inventará una.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="manualSaleValue">Valor de compra</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" id="manualSaleValue" min="0" max="9999999999.99" step="0.01" placeholder="Opcional">
                            </div>
                            <div class="form-text">La moneda configurada para CAPI se utilizará al reportar el valor.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="manualCustomerOrigin">Origen del prospecto <span class="text-danger">*</span></label>
                            <select class="form-select" id="manualCustomerOrigin" required>
                                <option value="unknown" selected>No estoy seguro / origen desconocido</option>
                                <option value="click_to_whatsapp">Confirmo que llegó desde un anuncio Meta a WhatsApp</option>
                            </select>
                            <div class="form-text">No confirmes un anuncio si no sabes que ese fue el origen.</div>
                        </div>
                        <div class="col-12 d-none" id="manualAttributionFields">
                            <div class="border rounded-3 bg-light p-3">
                                <div class="fw-semibold small mb-2">Atribución del anuncio (opcional)</div>
                                <p class="text-muted small mb-3">
                                    Captura estos datos solo si los tienes. `ctwa_clid` ayuda a atribuir la conversación; no lo inventes.
                                </p>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label small" for="manualCtwaClickId">CTWA click ID</label>
                                        <input type="text" class="form-control form-control-sm" id="manualCtwaClickId" maxlength="255">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small" for="manualAdId">ID del anuncio</label>
                                        <input type="text" class="form-control form-control-sm" id="manualAdId" maxlength="128">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small" for="manualCampaignId">ID de campaña</label>
                                        <input type="text" class="form-control form-control-sm" id="manualCampaignId" maxlength="128">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold" for="manualCustomerNotes">Notas internas</label>
                            <textarea class="form-control" id="manualCustomerNotes" rows="2" maxlength="5000" placeholder="Contexto útil para el equipo comercial"></textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check consent-check">
                                <input class="form-check-input" type="checkbox" value="1" id="manualMarketingConsent">
                                <label class="form-check-label" for="manualMarketingConsent">
                                    Tengo registro del consentimiento para compartir sus datos con Meta para medición de esta compra
                                </label>
                                <div class="form-text">
                                    Desmarcado por defecto. Sin consentimiento, el evento se guarda en ClubCheck pero no se envía.
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-warning small mt-3 mb-0">
                        <i class="fas fa-clock me-1"></i>
                        Meta acepta eventos recientes, dentro de los últimos 7 días. Una compra anterior puede guardarse aquí,
                        pero no se enviará a Meta. El teléfono se enviará normalizado y hasheado; la coincidencia no está garantizada.
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-success" id="saveManualCustomer" form="manualCustomerForm">
                    <i class="fas fa-floppy-disk me-2"></i>Guardar cliente
                </button>
            </div>
        </div>
    </div>
</div>

<?php
$endpointsJson = json_encode([
    'list' => app_url('/admin/api/whatsapp/prospects'),
    'detail' => app_url('/admin/api/whatsapp/prospects'),
    'manual' => app_url('/admin/api/whatsapp/prospects/manual'),
    'dispatch' => app_url('/admin/api/whatsapp/conversions/dispatch'),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$customStyles = <<<CSS
.prospects-page {
    max-width: 1440px;
}

.summary-card,
.prospects-card,
.detail-section {
    border: 1px solid #e8edf2;
    border-radius: 14px;
    box-shadow: 0 5px 18px rgba(31, 45, 61, 0.045);
}

.summary-icon {
    align-items: center;
    border-radius: 12px;
    display: inline-flex;
    font-size: 1.15rem;
    height: 48px;
    justify-content: center;
    width: 48px;
}

.summary-icon-blue {
    background: #e8f1ff;
    color: #2563eb;
}

.summary-icon-purple {
    background: #f1eaff;
    color: #7c3aed;
}

.summary-icon-green {
    background: #e7f8ef;
    color: #16834a;
}

.summary-value {
    color: #1f2937;
    font-size: 1.5rem;
    font-weight: 700;
    line-height: 1.2;
}

.prospects-card {
    overflow: hidden;
}

.search-control {
    min-width: 260px;
}

.status-filter {
    min-width: 180px;
}

.prospects-table thead th {
    background: #f8fafc;
    border-bottom: 1px solid #e8edf2;
    color: #64748b;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.055em;
    padding: 0.85rem 1rem;
    text-transform: uppercase;
    white-space: nowrap;
}

.prospects-table tbody td {
    border-color: #eef1f5;
    padding: 0.95rem 1rem;
}

.prospect-row {
    cursor: pointer;
}

.prospect-name {
    color: #1f2937;
    font-weight: 650;
}

.prospect-phone {
    color: #64748b;
    font-size: 0.84rem;
}

.status-badge {
    border-radius: 999px;
    display: inline-flex;
    font-size: 0.76rem;
    font-weight: 650;
    padding: 0.38rem 0.65rem;
    white-space: nowrap;
}

.status-new,
.status-no_response {
    background: #f1f5f9;
    color: #475569;
}

.status-contacted,
.status-demo_scheduled {
    background: #e8f1ff;
    color: #1d4ed8;
}

.status-interested,
.status-demo_completed {
    background: #f1eaff;
    color: #6d28d9;
}

.status-not_interested {
    background: #fff1f0;
    color: #b42318;
}

.status-customer {
    background: #e7f8ef;
    color: #147a43;
}

.origin-chip {
    align-items: center;
    color: #475569;
    display: inline-flex;
    font-size: 0.84rem;
    gap: 0.4rem;
}

.origin-chip.is-ad {
    color: #6d28d9;
}

.prospect-modal {
    border: 0;
    border-radius: 16px;
}

.manual-customer-modal-body {
    overscroll-behavior: contain;
}

.prospect-avatar {
    align-items: center;
    background: #e7f8ef;
    border-radius: 50%;
    color: #16834a;
    display: flex;
    flex: 0 0 48px;
    font-size: 1.15rem;
    height: 48px;
    justify-content: center;
    width: 48px;
}

.detail-section {
    background: #fff;
    padding: 1.1rem;
}

.section-title {
    color: #273449;
    font-size: 1rem;
    font-weight: 700;
    margin-bottom: 1rem;
}

.section-title i {
    color: #16834a;
}

.detail-grid {
    display: grid;
    gap: 0.9rem 1rem;
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.detail-grid dt {
    color: #64748b;
    font-size: 0.76rem;
    font-weight: 600;
    margin-bottom: 0.2rem;
}

.detail-grid dd {
    color: #273449;
    font-size: 0.9rem;
    margin: 0;
    overflow-wrap: anywhere;
}

.attribution-details summary {
    color: #475569;
    cursor: pointer;
    font-size: 0.83rem;
    font-weight: 600;
}

.attribution-json {
    background: #f8fafc;
    border-radius: 8px;
    color: #334155;
    font-size: 0.75rem;
    margin-top: 0.6rem;
    max-height: 180px;
    overflow: auto;
    padding: 0.75rem;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
}

.consent-check {
    background: #f8fafc;
    border-radius: 9px;
    padding: 0.8rem 0.8rem 0.8rem 2.4rem;
}

.consent-check .form-check-input {
    margin-left: -1.6rem;
}

.consent-check .form-check-label {
    color: #334155;
    font-size: 0.86rem;
    font-weight: 600;
}

.status-history {
    display: grid;
    gap: 0.6rem;
}

.history-item {
    align-items: center;
    border-left: 2px solid #dbeafe;
    display: flex;
    flex-wrap: wrap;
    font-size: 0.82rem;
    gap: 0.35rem;
    padding: 0.1rem 0 0.1rem 0.7rem;
}

.history-date {
    color: #64748b;
    flex-basis: 100%;
    font-size: 0.74rem;
}

.conversation-section {
    background: #f7faf8;
    min-height: 520px;
}

.message-list {
    display: flex;
    flex-direction: column;
    gap: 0.8rem;
    max-height: 690px;
    overflow-y: auto;
    padding: 0.25rem 0.2rem;
}

.message-bubble {
    border: 1px solid #e6ebef;
    border-radius: 12px;
    max-width: 88%;
    padding: 0.7rem 0.85rem;
}

.message-bubble.incoming {
    align-self: flex-start;
    background: #fff;
    border-top-left-radius: 4px;
}

.message-bubble.outgoing {
    align-self: flex-end;
    background: #e7f8ef;
    border-color: #d3efdf;
    border-top-right-radius: 4px;
}

.message-meta {
    color: #64748b;
    display: flex;
    flex-wrap: wrap;
    font-size: 0.72rem;
    gap: 0.55rem;
    justify-content: space-between;
    margin-bottom: 0.35rem;
}

.message-content {
    color: #273449;
    font-size: 0.9rem;
    margin: 0;
    overflow-wrap: anywhere;
    white-space: pre-wrap;
}

@media (max-width: 575.98px) {
    .search-control {
        min-width: 0;
    }

    .status-filter {
        min-width: 0;
    }

    .detail-grid {
        grid-template-columns: 1fr;
    }

    .conversation-section {
        min-height: 360px;
    }
}
CSS;

ob_start();
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const endpoints = <?= $endpointsJson ?>;
    const pageSize = 50;
    const tableBody = document.getElementById('prospectsTableBody');
    const refreshButton = document.getElementById('refreshProspects');
    const loadMoreButton = document.getElementById('loadMoreProspects');
    const statusFilter = document.getElementById('prospectStatusFilter');
    const searchInput = document.getElementById('prospectSearch');
    const detailModalElement = document.getElementById('prospectDetailModal');
    const detailModal = bootstrap.Modal.getOrCreateInstance(detailModalElement);
    const updateForm = document.getElementById('prospectUpdateForm');
    const manualCustomerForm = document.getElementById('manualCustomerForm');
    const manualCustomerModalElement = document.getElementById('manualCustomerModal');
    const manualCustomerModal = bootstrap.Modal.getOrCreateInstance(manualCustomerModalElement);
    const manualCustomerOrigin = document.getElementById('manualCustomerOrigin');
    const manualAttributionFields = document.getElementById('manualAttributionFields');
    const manualCountryCode = document.getElementById('manualCountryCode');
    const manualCustomerPhone = document.getElementById('manualCustomerPhone');
    const dispatchButton = document.getElementById('dispatchConversions');

    manualCustomerPhone.maxLength = 10;
    manualCustomerPhone.pattern = '[0-9]{10}';

    let prospects = [];
    let offset = 0;
    let hasMore = false;
    let selectedProspectId = null;
    let currentMessages = [];
    let currentHistory = [];

    const statusLabels = {
        new: 'Nuevo',
        contacted: 'Contactado',
        interested: 'Interesado',
        demo_scheduled: 'Demo agendada',
        demo_completed: 'Demo realizada',
        not_interested: 'No interesado',
        no_response: 'Sin respuesta',
        customer: 'Cliente',
    };

    function escapeHtml(value) {
        if (value === null || value === undefined) {
            return '';
        }

        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function statusLabel(status) {
        return statusLabels[status] || 'Sin estado';
    }

    function statusBadge(status) {
        const safeStatus = statusLabels[status] ? status : 'new';

        return `<span class="status-badge status-${safeStatus}">${escapeHtml(statusLabel(status))}</span>`;
    }

    function formatDate(value) {
        if (!value) {
            return '—';
        }

        const normalized = String(value).replace(' ', 'T');
        const date = new Date(normalized);

        if (Number.isNaN(date.getTime())) {
            return escapeHtml(value);
        }

        return new Intl.DateTimeFormat('es-MX', {
            dateStyle: 'medium',
            timeStyle: 'short',
        }).format(date);
    }

    function toLocalDateTimeInput(date) {
        const localDate = new Date(date.getTime() - (date.getTimezoneOffset() * 60000));

        return localDate.toISOString().slice(0, 16);
    }

    function toUtcIsoString(value) {
        return value ? new Date(value).toISOString() : null;
    }

    function showAlert(message, type = 'danger', target = 'prospectAlerts') {
        const container = document.getElementById(target);

        if (!container) {
            return;
        }

        container.innerHTML = `
            <div class="alert alert-${escapeHtml(type)} alert-dismissible fade show" role="alert">
                ${escapeHtml(message)}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>`;
    }

    function originDisplay(prospect) {
        if (prospect.Origin === 'click_to_whatsapp') {
            return {
                label: 'Anuncio de Meta',
                icon: 'fa-bullhorn',
                className: 'is-ad',
            };
        }

        return {
            label: 'Origen desconocido',
            icon: 'fa-circle-question',
            className: '',
        };
    }

    function renderSummary() {
        const adCount = prospects.filter(function(prospect) {
            return prospect.Origin === 'click_to_whatsapp';
        }).length;
        const customerCount = prospects.filter(function(prospect) {
            return prospect.Status === 'customer';
        }).length;

        document.getElementById('totalProspects').textContent = String(prospects.length);
        document.getElementById('adProspects').textContent = String(adCount);
        document.getElementById('customerProspects').textContent = String(customerCount);
    }

    function renderProspects() {
        const searchTerm = searchInput.value.trim().toLocaleLowerCase();
        const visibleProspects = prospects.filter(function(prospect) {
            const name = String(prospect.Name || '').toLocaleLowerCase();
            const phone = String(prospect.PhoneNumber || '').toLocaleLowerCase();

            return name.includes(searchTerm) || phone.includes(searchTerm);
        });

        renderSummary();
        document.getElementById('prospectsCount').textContent = visibleProspects.length === prospects.length
            ? `${prospects.length} prospectos cargados`
            : `${visibleProspects.length} de ${prospects.length} prospectos coinciden`;

        loadMoreButton.classList.toggle('d-none', !hasMore || searchTerm !== '');

        if (visibleProspects.length === 0) {
            const message = prospects.length === 0
                ? 'Aún no hay prospectos registrados. Cuando llegue un mensaje nuevo, aparecerá aquí.'
                : 'No encontramos prospectos con esa búsqueda.';

            tableBody.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center py-5">
                        <div class="text-muted"><i class="fas fa-inbox me-2"></i>${escapeHtml(message)}</div>
                    </td>
                </tr>`;
            return;
        }

        tableBody.innerHTML = visibleProspects.map(function(prospect) {
            const origin = originDisplay(prospect);
            const name = prospect.Name || 'Nombre no disponible';
            const phone = prospect.PhoneNumber || prospect.WaId || 'Teléfono no disponible';

            return `
                <tr class="prospect-row" data-prospect-id="${escapeHtml(prospect.Id)}" tabindex="0">
                    <td>
                        <div class="prospect-name">${escapeHtml(name)}</div>
                        <div class="prospect-phone">${escapeHtml(phone)}</div>
                    </td>
                    <td>
                        <span class="origin-chip ${origin.className}">
                            <i class="fas ${origin.icon}"></i>${escapeHtml(origin.label)}
                        </span>
                    </td>
                    <td>${statusBadge(prospect.Status)}</td>
                    <td><span class="small text-muted">${formatDate(prospect.LastContactAt)}</span></td>
                    <td class="text-end">
                        <button type="button" class="btn btn-sm btn-outline-success" data-open-prospect="${escapeHtml(prospect.Id)}">
                            Ver ficha <i class="fas fa-arrow-right ms-1"></i>
                        </button>
                    </td>
                </tr>`;
        }).join('');
    }

    async function readJsonResponse(response) {
        const payload = await response.json();

        if (!response.ok) {
            throw new Error(payload.error || payload.message || 'No se pudo completar la solicitud.');
        }

        return payload;
    }

    async function loadProspects(reset = true) {
        if (reset) {
            offset = 0;
            prospects = [];
        }

        tableBody.innerHTML = `
            <tr>
                <td colspan="5" class="text-center py-5 text-muted">
                    <span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                    Cargando prospectos...
                </td>
            </tr>`;

        const parameters = new URLSearchParams({
            limit: String(pageSize),
            offset: String(offset),
        });

        if (statusFilter.value) {
            parameters.set('status', statusFilter.value);
        }

        try {
            const response = await fetch(`${endpoints.list}?${parameters.toString()}`, {
                headers: {
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
            });
            const payload = await readJsonResponse(response);
            const pageProspects = Array.isArray(payload.prospects) ? payload.prospects : [];

            prospects = reset ? pageProspects : prospects.concat(pageProspects);
            offset += pageProspects.length;
            hasMore = pageProspects.length === pageSize;
            renderProspects();
        } catch (error) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center py-5 text-danger">
                        <i class="fas fa-triangle-exclamation me-2"></i>${escapeHtml(error.message)}
                    </td>
                </tr>`;
            document.getElementById('prospectsCount').textContent = 'No se pudieron cargar los prospectos';
            loadMoreButton.classList.add('d-none');
        }
    }

    function parseAttribution(value) {
        if (!value) {
            return null;
        }

        if (typeof value === 'object') {
            return value;
        }

        try {
            return JSON.parse(value);
        } catch (error) {
            return null;
        }
    }

    function renderMessages(messages) {
        currentMessages = Array.isArray(messages) ? messages : [];
        document.getElementById('messageCount').textContent =
            `${currentMessages.length} ${currentMessages.length === 1 ? 'mensaje' : 'mensajes'}`;

        if (currentMessages.length === 0) {
            document.getElementById('prospectMessages').innerHTML = `
                <div class="text-center text-muted py-5">
                    <i class="far fa-message fa-2x mb-3 d-block"></i>
                    No hay mensajes guardados para este prospecto.
                </div>`;
            return;
        }

        document.getElementById('prospectMessages').innerHTML = currentMessages.map(function(message) {
            const direction = message.Direction === 'outgoing' ? 'outgoing' : 'incoming';
            const directionLabel = direction === 'outgoing' ? 'Enviado' : 'Recibido';
            const content = message.Content || `Contenido no disponible (${message.MessageType || 'mensaje'})`;
            const deliveryStatus = message.DeliveryStatus
                ? `<span>${escapeHtml(message.DeliveryStatus)}</span>`
                : '';

            return `
                <article class="message-bubble ${direction}">
                    <div class="message-meta">
                        <span>${directionLabel} · ${escapeHtml(message.MessageType || 'tipo desconocido')}</span>
                        <span>${formatDate(message.MessageAt)}</span>
                    </div>
                    <p class="message-content">${escapeHtml(content)}</p>
                    ${deliveryStatus ? `<div class="message-meta mt-2 mb-0"><span>Entrega</span>${deliveryStatus}</div>` : ''}
                </article>`;
        }).join('');
    }

    function renderHistory(history) {
        currentHistory = Array.isArray(history) ? history : [];

        if (currentHistory.length === 0) {
            document.getElementById('statusHistory').innerHTML =
                '<div class="small text-muted">No hay cambios de estado registrados.</div>';
            return;
        }

        document.getElementById('statusHistory').innerHTML = currentHistory.map(function(change) {
            return `
                <div class="history-item">
                    <span class="history-date">${formatDate(change.ChangedAt)}</span>
                    <span>${escapeHtml(statusLabel(change.PreviousStatus))}</span>
                    <i class="fas fa-arrow-right text-muted small"></i>
                    <strong>${escapeHtml(statusLabel(change.NewStatus))}</strong>
                </div>`;
        }).join('');
    }

    async function openProspect(id) {
        selectedProspectId = id;
        document.getElementById('detailAlert').innerHTML = '';
        document.getElementById('prospectDetailTitle').textContent = 'Cargando prospecto...';
        document.getElementById('prospectMessages').innerHTML =
            '<div class="text-center text-muted py-5"><span class="spinner-border spinner-border-sm me-2"></span>Cargando conversación...</div>';
        detailModal.show();

        try {
            const response = await fetch(`${endpoints.detail}/${encodeURIComponent(id)}`, {
                headers: {
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
            });
            const payload = await readJsonResponse(response);
            const prospect = payload.prospect;

            if (!prospect) {
                throw new Error('No se encontró la ficha del prospecto.');
            }

            document.getElementById('prospectDetailTitle').textContent =
                prospect.Name || 'Nombre no disponible';
            document.getElementById('detailAvatar').innerHTML =
                `<span>${escapeHtml((prospect.Name || '?').trim().charAt(0).toUpperCase())}</span>`;

            const phone = prospect.PhoneNumber || prospect.WaId || '';
            const phoneDigits = phone.replace(/\D/g, '');
            document.getElementById('detailPhone').innerHTML = phoneDigits
                ? `<a href="https://wa.me/${escapeHtml(phoneDigits)}" target="_blank" rel="noopener noreferrer" class="link-success text-decoration-none">${escapeHtml(phone)} <i class="fas fa-arrow-up-right-from-square ms-1 small"></i></a>`
                : escapeHtml(phone || 'Teléfono no disponible');

            document.getElementById('detailFirstContact').textContent = formatDate(prospect.FirstContactAt);
            document.getElementById('detailLastContact').textContent = formatDate(prospect.LastContactAt);

            const origin = originDisplay(prospect);
            document.getElementById('detailOrigin').innerHTML =
                `<span class="origin-chip ${origin.className}"><i class="fas ${origin.icon}"></i>${escapeHtml(origin.label)}</span>`;
            document.getElementById('detailAdId').textContent = prospect.AdId || 'No proporcionado';
            document.getElementById('detailCampaignId').textContent = prospect.CampaignId || 'No proporcionada';

            const saleValue = prospect.SaleValue;
            document.getElementById('detailConversion').textContent = prospect.Status === 'customer'
                ? `${formatDate(prospect.ConvertedAt)}${saleValue !== null && saleValue !== '' ? ` · $${Number(saleValue).toLocaleString('es-MX', { minimumFractionDigits: 2 })}` : ''}`
                : 'Aún no es cliente';

            const attribution = parseAttribution(prospect.AttributionJson);
            const attributionDetails = document.getElementById('attributionDetails');
            attributionDetails.classList.toggle('d-none', attribution === null);
            document.getElementById('detailAttribution').textContent = attribution === null
                ? ''
                : JSON.stringify(attribution, null, 2);

            document.getElementById('detailStatus').value = prospect.Status || 'new';
            document.getElementById('detailNotes').value = prospect.Notes || '';
            document.getElementById('detailMarketingConsent').checked =
                Number(prospect.MarketingConsent) === 1 || prospect.MarketingConsent === true;
            document.getElementById('detailSaleValue').value = prospect.SaleValue ?? '';

            renderMessages(payload.messages);
            renderHistory(payload.status_history);
        } catch (error) {
            document.getElementById('prospectDetailTitle').textContent = 'No se pudo cargar el prospecto';
            document.getElementById('prospectMessages').innerHTML = '';
            showAlert(error.message, 'danger', 'detailAlert');
        }
    }

    tableBody.addEventListener('click', function(event) {
        const button = event.target.closest('[data-open-prospect]');
        const row = event.target.closest('[data-prospect-id]');
        const id = button?.dataset.openProspect || row?.dataset.prospectId;

        if (id) {
            openProspect(id);
        }
    });

    tableBody.addEventListener('keydown', function(event) {
        if (event.key !== 'Enter' && event.key !== ' ') {
            return;
        }

        const row = event.target.closest('[data-prospect-id]');

        if (row) {
            event.preventDefault();
            openProspect(row.dataset.prospectId);
        }
    });

    searchInput.addEventListener('input', renderProspects);
    statusFilter.addEventListener('change', function() {
        loadProspects();
    });
    refreshButton.addEventListener('click', function() {
        loadProspects();
    });
    loadMoreButton.addEventListener('click', function() {
        loadProspects(false);
    });

    manualCustomerOrigin.addEventListener('change', function() {
        manualAttributionFields.classList.toggle(
            'd-none',
            manualCustomerOrigin.value !== 'click_to_whatsapp',
        );
    });

    manualCountryCode.addEventListener('change', function() {
        const isMexico = manualCountryCode.value === '52';
        manualCustomerPhone.maxLength = isMexico ? 10 : 14;
        manualCustomerPhone.pattern = isMexico ? '[0-9]{10}' : '[0-9]{4,14}';
        manualCustomerPhone.placeholder = isMexico ? '10 dígitos' : 'Solo dígitos nacionales';
        document.getElementById('manualPhoneHelp').textContent = isMexico
            ? 'Escribe los 10 dígitos, sin el código de país ni el 1 de WhatsApp.'
            : 'Escribe el número sin código de país ni separadores; si tu país usa 0 como prefijo nacional, omítelo.';
    });

    manualCustomerPhone.addEventListener('input', function() {
        manualCustomerPhone.value = manualCustomerPhone.value.replace(/\D/g, '');
    });

    manualCustomerModalElement.addEventListener('show.bs.modal', function() {
        document.getElementById('manualPurchaseDate').value = toLocalDateTimeInput(new Date());
        document.getElementById('manualCustomerAlert').innerHTML = '';
    });

    manualCustomerForm.addEventListener('submit', async function(event) {
        event.preventDefault();

        const saveButton = document.getElementById('saveManualCustomer');
        const payload = {
            name: document.getElementById('manualCustomerName').value.trim() || null,
            country_calling_code: manualCountryCode.value,
            national_phone_number: manualCustomerPhone.value.trim(),
            converted_at: toUtcIsoString(document.getElementById('manualPurchaseDate').value),
            contact_at: toUtcIsoString(document.getElementById('manualContactDate').value),
            sale_value: document.getElementById('manualSaleValue').value === ''
                ? null
                : Number(document.getElementById('manualSaleValue').value),
            origin: manualCustomerOrigin.value,
            ctwa_clid: document.getElementById('manualCtwaClickId').value.trim() || null,
            source_id: document.getElementById('manualAdId').value.trim() || null,
            campaign_id: document.getElementById('manualCampaignId').value.trim() || null,
            notes: document.getElementById('manualCustomerNotes').value.trim() || null,
            marketing_consent: document.getElementById('manualMarketingConsent').checked,
        };

        if (!manualCustomerForm.reportValidity()) {
            return;
        }

        saveButton.disabled = true;
        saveButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Guardando...';
        document.getElementById('manualCustomerAlert').innerHTML = '';

        try {
            const response = await fetch(endpoints.manual, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload),
            });
            const result = await readJsonResponse(response);
            manualCustomerForm.reset();
            manualAttributionFields.classList.add('d-none');
            manualCustomerModal.hide();
            await loadProspects();

            if (result.purchase_event_status === 'sent') {
                showAlert(
                    'Este cliente ya tenía registrada su compra en Meta; no se creó un evento duplicado.',
                    'success',
                );
            } else if (result.purchase_event_status === 'failed') {
                showAlert(
                    result.purchase_event_error
                        || 'El cliente quedó guardado, pero su compra no se enviará a Meta.',
                    'warning',
                );
            } else if (result.origin !== 'click_to_whatsapp') {
                showAlert(
                    'El cliente quedó guardado. No se enviará a Meta porque no se confirmó que llegó desde un anuncio de WhatsApp.',
                    'success',
                );
            } else if (!result.marketing_consent) {
                showAlert(
                    'El cliente quedó guardado. No se enviará información a Meta porque no se indicó consentimiento.',
                    'success',
                );
            } else {
                const action = result.created ? 'registrado' : 'actualizado sin duplicar';
                showAlert(
                    `Cliente ${action}. La compra está en cola; usa "Enviar pendientes a Meta" cuando quieras despacharla.`,
                    'success',
                );
            }

            if (result.prospect_id) {
                await openProspect(result.prospect_id);
            }
        } catch (error) {
            showAlert(error.message, 'danger', 'manualCustomerAlert');
        } finally {
            saveButton.disabled = false;
            saveButton.innerHTML = '<i class="fas fa-floppy-disk me-2"></i>Guardar cliente';
        }
    });

    dispatchButton.addEventListener('click', async function() {
        const confirmed = window.confirm(
            'Se enviarán a Meta hasta 50 eventos pendientes con origen Click-to-WhatsApp confirmado y consentimiento. Los eventos rechazados no se volverán a enviar automáticamente. ¿Continuar?',
        );

        if (!confirmed) {
            return;
        }

        dispatchButton.disabled = true;
        dispatchButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Enviando...';

        try {
            const response = await fetch(endpoints.dispatch, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
            });
            const payload = await readJsonResponse(response);
            const results = payload.results || {};
            showAlert(
                `Resultado de Meta: ${results.sent || 0} enviadas, ${results.pending || 0} pendientes para reintento y ${results.failed || 0} rechazadas.`,
                results.failed ? 'warning' : 'success',
            );
        } catch (error) {
            showAlert(error.message, 'danger');
        } finally {
            dispatchButton.disabled = false;
            dispatchButton.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Enviar pendientes a Meta';
        }
    });

    updateForm.addEventListener('submit', async function(event) {
        event.preventDefault();

        if (!selectedProspectId) {
            return;
        }

        const saveButton = document.getElementById('saveProspect');
        const status = document.getElementById('detailStatus').value;
        const payload = {
            status: status,
            notes: document.getElementById('detailNotes').value,
            marketing_consent: document.getElementById('detailMarketingConsent').checked,
        };
        const saleValue = document.getElementById('detailSaleValue').value;

        if (status === 'customer' && saleValue !== '') {
            payload.sale_value = Number(saleValue);
        }

        saveButton.disabled = true;
        saveButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Guardando...';
        document.getElementById('detailAlert').innerHTML = '';

        try {
            const response = await fetch(`${endpoints.detail}/${encodeURIComponent(selectedProspectId)}/status`, {
                method: 'PATCH',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload),
            });
            await readJsonResponse(response);
            showAlert('Seguimiento guardado correctamente.', 'success', 'detailAlert');
            await loadProspects();
            await openProspect(selectedProspectId);
        } catch (error) {
            showAlert(error.message, 'danger', 'detailAlert');
        } finally {
            saveButton.disabled = false;
            saveButton.innerHTML = '<i class="fas fa-floppy-disk me-2"></i>Guardar seguimiento';
        }
    });

    loadProspects();
});
</script>
<?php
$customScripts = ob_get_clean();
$content = ob_get_clean();
include __DIR__ . '/../layouts/app.php';
?>
