<?php
// local/mai/participacion.php

/**
 * Seguimiento de participación estudiantil (vista principal).
 *
 * @package   local_mai
 */

require(__DIR__ . '/../../config.php');

global $DB;

require_login();

$courseid   = optional_param('courseid', 0, PARAM_INT);
$categoryid = optional_param('categoryid', 0, PARAM_INT);
$cohortid   = optional_param('cohortid', 0, PARAM_INT);
$groupid    = optional_param('groupid', 0, PARAM_INT);
$roleid     = optional_param('roleid', 0, PARAM_INT);

$systemcontext = context_system::instance();
require_capability('local/mai:viewreport', $systemcontext);

$pagetitle = 'Seguimiento de participación estudiantil';

$params = [];
if ($courseid)   { $params['courseid']   = $courseid; }
if ($categoryid) { $params['categoryid'] = $categoryid; }
if ($cohortid)   { $params['cohortid']   = $cohortid; }
if ($groupid)    { $params['groupid']    = $groupid; }
if ($roleid)     { $params['roleid']     = $roleid; }

$PAGE->set_url(new moodle_url('/local/mai/participacion.php', $params));
$PAGE->set_context($systemcontext);
$PAGE->set_pagelayout('report');

echo $OUTPUT->header();
echo $OUTPUT->heading($pagetitle);

// --- Carga segura de ApexCharts (sin AMD/RequireJS) ---
echo '<script>
    window.__apex_define = window.define;
    window.define = undefined;
</script>';
echo '<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>';
echo '<script>
    window.define = window.__apex_define;
</script>';

// --- DataTables: CSS + jQuery + plugin ---
echo html_writer::empty_tag('link', [
    'rel'  => 'stylesheet',
    'href' => 'https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css'
]);
// jQuery antes de DataTables (para evitar "jQuery is not defined").
echo '<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>';
echo '<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>';

// =====================
// Filtros: curso, categoría, cohorte, grupo, rol
// =====================

// Categorías de curso.
$categoriesmenu = [0 => 'Todas las categorías'];
$categorieslist = core_course_category::make_categories_list();
$categoriesmenu += $categorieslist;

// Cursos.
$coursesmenu = [0 => 'Todos los cursos'];
foreach (get_courses() as $c) {
    if ($c->id == SITEID) {
        continue;
    }
    $coursesmenu[$c->id] = format_string($c->fullname);
}

// Cohortes.
$cohortsmenu = [0 => 'Todas las cohortes'];
$cohorts = $DB->get_records('cohort', null, 'name ASC', 'id, name');
foreach ($cohorts as $co) {
    $cohortsmenu[$co->id] = format_string($co->name);
}

// Grupos.
$groupsmenu = [0 => 'Todos los grupos'];
$groups = $DB->get_records('groups', null, 'name ASC', 'id, name');
foreach ($groups as $g) {
    $groupsmenu[$g->id] = format_string($g->name);
}

// Roles.
$rolesmenu = [0 => 'Todos los roles'];
$roles = get_all_roles();
foreach ($roles as $r) {
    $rolesmenu[$r->id] = role_get_name($r, $systemcontext, ROLENAME_BOTH);
}

// ============================
// CSS
// ============================

$css = "
.local-mai-participation-layout {
    width: 100%;
    max-width: 1200px;
    margin: 0 auto 32px;
    display: flex;
    gap: 22px;
    align-items: flex-start;
    font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;

    --mai-maroon: #8C253E;
    --mai-orange: #FF7000;
    --mai-bg-soft: #f8fafc;
    --mai-border-soft: #e5e7eb;
    --mai-text-main: #111827;
    --mai-text-muted: #6b7280;
}

.local-mai-layout-left {
    flex: 0 0 380px;
}

.local-mai-layout-right {
    flex: 1 1 auto;
    min-width: 0;
}

/* Encabezado core */
#page-local-mai-participacion .page-header-headings h1,
#page-local-mai-participacion .page-header-headings h2 {
    color: var(--mai-maroon);
    font-weight: 700;
}

