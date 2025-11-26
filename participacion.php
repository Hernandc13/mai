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
$PAGE->set_title($pagetitle);

// jQuery de Moodle.
$PAGE->requires->jquery();

echo $OUTPUT->header();
echo $OUTPUT->heading($pagetitle);
// Botón para volver al dashboard principal de MAI.
$backurl = new moodle_url('/local/mai/index.php');

echo html_writer::start_div('local-mai-panel-back');

$icon  = html_writer::tag('i', '', [
    'class' => 'fa-solid fa-arrow-left local-mai-btn-back-icon',
    'aria-hidden' => 'true'
]);
$label = html_writer::tag('span', 'Volver al Menú');

echo html_writer::tag('a', $icon . $label, [
    'href'  => $backurl->out(false),
    'class' => 'local-mai-btn-back'
]);

echo html_writer::end_div();

// --- Carga segura de ApexCharts (sin AMD/RequireJS) ---
echo '<script>
    window.__apex_define = window.define;
    window.define = undefined;
</script>';
echo '<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>';
echo '<script>
    window.define = window.__apex_define;
</script>';

// --- DataTables por CDN (SOLO plugin, sin jQuery externo) ---
echo html_writer::empty_tag('link', [
    'rel'  => 'stylesheet',
    'href' => 'https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css'
]);
echo '<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>';

// --- Font Awesome para iconos de exportación / cards ---
echo html_writer::empty_tag('link', [
    'rel'  => 'stylesheet',
    'href' => 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css'
]);

// =====================
// Filtros: curso, categoría, cohorte, grupo, rol
// =====================

// Categorías de curso.
$categoriesmenu = [0 => 'Todas las categorías'];
$categorieslist = core_course_category::make_categories_list();
$categoriesmenu += $categorieslist;

// Cursos (para PHP y para JS dependiente).
$coursesmenu = [0 => 'Todos los cursos'];
$coursesdata = []; // Para JS: id, nombre, categoría.

foreach (get_courses() as $c) {
    if ($c->id == SITEID) {
        continue;
    }
    $coursesmenu[$c->id] = format_string($c->fullname);
    $coursesdata[] = [
        'id'         => (int)$c->id,
        'name'       => format_string($c->fullname),
        'categoryid' => (int)$c->category
    ];
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
// CSS (layout tipo dashboard BI)
// ============================

$css = "
:root {
    --mai-maroon: #8C253E;
    --mai-orange: #FF7000;
    --mai-bg-soft: #f8fafc;
    --mai-border-soft: #e5e7eb;
    --mai-text-main: #111827;
    --mai-text-muted: #6b7280;
}

#page-local-mai-participacion {
    background: radial-gradient(circle at top left, #f9fafb 0, #ffffff 55%, #f1f5f9 100%);
}

/* CONTENEDOR PRINCIPAL */
.local-mai-participation-layout {
    width: 100%;
    max-width: 1200px;
    margin: 8px auto 32px;
    padding: 8px 12px 24px;
    display: flex;
    flex-direction: column;
    gap: 16px;
    font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

/* Encabezado core */
#page-local-mai-participacion .page-header-headings h1,
#page-local-mai-participacion .page-header-headings h2 {
    color: var(--mai-maroon);
    font-weight: 700;
}

/* ALERTA SUPERIOR */
.local-mai-participation-wrapper .alert {
    border-radius: 999px;
    padding: 8px 14px;
    font-size: 0.82rem;
    border: none;
    margin-bottom: 6px;
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

/* CARD GENÉRICA */
.local-mai-card {
    position: relative;
    border-radius: 20px;
    border: 1px solid transparent;
    background:
        linear-gradient(#ffffff, #ffffff) padding-box,
        radial-gradient(circle at top left,
            rgba(140,37,62,0.10),
            rgba(255,112,0,0.03)) border-box;
    margin-bottom: 4px;
    box-shadow: 0 18px 40px rgba(15, 23, 42, 0.10);
    overflow-x: hidden;
    background-clip: padding-box, border-box;
    padding: 0;
}

.local-mai-card-header {
    padding: 10px 18px 8px;
    border-bottom: 1px solid #f3f4f6;
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: linear-gradient(to right,
        rgba(140,37,62,0.04),
        rgba(255,112,0,0.03));
}

/* Encabezado con icono + texto */
.local-mai-card-header-main {
    display: flex;
    align-items: center;
    gap: 10px;
}

.local-mai-card-header-icon {
    width: 32px;
    height: 32px;
    border-radius: 999px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(140,37,62,0.08);
    color: var(--mai-maroon);
    font-size: 1rem;
}

.local-mai-card-header-text {
    display: flex;
    flex-direction: column;
}

.local-mai-card-title {
    margin: 0;
    font-size: 1.02rem;
    font-weight: 600;
    color: var(--mai-text-main);
}

.local-mai-card-subtitle {
    margin: 2px 0 0;
    font-size: 0.8rem;
    color: var(--mai-text-muted);
}

/* Variantes de header por card */
.local-mai-card-header--kpis .local-mai-card-header-icon {
    background: rgba(37, 99, 235, 0.10);
    color: #2563eb;
}
.local-mai-card-header--filters .local-mai-card-header-icon {
    background: rgba(148, 163, 184, 0.18);
    color: var(--mai-text-main);
}
.local-mai-card-header--detalle .local-mai-card-header-icon {
    background: rgba(22, 163, 74, 0.12);
    color: #16a34a;
}
.local-mai-card-header--export .local-mai-card-header-icon {
    background: rgba(249, 115, 22, 0.14);
    color: var(--mai-orange);
}

.local-mai-card-body {
    padding: 12px 18px 16px;
}

/* FILA KPIs (4 columnas) */
.local-mai-card-kpis .local-mai-card-body {
    padding-top: 10px;
}
.local-mai-kpi-row {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
}
.local-mai-kpi-card {
    border-radius: 14px;
    padding: 10px 12px;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.local-mai-kpi-top {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.local-mai-kpi-label {
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--mai-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.local-mai-kpi-value {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--mai-text-main);
}
.local-mai-kpi-sub {
    font-size: 0.78rem;
    color: var(--mai-text-muted);
}

/* mini-grafica dentro del KPI */
.local-mai-kpi-chart {
    width: 100%;
    max-width: 180px;
    margin: 0 auto;
}

/* Colores por KPI */
.local-mai-kpi-card--total .local-mai-kpi-value { color: #111827; }
.local-mai-kpi-card--activos .local-mai-kpi-value { color: #16a34a; }
.local-mai-kpi-card--inactivos .local-mai-kpi-value { color: #f59e0b; }
.local-mai-kpi-card--nunca .local-mai-kpi-value { color: #dc2626; }

/* FILA PRINCIPAL: FILTROS (IZQ) + DETALLE (DER) */
.local-mai-main-row {
    display: flex;
    gap: 18px;
    align-items: flex-start;
}
.local-mai-main-left {
    flex: 1 1 0;
}
.local-mai-main-right {
    flex: 2 1 0;
}

/* FILTROS (card izquierda) */
.local-mai-filters-body {
    margin-top: 4px;
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
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--mai-text-muted);
    margin: 4px 0 2px 4px;
    letter-spacing: 0.01em;
}
.local-mai-filters select.custom-select {
    width: 100%;
    border-radius: 999px;
    border: 1px solid var(--mai-border-soft);
    font-size: 0.84rem;
    padding: 7px 34px 7px 12px;
    background-color: #f9fafb;
    transition: box-shadow 0.18s ease, border-color 0.18s ease, background-color 0.18s ease, transform 0.1s ease;
}
.local-mai-filters select.custom-select:hover {
    background-color: #ffffff;
}
.local-mai-filters select.custom-select:focus {
    outline: none;
    border-color: var(--mai-orange);
    background-color: #ffffff;
    box-shadow: 0 0 0 1px rgba(255, 112, 0, 0.25);
    transform: translateY(-1px);
}

/* BOTÓN APLICAR FILTROS */
.local-mai-btn-primary {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background-color:#8C253E;
    border: none;
    color: #ffffff;
    border-radius: 10px;
    padding: 9px 14px;
    font-size: 0.84rem;
    font-weight: 600;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    width: 100%;
    cursor: pointer;
}
.local-mai-btn-primary:hover,
.local-mai-btn-primary:focus {
    color: #ffffff;
    filter: brightness(1.05);
    transform: translateY(-1px);
}

/* CARD DETALLE / TABS */
.local-mai-tabs {
    display: flex;
    width: 100%;
    justify-content: space-between;
    border-bottom: 1px solid #e5e7eb;
    margin-bottom: 10px;
    padding-bottom: 2px;
}
.local-mai-tab {
    flex: 1 1 0;
    border: none;
    background: transparent;
    padding: 7px 8px;
    font-size: 0.86rem;
    font-weight: 500;
    cursor: pointer;
    text-align: center;
    border-bottom: 2px solid transparent;
    transition: all 0.15s ease;
}
.local-mai-tab--activos { color: #16a34a; }
.local-mai-tab--inactivos { color: #f59e0b; }
.local-mai-tab--nunca { color: #dc2626; }
.local-mai-tab--activos.active {
    border-bottom-color: #16a34a;
    background: rgba(22, 163, 74, 0.06);
    font-weight: 600;
}
.local-mai-tab--inactivos.active {
    border-bottom-color: #f59e0b;
    background: rgba(245, 158, 11, 0.06);
    font-weight: 600;
}
.local-mai-tab--nunca.active {
    border-bottom-color: #dc2626;
    background: rgba(220, 38, 38, 0.06);
    font-weight: 600;
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

/* TABLAS */
.local-mai-card table {
    border-collapse: collapse;
    font-size: 0.9rem;
    width: 100%;
}
.local-mai-card table th,
.local-mai-card table td {
    padding: 6px 8px;
    border-bottom: 1px solid #f3f4f6;
    box-sizing: border-box;
}
.local-mai-card table th {
    background-color: rgba(140, 37, 62, 0.03);
    font-weight: 600;
    color: var(--mai-text-muted);
}
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

/* EXPORT CARD */
.local-mai-export-help {
    font-size: 0.78rem;
    color: var(--mai-text-muted);
    margin: 0 0 8px;
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

/* Botones export centrados */
.local-mai-export-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    justify-content: center;
    margin-top: 8px;
}
.local-mai-btn-export {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    border-radius: 10px;
    padding: 7px 14px;
    font-size: 0.82rem;
    font-weight: 600;
    border: 1px solid transparent;
    cursor: pointer;
    white-space: nowrap;
}
.local-mai-btn-icon {
    font-size: 0.95rem;
    line-height: 1;
}
.local-mai-btn-excel {
    background: #16a34a;
    border-color: #15803d;
    color: #ffffff;
}
.local-mai-btn-excel:hover,
.local-mai-btn-excel:focus {
    background: #15803d;
}
.local-mai-btn-pdf {
    background: #dc2626;
    border-color: #b91c1c;
    color: #ffffff;
}
.local-mai-btn-pdf:hover,
.local-mai-btn-pdf:focus {
    background: #b91c1c;
}
.local-mai-btn-csv {
    background: #f3f4f6;
    border-color: #d1d5db;
    color: #374151;
}
.local-mai-btn-csv:hover,
.local-mai-btn-csv:focus {
    background: #e5e7eb;
}

/* MODAL LOADING */
.local-mai-loading-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.35);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}
.local-mai-loading-modal {
    background: #ffffff;
    border-radius: 18px;
    padding: 18px 26px 20px;
    box-shadow: 0 18px 45px rgba(15,23,42,0.25);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    min-width: 260px;
}
.local-mai-loading-spinner {
    width: 32px;
    height: 32px;
    border-radius: 999px;
    border: 3px solid #e5e7eb;
    border-top-color: #FF7000;
    animation: local-mai-spin 0.8s linear infinite;
}
.local-mai-loading-text {
    font-size: 0.9rem;
    font-weight: 500;
    color: #111827;
}
@keyframes local-mai-spin {
    to { transform: rotate(360deg); }
}

/* RESPONSIVE */
@media (max-width: 900px) {
    .local-mai-kpi-row {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .local-mai-main-row {
        flex-direction: column;
    }
}
    /* Botón regresar al dashboard MAI */
.local-mai-panel-back {
    display: flex;
    justify-content: flex-start; /* cambia a flex-end si lo quieres a la derecha */
    margin-bottom: 6px;
}

.local-mai-btn-back {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 999px;
    border: 1px solid var(--mai-border-soft);
    background: #ffffff;
    color: var(--mai-text-muted);
    font-size: 0.8rem;
    font-weight: 500;
    text-decoration: none;
    cursor: pointer;
    transition: border-color .15s ease, box-shadow .15s ease, transform .1s ease, color .15s ease;
}

.local-mai-btn-back:hover,
.local-mai-btn-back:focus {
    border-color: rgba(140,37,62,0.35);
    color: var(--mai-maroon);
    box-shadow: 0 8px 18px rgba(15,23,42,0.10);
    transform: translateY(-1px);
}

.local-mai-btn-back-icon {
    font-size: 0.85rem;
}
";
echo html_writer::tag('style', $css);

// ============================
// LAYOUT
// ============================

echo html_writer::start_div('local-mai-participation-layout');

echo html_writer::start_div('local-mai-participation-wrapper');

// ALERTA / loader inicial
echo html_writer::tag(
    'div',
    'Selecciona un curso y haz clic en "Aplicar filtros" para ver la participación.',
    ['id' => 'local-mai-participation-loading', 'class' => 'alert alert-info']
);

// ---- CARD KPIs ----
echo html_writer::start_div('local-mai-card local-mai-card-kpis');

// header con icono y subtítulo
echo html_writer::start_div('local-mai-card-header local-mai-card-header--kpis');
echo html_writer::start_div('local-mai-card-header-main');
echo html_writer::tag('span', '<i class="fa-solid fa-chart-pie"></i>', [
    'class' => 'local-mai-card-header-icon'
]);
echo html_writer::start_div('local-mai-card-header-text');
echo html_writer::tag('h3', 'Resumen de participación', ['class' => 'local-mai-card-title']);
echo html_writer::tag('p', 'Indicadores globales del curso seleccionado.', ['class' => 'local-mai-card-subtitle']);
echo html_writer::end_div(); // header-text
echo html_writer::end_div(); // header-main
echo html_writer::end_div(); // card-header

echo html_writer::start_div('local-mai-card-body');

echo html_writer::start_div('local-mai-kpi-row');

// Total inscritos
echo html_writer::start_div('local-mai-kpi-card local-mai-kpi-card--total');
echo html_writer::start_div('local-mai-kpi-top');
echo html_writer::tag('div', 'Inscritos', ['class' => 'local-mai-kpi-label']);
echo html_writer::tag('div', '0', [
    'class' => 'local-mai-kpi-value',
    'id'    => 'local-mai-kpi-total-value'
]);
echo html_writer::tag('div', 'Alumnos', [
    'class' => 'local-mai-kpi-sub',
    'id'    => 'local-mai-kpi-total-sub'
]);
echo html_writer::end_div(); // kpi-top
echo html_writer::tag('div', '', [
    'class' => 'local-mai-kpi-chart',
    'id'    => 'local-mai-kpi-chart-total'
]);
echo html_writer::end_div();

// Activos
echo html_writer::start_div('local-mai-kpi-card local-mai-kpi-card--activos');
echo html_writer::start_div('local-mai-kpi-top');
echo html_writer::tag('div', 'Activos', ['class' => 'local-mai-kpi-label']);
echo html_writer::tag('div', '0', [
    'class' => 'local-mai-kpi-value',
    'id'    => 'local-mai-kpi-active-value'
]);
echo html_writer::tag('div', '0% del total', [
    'class' => 'local-mai-kpi-sub',
    'id'    => 'local-mai-kpi-active-sub'
]);
echo html_writer::end_div();
echo html_writer::tag('div', '', [
    'class' => 'local-mai-kpi-chart',
    'id'    => 'local-mai-kpi-chart-active'
]);
echo html_writer::end_div();

// Inactivos
echo html_writer::start_div('local-mai-kpi-card local-mai-kpi-card--inactivos');
echo html_writer::start_div('local-mai-kpi-top');
echo html_writer::tag('div', 'Inactivos', ['class' => 'local-mai-kpi-label']);
echo html_writer::tag('div', '0', [
    'class' => 'local-mai-kpi-value',
    'id'    => 'local-mai-kpi-inactive-value'
]);
echo html_writer::tag('div', '0% del total', [
    'class' => 'local-mai-kpi-sub',
    'id'    => 'local-mai-kpi-inactive-sub'
]);
echo html_writer::end_div();
echo html_writer::tag('div', '', [
    'class' => 'local-mai-kpi-chart',
    'id'    => 'local-mai-kpi-chart-inactive'
]);
echo html_writer::end_div();

// Nunca ingresaron
echo html_writer::start_div('local-mai-kpi-card local-mai-kpi-card--nunca');
echo html_writer::start_div('local-mai-kpi-top');
echo html_writer::tag('div', 'Nunca ingresaron', ['class' => 'local-mai-kpi-label']);
echo html_writer::tag('div', '0', [
    'class' => 'local-mai-kpi-value',
    'id'    => 'local-mai-kpi-never-value'
]);
echo html_writer::tag('div', '0% del total', [
    'class' => 'local-mai-kpi-sub',
    'id'    => 'local-mai-kpi-never-sub'
]);
echo html_writer::end_div();
echo html_writer::tag('div', '', [
    'class' => 'local-mai-kpi-chart',
    'id'    => 'local-mai-kpi-chart-never'
]);
echo html_writer::end_div();

echo html_writer::end_div(); // kpi-row
echo html_writer::end_div(); // card-body
echo html_writer::end_div(); // card-kpis

// ---- FILA PRINCIPAL: filtros IZQ, detalle DER ----
echo html_writer::start_div('local-mai-main-row');

// Columna izquierda: Filtros
echo html_writer::start_div('local-mai-main-left');
echo html_writer::start_div('local-mai-card local-mai-filters');

// header filtros
echo html_writer::start_div('local-mai-card-header local-mai-card-header--filters');
echo html_writer::start_div('local-mai-card-header-main');
echo html_writer::tag('span', '<i class="fa-solid fa-filter"></i>', [
    'class' => 'local-mai-card-header-icon'
]);
echo html_writer::start_div('local-mai-card-header-text');
echo html_writer::tag('h3', 'Filtros de búsqueda', ['class' => 'local-mai-card-title']);
echo html_writer::tag('p', 'Refina el análisis por categoría, curso, cohorte, grupo y rol.', ['class' => 'local-mai-card-subtitle']);
echo html_writer::end_div(); // header-text
echo html_writer::end_div(); // header-main
echo html_writer::end_div(); // card-header

echo html_writer::start_div('local-mai-card-body');
echo html_writer::tag('p',
    'Selecciona la combinación de filtros para analizar la participación.',
    ['class' => 'local-mai-export-help']
);

echo html_writer::start_div('local-mai-filters-body');

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
echo html_writer::end_div(); // filters-body
echo html_writer::end_div(); // card-body
echo html_writer::end_div(); // card filtros
echo html_writer::end_div(); // main-left

// Columna derecha: Detalle + Configuración de exportación
echo html_writer::start_div('local-mai-main-right');

// Card Detalle por estudiante
echo html_writer::start_div('local-mai-card', ['id' => 'local-mai-participation-tables']);

// header detalle
echo html_writer::start_div('local-mai-card-header local-mai-card-header--detalle');
echo html_writer::start_div('local-mai-card-header-main');
echo html_writer::tag('span', '<i class="fa-solid fa-user-check"></i>', [
    'class' => 'local-mai-card-header-icon'
]);
echo html_writer::start_div('local-mai-card-header-text');
echo html_writer::tag('h3', 'Detalle por estudiante', ['class' => 'local-mai-card-title']);
echo html_writer::tag('p', 'Listado segmentado de estudiantes según su nivel de actividad.', ['class' => 'local-mai-card-subtitle']);
echo html_writer::end_div(); // header-text
echo html_writer::end_div(); // header-main
echo html_writer::end_div(); // card-header

echo html_writer::start_div('local-mai-card-body');

// Tabs y paneles
echo '
    <div class="local-mai-tabs">
        <button type="button" class="local-mai-tab local-mai-tab--activos active" data-tab="activos">Activos</button>
        <button type="button" class="local-mai-tab local-mai-tab--inactivos" data-tab="inactivos">Inactivos</button>
        <button type="button" class="local-mai-tab local-mai-tab--nunca" data-tab="nunca">Nunca ingresaron</button>
    </div>
    <div class="local-mai-tab-panels">
        <div id="local-mai-tab-activos" class="local-mai-tab-panel active"></div>
        <div id="local-mai-tab-inactivos" class="local-mai-tab-panel"></div>
        <div id="local-mai-tab-nunca" class="local-mai-tab-panel"></div>
    </div>
';

echo html_writer::end_div(); // card-body detalle
echo html_writer::end_div(); // card detalle

// Nueva card Configuración de exportación (debajo)
echo html_writer::start_div('local-mai-card local-mai-export-card', [
    'id'    => 'local-mai-export-card',
    'style' => 'display:none;'
]);

echo html_writer::start_div('local-mai-card-header local-mai-card-header--export');
echo html_writer::start_div('local-mai-card-header-main');
echo html_writer::tag('span', '<i class="fa-solid fa-file-export"></i>', [
    'class' => 'local-mai-card-header-icon'
]);
echo html_writer::start_div('local-mai-card-header-text');
echo html_writer::tag('h3', 'Configuración de exportación', ['class' => 'local-mai-card-title']);
echo html_writer::tag('p', 'Personaliza columnas y formato antes de descargar los resultados.', ['class' => 'local-mai-card-subtitle']);
echo html_writer::end_div(); // header-text
echo html_writer::end_div(); // header-main
echo html_writer::end_div(); // card-header

echo html_writer::start_div('local-mai-card-body');
echo html_writer::tag(
    'p',
    'Activa o desactiva las columnas que se incluirán en los archivos Excel, PDF o CSV.',
    ['class' => 'local-mai-export-help']
);

echo html_writer::start_div('local-mai-export-row');

// Columnas export.
echo html_writer::start_div('local-mai-export-columns');
echo html_writer::tag('span', 'Columnas:', ['class' => 'local-mai-export-label']);

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

// Botones export
echo html_writer::start_div('local-mai-export-buttons');

echo '
<button type="button" id="local-mai-export-excel" class="local-mai-btn-export local-mai-btn-excel">
    <span class="local-mai-btn-icon"><i class="fa-solid fa-file-excel"></i></span>
    <span>Excel</span>
</button>';

echo '
<button type="button" id="local-mai-export-pdf" class="local-mai-btn-export local-mai-btn-pdf">
    <span class="local-mai-btn-icon"><i class="fa-solid fa-file-pdf"></i></span>
    <span>PDF</span>
</button>';

echo '
<button type="button" id="local-mai-export-csv" class="local-mai-btn-export local-mai-btn-csv">
    <span class="local-mai-btn-icon"><i class="fa-solid fa-file-csv"></i></span>
    <span>CSV</span>
</button>';

echo html_writer::end_div(); // export-buttons

echo html_writer::end_div(); // export-row
echo html_writer::end_div(); // card-body export
echo html_writer::end_div(); // card export

echo html_writer::end_div(); // main-right

echo html_writer::end_div(); // main-row

echo html_writer::end_div(); // participation-wrapper
echo html_writer::end_div(); // participation-layout

// ------- MODAL LOADING (overlay) -------
echo '
<div id="local-mai-loading-modal" class="local-mai-loading-backdrop">
    <div class="local-mai-loading-modal">
        <div class="local-mai-loading-spinner"></div>
        <div class="local-mai-loading-text">Cargando datos de participación...</div>
    </div>
</div>
';

// ============================
// JS inline (sin require, usando jQuery global de Moodle)
// ============================

$ajaxurl    = (new moodle_url('/local/mai/participacion/ajax.php'))->out(false);
$exporturl  = (new moodle_url('/local/mai/participacion/export.php'))->out(false);
$sesskey    = sesskey();

?>
<script>
var MAI_COURSES = <?php echo json_encode($coursesdata); ?> || [];
var MAI_INITIAL_COURSEID = '<?php echo (int)$courseid; ?>';
var MAI_INITIAL_CATEGORYID = '<?php echo (int)$categoryid; ?>';

document.addEventListener('DOMContentLoaded', function() {
    var $ = window.jQuery || null;
    if (!$) {
        console.error('jQuery de Moodle no está disponible.');
        return;
    }

    var ajaxUrl        = '<?php echo $ajaxurl; ?>';
    var exportUrlBase  = '<?php echo $exporturl; ?>';
    var sesskey        = '<?php echo $sesskey; ?>';

    var form       = document.getElementById('local-mai-filters-form');
    var loadingEl  = document.getElementById('local-mai-participation-loading');
    var exportCard = document.getElementById('local-mai-export-card');

    var tabActivos   = document.getElementById('local-mai-tab-activos');
    var tabInactivos = document.getElementById('local-mai-tab-inactivos');
    var tabNunca     = document.getElementById('local-mai-tab-nunca');

    var exportExcelBtn = document.getElementById('local-mai-export-excel');
    var exportPdfBtn   = document.getElementById('local-mai-export-pdf');
    var exportCsvBtn   = document.getElementById('local-mai-export-csv');

    var loadingModal = document.getElementById('local-mai-loading-modal');

    // ---------- SELECT DEPENDIENTE CATEGORÍA -> CURSO ----------
    function populateCourses(categoryId, selectedCourseId) {
        var courseSelect = document.getElementById('id_courseid');
        if (!courseSelect) {
            return;
        }

        categoryId = categoryId || '0';
        selectedCourseId = (typeof selectedCourseId === 'undefined' || selectedCourseId === null)
            ? courseSelect.value || '0'
            : selectedCourseId;

        // Limpiar opciones actuales
        while (courseSelect.firstChild) {
            courseSelect.removeChild(courseSelect.firstChild);
        }

        // Opción default
        var optAll = document.createElement('option');
        optAll.value = '0';
        optAll.textContent = 'Todos los cursos';
        courseSelect.appendChild(optAll);

        // Agregar cursos según categoría
        MAI_COURSES.forEach(function(c) {
            if (categoryId === '0' || String(c.categoryid) === String(categoryId)) {
                var o = document.createElement('option');
                o.value = String(c.id);
                o.textContent = c.name;
                if (String(c.id) === String(selectedCourseId)) {
                    o.selected = true;
                }
                courseSelect.appendChild(o);
            }
        });
    }

    var categorySelect = document.getElementById('id_categoryid');
    if (categorySelect) {
        var initialCat = MAI_INITIAL_CATEGORYID || categorySelect.value || '0';
        var initialCourse = MAI_INITIAL_COURSEID || '0';
        populateCourses(initialCat, initialCourse);

        categorySelect.addEventListener('change', function() {
            var selectedCat = this.value || '0';
            populateCourses(selectedCat, '0');
        });
    }

    // ---------- MODAL LOADING ----------
    function showLoadingModal() {
        if (loadingModal) {
            loadingModal.style.display = 'flex';
        }
    }
    function hideLoadingModal() {
        if (loadingModal) {
            loadingModal.style.display = 'none';
        }
    }

    function toggleExportCard(show) {
        if (!exportCard) { return; }
        exportCard.style.display = show ? 'block' : 'none';
    }

    // ---------- KPIs ----------
    function renderKpis(counts, total) {
        counts = counts || {};

        var active   = counts.active   || 0;
        var inactive = counts.inactive || 0;
        var never    = counts.never    || 0;

        function pct(n) {
            return total > 0 ? Math.round((n * 100) / total) : 0;
        }

        var totalEl = document.getElementById('local-mai-kpi-total-value');
        if (totalEl) { totalEl.textContent = total; }

        var actVal = document.getElementById('local-mai-kpi-active-value');
        var actSub = document.getElementById('local-mai-kpi-active-sub');
        if (actVal) { actVal.textContent = active + ' (' + pct(active) + '%)'; }
        if (actSub) { actSub.textContent = 'Del total de inscritos'; }

        var inaVal = document.getElementById('local-mai-kpi-inactive-value');
        var inaSub = document.getElementById('local-mai-kpi-inactive-sub');
        if (inaVal) { inaVal.textContent = inactive + ' (' + pct(inactive) + '%)'; }
        if (inaSub) { inaSub.textContent = 'Han ingresado pero no actúan'; }

        var nevVal = document.getElementById('local-mai-kpi-never-value');
        var nevSub = document.getElementById('local-mai-kpi-never-sub');
        if (nevVal) { nevVal.textContent = never + ' (' + pct(never) + '%)'; }
        if (nevSub) { nevSub.textContent = 'Matriculados sin acceso'; }
    }

    // ---------- Gauges dentro de los KPIs ----------
    var kpiChartsIds = {
        total:   'local-mai-kpi-chart-total',
        active:  'local-mai-kpi-chart-active',
        inactive:'local-mai-kpi-chart-inactive',
        never:   'local-mai-kpi-chart-never'
    };

    function clearGauges() {
        Object.keys(kpiChartsIds).forEach(function(key) {
            var el = document.getElementById(kpiChartsIds[key]);
            if (el) { el.innerHTML = ''; }
        });
    }

    function renderGauge(elementId, percent, color, label) {
        var el = document.getElementById(elementId);
        if (!el || typeof ApexCharts === 'undefined') {
            return;
        }
        el.innerHTML = '';

        var options = {
            chart: {
                type: 'radialBar',
                height: 140,
                sparkline: { enabled: true }
            },
            series: [percent],
            labels: [label],
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
                            fontSize: '12px',
                            offsetY: 26
                        },
                        value: {
                            fontSize: '16px',
                            formatter: function(val) {
                                return Math.round(val) + '%';
                            },
                            offsetY: -10
                        }
                    }
                }
            }
        };
        var chart = new ApexCharts(el, options);
        chart.render();
    }

    function renderGauges(counts, total) {
        counts = counts || {};
        var active   = counts.active   || 0;
        var inactive = counts.inactive || 0;
        var never    = counts.never    || 0;

        function pct(n) {
            return total > 0 ? Math.round((n * 100) / total) : 0;
        }

        clearGauges();

        renderGauge(kpiChartsIds.total, total > 0 ? 100 : 0, '#0f766e', 'Total');
        renderGauge(kpiChartsIds.active, pct(active), '#16a34a', 'Activos');
        renderGauge(kpiChartsIds.inactive, pct(inactive), '#f59e0b', 'Inactivos');
        renderGauge(kpiChartsIds.never, pct(never), '#dc2626', 'Nunca');
    }

    // --- Ajustar columnas de DataTables al cambiar de tab ---
    function adjustTableForTab(tab) {
        if (!$.fn.DataTable) {
            return;
        }
        if (tab === 'activos' && $('#local-mai-table-activos').length && $.fn.DataTable.isDataTable('#local-mai-table-activos')) {
            $('#local-mai-table-activos').DataTable().columns.adjust();
        } else if (tab === 'inactivos' && $('#local-mai-table-inactivos').length && $.fn.DataTable.isDataTable('#local-mai-table-inactivos')) {
            $('#local-mai-table-inactivos').DataTable().columns.adjust();
        } else if (tab === 'nunca' && $('#local-mai-table-nunca').length && $.fn.DataTable.isDataTable('#local-mai-table-nunca')) {
            $('#local-mai-table-nunca').DataTable().columns.adjust();
        }
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

            setTimeout(function() {
                adjustTableForTab(tab);
            }, 10);
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

    // --- DataTables helper ---
    function initDataTable(selector) {
        if (!$.fn || !$.fn.DataTable) {
            console.error('DataTables no está disponible para', selector);
            return;
        }
        var $table = $(selector);
        if (!$table.length) {
            return;
        }
        if ($.fn.DataTable.isDataTable($table)) {
            $table.DataTable().destroy();
        }
        $table.DataTable({
            pageLength: 5,
            lengthChange: false,
            dom: 'ftip',
            ordering: true,
            searching: true,
            info: true,
            scrollX: true,
            autoWidth: false,
            language: {
                decimal: ',',
                thousands: '.',
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

        if (!activos.length && !inactivos.length && !nunca.length) {
            tabActivos.innerHTML = '<div class=\"alert alert-warning\">No se encontraron estudiantes con los filtros seleccionados.</div>';
            tabInactivos.innerHTML = '';
            tabNunca.innerHTML = '';
            return;
        }

        setTimeout(function() {
            initDataTable('#local-mai-table-activos');
            initDataTable('#local-mai-table-inactivos');
            initDataTable('#local-mai-table-nunca');
            adjustTableForTab('activos');
        }, 10);
    }

    // --- Carga participación ---
    function loadParticipation(filters) {
        if (!filters.courseid || filters.courseid === '0') {
            hideLoadingModal();
            loadingEl.className = 'alert alert-warning';
            loadingEl.textContent = 'Debes seleccionar un curso antes de aplicar filtros.';
            loadingEl.style.display = 'block';
            tabActivos.innerHTML = '';
            tabInactivos.innerHTML = '';
            tabNunca.innerHTML = '';
            toggleExportCard(false);
            renderKpis({}, 0);
            clearGauges();
            return;
        }

        showLoadingModal();

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
            hideLoadingModal();
            loadingEl.style.display = 'none';
            renderKpis(data.counts || {}, data.total || 0);
            renderGauges(data.counts || {}, data.total || 0);
            renderTables(data);
            toggleExportCard(true);
        }).catch(function(err) {
            console.error(err);
            hideLoadingModal();
            loadingEl.className = 'alert alert-danger';
            loadingEl.textContent = 'Ocurrió un error al cargar la información de participación.';
            loadingEl.style.display = 'block';
            tabActivos.innerHTML = '';
            tabInactivos.innerHTML = '';
            tabNunca.innerHTML = '';
            toggleExportCard(false);
            renderKpis({}, 0);
            clearGauges();
        });
    }

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