/* FILTROS en tarjeta IZQUIERDA */
.local-mai-filters {
    width: 100%;
    padding: 16px 18px 14px;
    border-radius: 16px;
    border: 1px solid var(--mai-border-soft);
    background: #ffffff;
    box-shadow: 0 10px 20px rgba(15, 23, 42, 0.06);
}
#local-mai-filters-form {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    gap: 10px 0;
}
.local-mai-filters .form-group {
    width: 100%;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
}
.local-mai-filters label {
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--mai-text-muted);
    margin: 0 0 2px 4px;
}
.local-mai-filters select.custom-select {
    width: 100%;
    border-radius: 999px;
    border: 1px solid var(--mai-border-soft);
    font-size: 0.85rem;
    padding: 6px 30px 6px 12px;
    background-color: #f9fafb;
}
.local-mai-filters select.custom-select:focus {
    outline: none;
    border-color: var(--mai-orange);
    box-shadow: 0 0 0 1px rgba(255, 112, 0, 0.2);
}

/* BOTONES BRAND */
.local-mai-btn-primary,
.local-mai-btn-neutral,
.local-mai-btn-ghost {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    white-space: nowrap;
    cursor: pointer;
}

.local-mai-btn-primary {
    background: linear-gradient(135deg, var(--mai-maroon), var(--mai-orange));
    border: none;
    color: #ffffff;
    border-radius: 999px;
    padding: 7px 20px;
    font-size: 0.82rem;
    font-weight: 600;
    box-shadow: 0 8px 18px rgba(140, 37, 62, 0.25);
}
.local-mai-btn-primary:hover,
.local-mai-btn-primary:focus {
    color: #ffffff;
    filter: brightness(1.05);
    transform: translateY(-1px);
}

/* EXPORT CARD (columna derecha) */
.local-mai-export-card h3 {
    margin-bottom: 8px;
}
.local-mai-export-row {
    display: flex;
    flex-direction: column;
    gap: 8px;
    width: 100%;
}
.local-mai-export-label {
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--mai-maroon);
}

/* Chips tipo switch para columnas */
.local-mai-export-columns {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px;
    font-size: 0.8rem;
}
.local-mai-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    border-radius: 999px;
    border: 1px solid var(--mai-border-soft);
    background: #f9fafb;
    cursor: pointer;
}
.local-mai-chip input[type='checkbox'] {
    appearance: none;
    -webkit-appearance: none;
    width: 26px;
    height: 16px;
    border-radius: 999px;
    background: #e5e7eb;
    position: relative;
    outline: none;
    border: none;
    transition: background 0.18s ease;
}
.local-mai-chip input[type='checkbox']::before {
    content: '';
    position: absolute;
    width: 12px;
    height: 12px;
    border-radius: 999px;
    background: #ffffff;
    top: 2px;
    left: 2px;
    box-shadow: 0 1px 4px rgba(15,23,42,0.35);
    transition: transform 0.18s ease;
}
.local-mai-chip input[type='checkbox']:checked {
    background: linear-gradient(135deg, var(--mai-maroon), var(--mai-orange));
}
.local-mai-chip input[type='checkbox']:checked::before {
    transform: translateX(10px);
}
.local-mai-chip-text {
    font-size: 0.78rem;
    color: var(--mai-text-muted);
    font-weight: 500;
}
.local-mai-chip input[type='checkbox']:checked + .local-mai-chip-text {
    color: var(--mai-maroon);
}

/* Botones secundarios export */
.local-mai-export-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.local-mai-btn-neutral {
    background: #ffffff;
    border: 1px solid var(--mai-border-soft);
    color: var(--mai-maroon);
    border-radius: 999px;
    padding: 6px 16px;
    font-size: 0.82rem;
    font-weight: 600;
}
.local-mai-btn-neutral:hover,
.local-mai-btn-neutral:focus {
    border-color: var(--mai-orange);
    color: var(--mai-orange);
    background: #f9fafb;
}
.local-mai-btn-ghost {
    background: transparent;
    border: 1px dashed var(--mai-border-soft);
    color: var(--mai-text-muted);
    border-radius: 999px;
    padding: 6px 16px;
    font-size: 0.8rem;
    font-weight: 500;
}
.local-mai-btn-ghost:hover,
.local-mai-btn-ghost:focus {
    border-style: solid;
    border-color: var(--mai-orange);
    color: var(--mai-orange);
}

/* COLUMNA DERECHA */
.local-mai-participation-wrapper {
    padding: 4px 0 24px;
    font-family: inherit;
    min-width: 0;
}

/* ALERTAS */
.local-mai-participation-wrapper .alert {
    border-radius: 999px;
    padding: 8px 14px;
    font-size: 0.82rem;
    border: none;
    margin-bottom: 18px;
}
.local-mai-participation-wrapper .alert-info {
    background: rgba(140, 37, 62, 0.06);
    color: var(--mai-text-main);
    border-left: 3px solid var(--mai-maroon);
}
.local-mai-participation-wrapper .alert-warning {
    background: rgba(255, 112, 0, 0.06);
    color: var(--mai-text-main);
    border-left: 3px solid var(--mai-orange);
}
.local-mai-participation-wrapper .alert-danger {
    background: #fef2f2;
    color: #b91c1c;
    border-left: 3px solid #ef4444;
}

/* BADGES SEMÁFORO */
.local-mai-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 0.8rem;
    font-weight: 600;
    color: #fff;
}
.local-mai-badge.green { background-color: #28a745; }
.local-mai-badge.yellow { background-color: #ffc107; color: #333; }
.local-mai-badge.red { background-color: #dc3545; }

/* TARJETAS DERECHA */
.local-mai-card {
    border-radius: 16px;
    border: 1px solid var(--mai-border-soft);
    padding: 16px 18px;
    margin-bottom: 24px;
    background-color: #ffffff;
    box-shadow: 0 10px 22px rgba(15, 23, 42, 0.06);
    overflow-x: hidden;
}
.local-mai-card h3 {
    margin-top: 0;
    margin-bottom: 10px;
    font-size: 1.02rem;
    font-weight: 600;
    color: var(--mai-text-main);
}

/* GAUGES (ahora siempre caben en la tarjeta) */
.local-mai-gauges-row {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    max-width: 100%;
}

.local-mai-gauge {
    flex: 1 1 calc(33.333% - 16px);
    min-width: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
}

.local-mai-gauge-title {
    font-weight: 600;
    margin-bottom: 6px;
    font-size: 0.9rem;
    color: var(--mai-text-main);
}

/* Contenedor del chart para que no se desborde */
.local-mai-gauge-chart {
    width: 100%;
    max-width: 230px;
}

/* TABS TABLAS */
.local-mai-tabs {
    display: inline-flex;
    gap: 6px;
    padding: 2px;
    border-radius: 999px;
    background: #f3f4f6;
    margin-bottom: 10px;
}
.local-mai-tab {
    border: none;
    background: transparent;
    padding: 5px 12px;
    border-radius: 999px;
    font-size: 0.82rem;
    font-weight: 500;
    color: var(--mai-text-muted);
    cursor: pointer;
}
.local-mai-tab.active {
    background: #ffffff;
    color: var(--mai-maroon);
    box-shadow: 0 4px 10px rgba(15, 23, 42, 0.08);
}
.local-mai-tab-panels {
    margin-top: 6px;
}
.local-mai-tab-panel {
    display: none;
}
.local-mai-tab-panel.active {
    display: block;
}

/* TABLAS DENTRO DE TABS */
.local-mai-card table {
    border-collapse: collapse;
    font-size: 0.9rem;
}
.local-mai-card table th,
.local-mai-card table td {
    padding: 6px 8px;
    border-bottom: 1px solid #f3f4f6;
}
.local-mai-card table th {
    background-color: rgba(140, 37, 62, 0.03);
    font-weight: 600;
    color: var(--mai-text-muted);
}

/* DataTables limpio y contenido dentro de la tarjeta */
.local-mai-card .dataTables_wrapper {
    margin-top: 8px;
    overflow-x: auto;
}
.local-mai-card table.dataTable {
    width: 100% !important;
}
.local-mai-card .dataTables_length label,
.local-mai-card .dataTables_filter label {
    font-size: 0.8rem;
    color: var(--mai-text-muted);
}
.local-mai-card .dataTables_filter input {
    border-radius: 999px;
    border: 1px solid var(--mai-border-soft);
    padding: 2px 8px;
    font-size: 0.8rem;
}
.local-mai-card .dataTables_paginate a {
    font-size: 0.8rem;
}
.local-mai-card table.dataTable.no-footer {
    border-bottom: none;
}

/* RESPONSIVE */
@media (max-width: 900px) {
    .local-mai-participation-layout {
        flex-direction: column;
        max-width: 100%;
        padding: 0 12px 24px;
    }
    .local-mai-layout-left,
    .local-mai-layout-right {
        flex: 1 1 100%;
    }
    .local-mai-gauge {
        flex: 1 1 calc(50% - 16px);
    }
}
";
echo html_writer::tag('style', $css);

// ============================
// LAYOUT: 2 columnas
// ============================

echo html_writer::start_div('local-mai-participation-layout');

// -------- COLUMNA IZQUIERDA: filtros --------
echo html_writer::start_div('local-mai-layout-left');

echo html_writer::start_div('local-mai-filters');

echo html_writer::start_tag('form', [
    'method' => 'get',
    'class'  => 'form-inline flex-wrap',
    'id'     => 'local-mai-filters-form'
]);

// Categoría.
echo html_writer::start_div('form-group');
echo html_writer::label('Categoría', 'id_categoryid');
echo html_writer::select($categoriesmenu, 'categoryid', $categoryid, null, [
    'id'    => 'id_categoryid',
    'class' => 'custom-select'
]);
echo html_writer::end_div();

// Curso.
echo html_writer::start_div('form-group');
echo html_writer::label('Curso', 'id_courseid');
echo html_writer::select($coursesmenu, 'courseid', $courseid, null, [
    'id'    => 'id_courseid',
    'class' => 'custom-select'
]);
echo html_writer::end_div();

// Cohorte.
echo html_writer::start_div('form-group');
echo html_writer::label('Cohorte', 'id_cohortid');
echo html_writer::select($cohortsmenu, 'cohortid', $cohortid, null, [
    'id'    => 'id_cohortid',
    'class' => 'custom-select'
]);
echo html_writer::end_div();

// Grupo.
echo html_writer::start_div('form-group');
echo html_writer::label('Grupo', 'id_groupid');
echo html_writer::select($groupsmenu, 'groupid', $groupid, null, [
    'id'    => 'id_groupid',
    'class' => 'custom-select'
]);
echo html_writer::end_div();

// Rol.
echo html_writer::start_div('form-group');
echo html_writer::label('Rol', 'id_roleid');
echo html_writer::select($rolesmenu, 'roleid', $roleid, null, [
    'id'    => 'id_roleid',
    'class' => 'custom-select'
]);
echo html_writer::end_div();

// Botón aplicar.
echo html_writer::start_div('form-group');
echo html_writer::empty_tag('input', [
    'type'  => 'submit',
    'value' => 'Aplicar filtros',
    'class' => 'local-mai-btn-primary'
]);
echo html_writer::end_div();

echo html_writer::end_tag('form');
echo html_writer::end_div(); // local-mai-filters

echo html_writer::end_div(); // layout-left

// -------- COLUMNA DERECHA: loader + semáforo + export + tabs/tablas --------
echo html_writer::start_div('local-mai-layout-right');
echo html_writer::start_div('local-mai-participation-wrapper');

// Loader / mensaje inicial.
echo html_writer::tag('div',
    'Selecciona un curso y haz clic en \"Aplicar filtros\" para ver la participación.',
    ['id' => 'local-mai-participation-loading', 'class' => 'alert alert-info']
);

// Tarjeta de gauges (semáforo).
echo html_writer::start_div('local-mai-card');
echo html_writer::tag('h3', 'Semáforo de participación');
echo html_writer::start_div('local-mai-gauges-row', ['id' => 'local-mai-participation-gauges']);
echo html_writer::end_div();
echo html_writer::end_div();

// Card EXPORT (inicialmente oculta)
echo html_writer::start_div('local-mai-card local-mai-export-card', [
    'id'    => 'local-mai-export-card',
    'style' => 'display:none;'
]);
echo html_writer::tag('h3', 'Exportar resultados');

echo html_writer::start_div('local-mai-export-row');

// Columnas export.
echo html_writer::start_div('local-mai-export-columns');
echo html_writer::tag('span', 'Configuración ·', ['class' => 'local-mai-export-label']);
echo html_writer::tag('span', 'Columnas:', []);

echo html_writer::start_tag('label', ['class' => 'local-mai-chip']);
echo html_writer::empty_tag('input', [
    'type'    => 'checkbox',
    'id'      => 'col_email',
    'checked' => 'checked'
]);
echo html_writer::tag('span', 'Correo', ['class' => 'local-mai-chip-text']);
echo html_writer::end_tag('label');

echo html_writer::start_tag('label', ['class' => 'local-mai-chip']);
echo html_writer::empty_tag('input', [
    'type'    => 'checkbox',
    'id'      => 'col_cohort',
    'checked' => 'checked'
]);
echo html_writer::tag('span', 'Cohorte', ['class' => 'local-mai-chip-text']);
echo html_writer::end_tag('label');

echo html_writer::start_tag('label', ['class' => 'local-mai-chip']);
echo html_writer::empty_tag('input', [
    'type'    => 'checkbox',
    'id'      => 'col_group',
    'checked' => 'checked'
]);
echo html_writer::tag('span', 'Grupo', ['class' => 'local-mai-chip-text']);
echo html_writer::end_tag('label');

echo html_writer::start_tag('label', ['class' => 'local-mai-chip']);
echo html_writer::empty_tag('input', [
    'type'    => 'checkbox',
    'id'      => 'col_lastaccess',
    'checked' => 'checked'
]);
echo html_writer::tag('span', 'Último acceso', ['class' => 'local-mai-chip-text']);
echo html_writer::end_tag('label');

echo html_writer::start_tag('label', ['class' => 'local-mai-chip']);
echo html_writer::empty_tag('input', [
    'type'    => 'checkbox',
    'id'      => 'col_enroltime',
    'checked' => 'checked'
]);
echo html_writer::tag('span', 'Fecha de matrícula', ['class' => 'local-mai-chip-text']);
echo html_writer::end_tag('label');

echo html_writer::end_div(); // export-columns

// Botones export.
echo html_writer::start_div('local-mai-export-buttons');
echo html_writer::tag('button', 'Excel', [
    'type'  => 'button',
    'class' => 'local-mai-btn-neutral',
    'id'    => 'local-mai-export-excel'
]);
echo html_writer::tag('button', 'PDF', [
    'type'  => 'button',
    'class' => 'local-mai-btn-ghost',
    'id'    => 'local-mai-export-pdf'
]);
echo html_writer::tag('button', 'CSV', [
    'type'  => 'button',
    'class' => 'local-mai-btn-ghost',
    'id'    => 'local-mai-export-csv'
]);
echo html_writer::end_div(); // export-buttons

echo html_writer::end_div(); // export-row
echo html_writer::end_div(); // export-card

// Card de tabs/tablas (Activos / Inactivos / Nunca ingresaron)
echo html_writer::start_div('', ['id' => 'local-mai-participation-tables']);
echo html_writer::start_div('local-mai-card');
echo html_writer::tag('h3', 'Detalle por estudiante');
echo '
    <div class="local-mai-tabs">
        <button type="button" class="local-mai-tab active" data-tab="activos">Activos</button>
        <button type="button" class="local-mai-tab" data-tab="inactivos">Inactivos</button>
        <button type="button" class="local-mai-tab" data-tab="nunca">Nunca ingresaron</button>
    </div>
    <div class="local-mai-tab-panels">
        <div id="local-mai-tab-activos" class="local-mai-tab-panel active"></div>
        <div id="local-mai-tab-inactivos" class="local-mai-tab-panel"></div>
        <div id="local-mai-tab-nunca" class="local-mai-tab-panel"></div>
    </div>
';
echo html_writer::end_div(); // card
echo html_writer::end_div(); // participation-tables

echo html_writer::end_div(); // participation-wrapper
echo html_writer::end_div(); // layout-right

echo html_writer::end_div(); // participation-layout

// ============================
// JS inline (sin AMD)
// ============================

$ajaxurl    = (new moodle_url('/local/mai/participacion/ajax.php'))->out(false);
$exporturl  = (new moodle_url('/local/mai/participacion/export.php'))->out(false);
$sesskey    = sesskey();

?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var ajaxUrl        = '<?php echo $ajaxurl; ?>';
    var exportUrlBase  = '<?php echo $exporturl; ?>';
    var sesskey        = '<?php echo $sesskey; ?>';

    var form       = document.getElementById('local-mai-filters-form');
    var loadingEl  = document.getElementById('local-mai-participation-loading');
    var gaugesEl   = document.getElementById('local-mai-participation-gauges');
    var exportCard = document.getElementById('local-mai-export-card');

    var tabActivos   = document.getElementById('local-mai-tab-activos');
    var tabInactivos = document.getElementById('local-mai-tab-inactivos');
    var tabNunca     = document.getElementById('local-mai-tab-nunca');

    var exportExcelBtn = document.getElementById('local-mai-export-excel');
    var exportPdfBtn   = document.getElementById('local-mai-export-pdf');
    var exportCsvBtn   = document.getElementById('local-mai-export-csv');

    function toggleExportCard(show) {
        if (!exportCard) { return; }
        exportCard.style.display = show ? 'block' : 'none';
    }

    // --- Tabs comportamiento ---
    document.querySelectorAll('.local-mai-tab').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var tab = btn.getAttribute('data-tab');
            document.querySelectorAll('.local-mai-tab').forEach(function(b) {
                b.classList.toggle('active', b === btn);
            });
            document.querySelectorAll('.local-mai-tab-panel').forEach(function(panel) {
                panel.classList.remove('active');
            });
            if (tab === 'activos') {
                tabActivos.classList.add('active');
            } else if (tab === 'inactivos') {
                tabInactivos.classList.add('active');
            } else {
                tabNunca.classList.add('active');
            }
        });
    });

    function getFilters() {
        function getVal(id) {
            var el = document.getElementById(id);
            return el ? el.value : '0';
        }
        return {
            courseid:   getVal('id_courseid'),
            categoryid: getVal('id_categoryid'),
            cohortid:   getVal('id_cohortid'),
            groupid:    getVal('id_groupid'),
            roleid:     getVal('id_roleid')
        };
    }

    function getExportColumns() {
        function isChecked(id) {
            var el = document.getElementById(id);
            return el ? el.checked : false;
        }
        return {
            email:      isChecked('col_email'),
            cohort:     isChecked('col_cohort'),
            group:      isChecked('col_group'),
            lastaccess: isChecked('col_lastaccess'),
            enroltime:  isChecked('col_enroltime')
        };
    }

    function buildExportUrl(format) {
        var filters = getFilters();

        if (!filters.courseid || filters.courseid === '0') {
            alert('Selecciona un curso antes de exportar.');
            return null;
        }

        var cols = getExportColumns();

        var params = new URLSearchParams();
        params.append('format',     format);
        params.append('courseid',   filters.courseid);
        params.append('categoryid', filters.categoryid || 0);
        params.append('cohortid',   filters.cohortid || 0);
        params.append('groupid',    filters.groupid || 0);
        params.append('roleid',     filters.roleid || 0);
        params.append('sesskey',    sesskey);

        Object.keys(cols).forEach(function(key) {
            params.append('col_' + key, cols[key] ? 1 : 0);
        });

        return exportUrlBase + '?' + params.toString();
    }

    // --- Gauges ---
    function renderGauges(counts, total) {
        gaugesEl.innerHTML = '';

        if (!total) {
            gaugesEl.innerHTML = '<p class=\"text-muted\">No hay estudiantes que coincidan con los filtros seleccionados.</p>';
            return;
        }

        var keys   = ['active', 'inactive', 'never'];
        var titles = ['Activos', 'Inactivos', 'Nunca ingresaron'];
        var colors = ['#28a745', '#ffc107', '#dc3545']; // semáforo

        for (var i = 0; i < keys.length; i++) {
            var key   = keys[i];
            var title = titles[i];
            var color = colors[i];

            var count   = counts[key] || 0;
            var percent = total > 0 ? Math.round((count * 100) / total) : 0;

            var gaugeWrapper = document.createElement('div');
            gaugeWrapper.className = 'local-mai-gauge';

            var titleEl = document.createElement('div');
            titleEl.className = 'local-mai-gauge-title';
            titleEl.textContent = title + ' (' + count + '/' + total + ')';
            gaugeWrapper.appendChild(titleEl);

            var chartDiv = document.createElement('div');
            chartDiv.id = 'local-mai-gauge-' + key;
            chartDiv.className = 'local-mai-gauge-chart';
            gaugeWrapper.appendChild(chartDiv);

            gaugesEl.appendChild(gaugeWrapper);

            if (typeof ApexCharts !== 'undefined') {
                var options = {
                    chart: {
                        type: 'radialBar',
                        height: 170,
                        sparkline: {
                            enabled: true
                        }
                    },
                    series: [percent],
                    labels: [title],
                    colors: [color],
                    plotOptions: {
                        radialBar: {
                            startAngle: -90,
                            endAngle: 90,
                            hollow: {
                                margin: 0,
                                size: '65%'
                            },
                            track: {
                                background: '#f3f4f6',
                                strokeWidth: '100%'
                            },
                            dataLabels: {
                                name: {
                                    fontSize: '13px',
                                    offsetY: 28
                                },
                                value: {
                                    fontSize: '18px',
                                    formatter: function(val) {
                                        return Math.round(val) + '%';
                                    },
                                    offsetY: -12
                                }
                            }
                        }
                    }
                };
                var chart = new ApexCharts(chartDiv, options);
                chart.render();
            }
        }
    }

    // --- DataTables helper ---
    function initDataTable(selector) {
        if (!window.jQuery || !jQuery.fn.DataTable) {
            return;
        }
        var $ = jQuery;
        if ($.fn.DataTable.isDataTable(selector)) {
            $(selector).DataTable().destroy();
        }
        $(selector).DataTable({
            pageLength: 25,
            lengthMenu: [10, 25, 50, 100],
            ordering: true,
            searching: true,
            info: true,
            scrollX: true,
            language: {
                decimal: ',',
                thousands: '.',
                lengthMenu: 'Mostrar _MENU_ registros',
                zeroRecords: 'No se encontraron resultados',
                info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
                infoEmpty: 'Mostrando 0 a 0 de 0 registros',
                infoFiltered: '(filtrado de _MAX_ registros totales)',
                search: 'Buscar:',
                paginate: {
                    first: 'Primero',
                    last: 'Último',
                    next: 'Siguiente',
                    previous: 'Anterior'
                }
            }
        });
    }

    // --- Tablas (rellena cada panel) ---
    function renderTables(data) {
        var activos   = data.activos || [];
        var inactivos = data.inactivos || [];
        var nunca     = data.nuncaingresaron || [];

        function buildTable(id, headers, rows, rowRenderer) {
            if (!rows.length) {
                return '<p class=\"text-muted\">No se encontraron estudiantes.</p>';
            }
            var html = '<table id=\"' + id + '\" class=\"display compact\" style=\"width:100%\">';
            html += '<thead><tr>';
            headers.forEach(function(h) { html += '<th>' + h + '</th>'; });
            html += '</tr></thead><tbody>';
            rows.forEach(function(r) { html += rowRenderer(r); });
            html += '</tbody></table>';
            return html;
        }

        // Activos
        tabActivos.innerHTML = buildTable(
            'local-mai-table-activos',
            ['Nombre completo', 'Correo', 'Act.Completadas', 'Avance', 'Último acceso'],
            activos,
            function(r) {
                return '<tr>' +
                    '<td>' + r.fullname + '</td>' +
                    '<td>' + r.email + '</td>' +
                    '<td>' + r.completedactivities + '</td>' +
                    '<td>' + r.progress + '%</td>' +
                    '<td>' + r.lastaccess + '</td>' +
                '</tr>';
            }
        );

        // Inactivos
        tabInactivos.innerHTML = buildTable(
            'local-mai-table-inactivos',
            ['Nombre completo', 'Correo', 'Último acceso', 'Minutos (aprox.)', 'Clics'],
            inactivos,
            function(r) {
                return '<tr>' +
                    '<td>' + r.fullname + '</td>' +
                    '<td>' + r.email + '</td>' +
                    '<td>' + r.lastaccess + '</td>' +
                    '<td>' + r.minutes + '</td>' +
                    '<td>' + r.clicks + '</td>' +
                '</tr>';
            }
        );

        // Nunca ingresaron
        tabNunca.innerHTML = buildTable(
            'local-mai-table-nunca',
            ['Nombre completo', 'Correo', 'Cohorte', 'Grupo', 'Fecha de matrícula'],
            nunca,
            function(r) {
                return '<tr>' +
                    '<td>' + r.fullname + '</td>' +
                    '<td>' + r.email + '</td>' +
                    '<td>' + r.cohort + '</td>' +
                    '<td>' + r.group + '</td>' +
                    '<td>' + r.enroltime + '</td>' +
                '</tr>';
            }
        );

        // Si todo vacío → mensaje general.
        if (!activos.length && !inactivos.length && !nunca.length) {
            tabActivos.innerHTML = '<div class=\"alert alert-warning\">No se encontraron estudiantes con los filtros seleccionados.</div>';
            tabInactivos.innerHTML = '';
            tabNunca.innerHTML = '';
            return;
        }

        // Inicializar DataTables
        initDataTable('#local-mai-table-activos');
        initDataTable('#local-mai-table-inactivos');
        initDataTable('#local-mai-table-nunca');
    }

    // --- Carga participación ---
    function loadParticipation(filters) {
        if (!filters.courseid || filters.courseid === '0') {
            loadingEl.className = 'alert alert-warning';
            loadingEl.textContent = 'Debes seleccionar un curso antes de aplicar filtros.';
            loadingEl.style.display = 'block';
            gaugesEl.innerHTML = '';
            tabActivos.innerHTML = '';
            tabInactivos.innerHTML = '';
            tabNunca.innerHTML = '';
            toggleExportCard(false);
            return;
        }

        loadingEl.className = 'alert alert-info';
        loadingEl.textContent = 'Cargando información de participación...';
        loadingEl.style.display = 'block';

        var params = new URLSearchParams();
        params.append('courseid',   filters.courseid);
        params.append('categoryid', filters.categoryid || 0);
        params.append('cohortid',   filters.cohortid || 0);
        params.append('groupid',    filters.groupid || 0);
        params.append('roleid',     filters.roleid || 0);
        params.append('sesskey',    sesskey);

        fetch(ajaxUrl + '?' + params.toString(), {
            credentials: 'same-origin'
        }).then(function(response) {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.json();
        }).then(function(data) {
            loadingEl.style.display = 'none';
            renderGauges(data.counts || {}, data.total || 0);
            renderTables(data);
            toggleExportCard(true); // mostrar export solo con datos cargados
        }).catch(function(err) {
            console.error(err);
            loadingEl.className = 'alert alert-danger';
            loadingEl.textContent = 'Ocurrió un error al cargar la información de participación.';
            loadingEl.style.display = 'block';
            gaugesEl.innerHTML = '';
            tabActivos.innerHTML = '';
            tabInactivos.innerHTML = '';
            tabNunca.innerHTML = '';
            toggleExportCard(false);
        });
    }

    // Eventos
    if (form) {
        form.addEventListener('submit', function(ev) {
            ev.preventDefault();
            loadParticipation(getFilters());
        });
    }

    if (exportExcelBtn) {
        exportExcelBtn.addEventListener('click', function() {
            var url = buildExportUrl('xlsx');
            if (url) {
                window.location.href = url;
            }
        });
    }

    if (exportPdfBtn) {
        exportPdfBtn.addEventListener('click', function() {
            var url = buildExportUrl('pdf');
            if (url) {
                window.location.href = url;
            }
        });
    }

    if (exportCsvBtn) {
        exportCsvBtn.addEventListener('click', function() {
            var url = buildExportUrl('csv');
            if (url) {
                window.location.href = url;
            }
        });
    }

    <?php if ($courseid) : ?>
    loadParticipation({
        courseid:   String(<?php echo (int)$courseid; ?>),
        categoryid: String(<?php echo (int)$categoryid; ?>),
        cohortid:   String(<?php echo (int)$cohortid; ?>),
        groupid:    String(<?php echo (int)$groupid; ?>),
        roleid:     String(<?php echo (int)$roleid; ?>)
    });
    <?php endif; ?>
});
</script>
<?php

echo $OUTPUT->footer();
