<?php
// local/mai/estructura.php
/**
 * Vistas clasificadas por estructura académica (frontend con AJAX).
 *
 * @package   local_mai
 */

require(__DIR__ . '/../../config.php');

require_login();

global $DB;

$systemcontext = context_system::instance();
require_capability('local/mai:viewreport', $systemcontext);

$pagetitle = 'Estructura académica';

$PAGE->set_url(new moodle_url('/local/mai/estructura.php'));
$PAGE->set_context($systemcontext);
$PAGE->set_pagelayout('report');
$PAGE->set_title($pagetitle);

// jQuery
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
// Font Awesome (íconos exportación / headers)
echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />';

// DataTables (tabla limpia sin botones de exportación)
echo '<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css" />';
echo '<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>';

// --- Carga segura de ApexCharts (sin AMD/RequireJS) ---
echo '<script> window.__apex_define = window.define; window.define = undefined; </script>';
echo '<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>';
echo '<script> window.define = window.__apex_define; </script>';

// ======================
// Datos para filtros
// ======================

// Programas = categorías padre.
$programcats = $DB->get_records('course_categories', ['parent' => 0], 'sortorder', 'id, name, parent');
$programoptions = [0 => 'Todos los programas'];
foreach ($programcats as $pcat) {
    $programoptions[$pcat->id] = format_string($pcat->name);
}

// Cuatrimestres (se cargan por AJAX, placeholder).
$termoptions = [0 => 'Todos los cuatrimestres'];

// Docentes (también por AJAX, placeholder).
$teacheroptions = [0 => 'Todos los docentes'];

// Grupos (también por AJAX, placeholder).
$groupoptions = [0 => 'Todos los grupos'];

// ======================
// CSS
// ======================
$css = "
:root {
    --mai-maroon: #8C253E;
    --mai-orange: #FF7000;
    --mai-bg-soft: #f8fafc;
    --mai-border-soft: #e5e7eb;
    --mai-text-main: #111827;
    --mai-text-muted: #6b7280;
}

#page-local-mai-estructura {
    background: radial-gradient(circle at top left, #f9fafb 0, #ffffff 55%, #f1f5f9 100%);
}

/* Encabezado core */
#page-local-mai-estructura .page-header-headings h1,
#page-local-mai-estructura .page-header-headings h2 {
    color: var(--mai-maroon);
    font-weight: 700;
}

/* LAYOUT GENERAL */
.local-mai-estructura-layout {
    width: 100%;
    max-width: 1200px;
    margin: 8px auto 32px;
    padding: 8px 12px 24px;
    display: flex;
    flex-direction: column;
    gap: 16px;
    font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.local-mai-page-subtitle {
    color: var(--mai-text-muted);
    margin: 2px 0 14px;
    font-size: 0.9rem;
}

/* CARD GENÉRICA */
.local-mai-card {
    position: relative;
    border-radius: 20px;
    border: 1px solid transparent;
    background: linear-gradient(#ffffff, #ffffff) padding-box,
                radial-gradient(circle at top left, rgba(140,37,62,0.10), rgba(255,112,0,0.03)) border-box;
    margin-bottom: 4px;
    box-shadow: 0 18px 40px rgba(15, 23, 42, 0.10);
    overflow-x: hidden;
    background-clip: padding-box, border-box;
    padding: 0;
    transition: transform 0.16s ease, box-shadow 0.16s ease;
}

.local-mai-card:hover {
    transform: translateY(-1px);
    box-shadow: 0 22px 45px rgba(15,23,42,0.14);
}

/* HEADER DE CARD CON ICONO */
.local-mai-card-header {
    padding: 10px 18px 8px;
    border-bottom: 1px solid #f3f4f6;
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: linear-gradient(to right, rgba(140,37,62,0.04), rgba(255,112,0,0.03));
}

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
    background: rgba(140,37,62,0.10);
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

/* Variantes header */
.local-mai-card-header--general .local-mai-card-header-icon {
    background: rgba(59,130,246,0.12);
    color: #2563eb;
}
.local-mai-card-header--programs .local-mai-card-header-icon {
    background: rgba(34,197,94,0.12);
    color: #16a34a;
}
.local-mai-card-header--term .local-mai-card-header-icon {
    background: rgba(249,115,22,0.14);
    color: var(--mai-orange);
}
.local-mai-card-header--export .local-mai-card-header-icon {
    background: rgba(148,163,184,0.16);
    color: var(--mai-text-main);
}

.local-mai-card-body {
    padding: 12px 18px 16px;
}

/* TEXTOS DE AYUDA */
.local-mai-help-text {
    font-size: 0.8rem;
    color: var(--mai-text-muted);
    margin-bottom: 8px;
}

/* FILTROS (programa / cuatrimestre / docente / grupo) */
#local-mai-estructura-filters {
    display: flex;
    flex-wrap: wrap;
    gap: 10px 16px;
    align-items: flex-end;
}

.local-mai-filters .form-group,
.local-mai-term-filters .form-group {
    display: flex;
    flex-direction: column;
    min-width: 180px;
}

.local-mai-filters label,
.local-mai-term-filters label {
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--mai-text-muted);
    margin: 0 0 2px 2px;
    letter-spacing: 0.01em;
}

.local-mai-filters select.custom-select,
.local-mai-term-filters select.custom-select {
    border-radius: 999px;
    border: 1px solid var(--mai-border-soft);
    font-size: 0.84rem;
    padding: 7px 34px 7px 12px;
    background-color: #f9fafb;
    transition: box-shadow 0.18s ease, border-color 0.18s ease, background-color 0.18s ease, transform 0.1s ease;
}

.local-mai-filters select.custom-select:hover,
.local-mai-term-filters select.custom-select:hover {
    background-color: #ffffff;
}

.local-mai-filters select.custom-select:focus,
.local-mai-term-filters select.custom-select:focus {
    outline: none;
    border-color: var(--mai-orange);
    background-color: #ffffff;
    box-shadow: 0 0 0 1px rgba(255, 112, 0, 0.25);
    transform: translateY(-1px);
}

/* BOTÓN APLICAR FILTROS (por si algún día se usa) */
.local-mai-btn-primary {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background-color:#8C253E;
    border: none;
    color: #ffffff;
    border-radius: 10px;
    padding: 9px 18px;
    font-size: 0.84rem;
    font-weight: 600;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    cursor: pointer;
    white-space: nowrap;
}

.local-mai-btn-primary:hover,
.local-mai-btn-primary:focus {
    color: #ffffff;
    filter: brightness(1.05);
    transform: translateY(-1px);
}

/* CARD EXPORT */
.local-mai-export-help {
    font-size: 0.78rem;
    color: var(--mai-text-muted);
    margin: 0 0 8px;
}

.local-mai-export-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    justify-content: flex-start;
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

/* Excel: verde */
.local-mai-btn-excel {
    background: #16a34a;
    border-color: #15803d;
    color: #ffffff;
}

.local-mai-btn-excel:hover,
.local-mai-btn-excel:focus {
    background: #15803d;
}

/* PDF: rojo */
.local-mai-btn-pdf {
    background: #dc2626;
    border-color: #b91c1c;
    color: #ffffff;
}

.local-mai-btn-pdf:hover,
.local-mai-btn-pdf:focus {
    background: #b91c1c;
}

/* CSV: gris */
.local-mai-btn-csv {
    background: #f3f4f6;
    border-color: #d1d5db;
    color: #374151;
}

.local-mai-btn-csv:hover,
.local-mai-btn-csv:focus {
    background: #e5e7eb;
}

/* VISTA GENERAL: chart + tarjetas */
.local-mai-general-row {
    display: flex;
    flex-wrap: wrap;
    gap: 18px;
    align-items: stretch;
}

.local-mai-general-chart {
    flex: 2 1 260px;
}

.local-mai-general-stats {
    flex: 1 1 220px;
    display: grid;
    grid-template-columns: 1fr;
    grid-auto-rows: minmax(0, 1fr);
    gap: 10px;
}

.local-mai-stat-box {
    border-radius: 14px;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    padding: 8px 10px;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.local-mai-stat-label {
    font-size: 0.78rem;
    color: var(--mai-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.local-mai-stat-value {
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--mai-text-main);
}

/* Versión compacta para cuatrimestres */
.local-mai-term-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 8px;
    margin-top: 6px;
}

.local-mai-stat-box-sm {
    padding: 6px 8px;
}

.local-mai-stat-box-sm .local-mai-stat-label {
    font-size: 0.72rem;
}

.local-mai-stat-box-sm .local-mai-stat-value {
    font-size: 0.9rem;
}

/* Tablas */
.local-mai-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
}

.local-mai-table th,
.local-mai-table td {
    padding: 6px 8px;
    border-bottom: 1px solid #f1f5f9;
}

.local-mai-table th {
    background-color: rgba(140, 37, 62, 0.03);
    font-weight: 600;
    color: var(--mai-text-muted);
}

.local-mai-program-highlight {
    background-color: #e0f2fe;
}

/* Contenedor de gráficas */
.local-mai-chart-container {
    min-height: 260px;
}

/* Inline loading + spinner */
.local-mai-inline-loading {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 0.86rem;
    color: var(--mai-text-muted);
}

.local-mai-loading-spinner {
    width: 28px;
    height: 28px;
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
    to {
        transform: rotate(360deg);
    }
}

/* CONTEXTO VISTA POR CUATRIMESTRE */
.local-mai-term-context {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 4px 16px;
    margin-bottom: 10px;
    font-size: 0.85rem;
}

.local-mai-term-label {
    font-weight: 600;
    color: var(--mai-text-muted);
    margin-right: 4px;
}

/* FILTROS DOCENTE / GRUPO / CUATRIMESTRE EN TAB CUATRIMESTRE */
.local-mai-term-filters {
    display: flex;
    flex-wrap: wrap;
    gap: 10px 16px;
    align-items: flex-end;
    margin-bottom: 10px;
}

/* LAYOUT PROGRAMAS y CUATRIMESTRES */
.local-mai-program-row,
.local-mai-term-row {
    display: flex;
    flex-wrap: wrap;
    gap: 18px;
    align-items: flex-start;
}

.local-mai-term-table-col,
.local-mai-term-chart-col {
    flex: 1 1 0;
    min-width: 320px;
}

/* TABS MAI */
.local-mai-tabs-wrapper {
    margin-top: 10px;
}

.local-mai-tabs-nav {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    border-bottom: 1px solid #e5e7eb;
    margin-bottom: 10px;
    overflow-x: auto;
}

.local-mai-tab-btn {
    border: none;
    background: transparent;
    padding: 8px 14px;
    border-radius: 999px 999px 0 0;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--mai-text-muted);
    cursor: pointer;
    position: relative;
}

.local-mai-tab-btn.active {
    background: #ffffff;
    color: var(--mai-maroon);
    box-shadow: 0 -1px 0 #ffffff, 0 6px 16px rgba(15,23,42,0.10);
}

/* Solo tab activo visible */
.local-mai-tab-pane {
    display: none;
}
.local-mai-tab-pane.active {
    display: block;
}

/* RESPONSIVE */
@media (max-width: 900px) {
    #local-mai-estructura-filters {
        flex-direction: column;
        align-items: stretch;
    }

    .local-mai-filters .form-group {
        min-width: 100%;
    }

    .local-mai-general-row {
        flex-direction: column;
    }

    .local-mai-program-row,
    .local-mai-term-row {
        flex-direction: column;
    }
}

/* Hover filas tabla */
.local-mai-table tbody tr:hover {
    background-color: #f9fafb;
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

// ======================
// Layout principal
// ======================

echo html_writer::start_div('local-mai-estructura-layout');

// Subtítulo
echo html_writer::tag(
    'div',
    'Usa las pestañas para revisar: vista general, resumen por programa académico y detalle por cuatrimestre con filtros por docente y grupo.',
    ['class' => 'local-mai-page-subtitle']
);

// --------- TABS WRAPPER ----------
echo '<div class="local-mai-tabs-wrapper">';

// NAV TABS
echo '<div class="local-mai-tabs-nav">';
echo '<button type="button" class="local-mai-tab-btn active" data-tab="general">Vista general de la plataforma</button>';
echo '<button type="button" class="local-mai-tab-btn" data-tab="programas">Vista por programa académico</button>';
echo '<button type="button" class="local-mai-tab-btn" data-tab="term">Vista por cuatrimestre</button>';
echo '</div>';

// CONTENT TABS
echo '<div class="local-mai-tabs-content">';

// =====================
// TAB: VISTA GENERAL
// =====================
echo '<div class="local-mai-tab-pane active" data-tabpane="general">';

echo html_writer::start_div('local-mai-card', ['id' => 'local-mai-estructura-general']);

// header con icono
echo html_writer::start_div('local-mai-card-header local-mai-card-header--general');
echo html_writer::start_div('local-mai-card-header-main');
echo html_writer::tag('span', '<i class="fa-solid fa-globe"></i>', [
    'class' => 'local-mai-card-header-icon'
]);
echo html_writer::start_div('local-mai-card-header-text');
echo html_writer::tag('div', 'Vista general de la plataforma', ['class' => 'local-mai-card-title']);
echo html_writer::tag(
    'div',
    'Resumen global de cursos y usuarios en toda la plataforma.',
    ['class' => 'local-mai-card-subtitle']
);
echo html_writer::end_div(); // header-text
echo html_writer::end_div(); // header-main
echo html_writer::end_div(); // card-header

echo html_writer::start_div('local-mai-card-body');
echo html_writer::start_div('local-mai-general-row');

// Chart donut global
echo html_writer::tag('div', '', [
    'id' => 'local-mai-estructura-general-chart',
    'class' => 'local-mai-chart-container local-mai-general-chart'
]);

// Stats a la derecha
echo html_writer::tag(
    'div',
    '<div class="local-mai-help-text">Este resumen considera toda la plataforma. Los filtros de programa y cuatrimestre solo afectan a las otras pestañas.</div>',
    [
        'id'    => 'local-mai-estructura-general-body',
        'class' => 'local-mai-general-stats'
    ]
);

echo html_writer::end_div(); // general-row
echo html_writer::end_div(); // card-body
echo html_writer::end_div(); // card general

echo '</div>'; // tab-pane general

// =====================
// TAB: VISTA POR PROGRAMA
// =====================
echo '<div class="local-mai-tab-pane" data-tabpane="programas">';

echo html_writer::start_div('local-mai-card', ['id' => 'local-mai-estructura-programas']);

// header con icono
echo html_writer::start_div('local-mai-card-header local-mai-card-header--programs');
echo html_writer::start_div('local-mai-card-header-main');
echo html_writer::tag('span', '<i class="fa-solid fa-diagram-project"></i>', [
    'class' => 'local-mai-card-header-icon'
]);
echo html_writer::start_div('local-mai-card-header-text');
echo html_writer::tag('div', 'Vista por programa académico', ['class' => 'local-mai-card-title']);
echo html_writer::tag(
    'div',
    'Total de cursos, usuarios y retención por cada programa académico.',
    ['class' => 'local-mai-card-subtitle']
);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div(); // card-header

echo html_writer::start_div('local-mai-card-body');

// FILTRO SOLO DE PROGRAMA
echo html_writer::start_div('local-mai-filters');

echo html_writer::start_tag('form', [
    'method' => 'get',
    'id'     => 'local-mai-estructura-filters'
]);

// Programa académico
echo html_writer::start_div('form-group');
echo html_writer::label('Selecciona programa académico', 'id_programid');
echo html_writer::select(
    $programoptions,
    'programid',
    0,
    null,
    [
        'id'    => 'id_programid',
        'class' => 'custom-select'
    ]
);
echo html_writer::end_div();

echo html_writer::end_tag('form');
echo html_writer::end_div(); // .local-mai-filters

// TABLA y debajo GRÁFICA
echo '<div id="local-mai-estructura-programas-table"></div>';

echo html_writer::tag('div', '', [
    'id'    => 'local-mai-estructura-programas-chart',
    'class' => 'local-mai-chart-container',
    'style' => 'margin-top:14px;'
]);

echo html_writer::end_div(); // card-body
echo html_writer::end_div(); // card programas

echo '</div>'; // tab-pane programas

// =====================
// TAB: VISTA POR CUATRIMESTRE
// =====================
echo '<div class="local-mai-tab-pane" data-tabpane="term">';

echo html_writer::start_div('local-mai-card', ['id' => 'local-mai-estructura-term']);

// header con icono
echo html_writer::start_div('local-mai-card-header local-mai-card-header--term');
echo html_writer::start_div('local-mai-card-header-main');
echo html_writer::tag('span', '<i class="fa-solid fa-layer-group"></i>', [
    'class' => 'local-mai-card-header-icon'
]);
echo html_writer::start_div('local-mai-card-header-text');
echo html_writer::tag('div', 'Vista por cuatrimestre (detalle por curso)', ['class' => 'local-mai-card-title']);
echo html_writer::tag(
    'div',
    'Desglosa usuarios por curso dentro del programa y cuatrimestre seleccionados. Puedes filtrar por docente o grupo.',
    ['class' => 'local-mai-card-subtitle']
);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div(); // card-header

echo html_writer::start_div('local-mai-card-body');

// Filtros: PROGRAMA + CUATRIMESTRE + DOCENTE + GRUPO
echo html_writer::start_div('local-mai-term-filters');

// Programa académico (independiente para esta pestaña)
echo html_writer::start_div('form-group');
echo html_writer::label('Programa académico', 'id_programid_term');
echo html_writer::select(
    $programoptions,
    'programid_term',
    0,
    null,
    [
        'id'    => 'id_programid_term',
        'class' => 'custom-select'
    ]
);
echo html_writer::end_div();

// Cuatrimestre
echo html_writer::start_div('form-group');
echo html_writer::label('Cuatrimestre', 'id_termid');
echo html_writer::select(
    $termoptions,
    'termid',
    0,
    null,
    [
        'id'    => 'id_termid',
        'class' => 'custom-select'
    ]
);
echo html_writer::end_div();

// Docente
echo html_writer::start_div('form-group');
echo html_writer::label('Docente', 'id_teacherid');
echo html_writer::select(
    $teacheroptions,
    'teacherid',
    0,
    null,
    [
        'id'    => 'id_teacherid',
        'class' => 'custom-select'
    ]
);
echo html_writer::end_div();

// Grupo
echo html_writer::start_div('form-group');
echo html_writer::label('Grupo', 'id_groupid');
echo html_writer::select(
    $groupoptions,
    'groupid',
    0,
    null,
    [
        'id'    => 'id_groupid',
        'class' => 'custom-select'
    ]
);
echo html_writer::end_div();

echo html_writer::end_div(); // local-mai-term-filters

// Resumen de contexto y KPIs del cuatrimestre
echo html_writer::tag(
    'div',
    '<span class="local-mai-help-text">Selecciona un programa académico para cargar los cuatrimestres disponibles y después elige un cuatrimestre para ver el detalle por curso.</span>',
    ['id' => 'local-mai-estructura-term-summary']
);

// Tabla y gráfica
echo '<div id="local-mai-estructura-term-table"></div>';

echo html_writer::tag('div', '', [
    'id'    => 'local-mai-estructura-term-chart',
    'class' => 'local-mai-chart-container',
    'style' => 'margin-top:14px;'
]);

echo html_writer::end_div(); // card-body
echo html_writer::end_div(); // card term

echo '</div>'; // tab-pane term

echo '</div>'; // .local-mai-tabs-content

// --------- CARD EXPORT ----------
echo html_writer::start_div('local-mai-card local-mai-export-card', ['id' => 'local-mai-estructura-export-card']);
echo html_writer::start_div('local-mai-card-header local-mai-card-header--export');
echo html_writer::start_div('local-mai-card-header-main');
echo html_writer::tag('span', '<i class="fa-solid fa-arrow-up-right-from-square"></i>', [
    'class' => 'local-mai-card-header-icon'
]);
echo html_writer::start_div('local-mai-card-header-text');
echo html_writer::tag('div', 'Exportar vista actual', ['class' => 'local-mai-card-title']);
echo html_writer::tag(
    'div',
    'Descarga la información en Excel, CSV o PDF respetando los filtros de la pestaña activa.',
    ['class' => 'local-mai-card-subtitle']
);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div(); // card-header

echo html_writer::start_div('local-mai-card-body');
echo html_writer::tag(
    'p',
    'La exportación respeta el programa/cuatrimestre según la pestaña seleccionada.',
    ['class' => 'local-mai-export-help']
);

echo html_writer::start_div('local-mai-export-buttons');

// Excel
echo '
    <button type="button" id="local-mai-estructura-export-excel" class="local-mai-btn-export local-mai-btn-excel">
        <i class="local-mai-btn-icon fa-solid fa-file-excel" aria-hidden="true"></i>
        <span>Excel</span>
    </button>';

// CSV
echo '
    <button type="button" id="local-mai-estructura-export-csv" class="local-mai-btn-export local-mai-btn-csv">
        <i class="local-mai-btn-icon fa-solid fa-file-lines" aria-hidden="true"></i>
        <span>CSV</span>
    </button>';

// PDF
echo '
    <button type="button" id="local-mai-estructura-export-pdf" class="local-mai-btn-export local-mai-btn-pdf">
        <i class="local-mai-btn-icon fa-solid fa-file-pdf" aria-hidden="true"></i>
        <span>PDF</span>
    </button>';

echo html_writer::end_div(); // export-buttons
echo html_writer::end_div(); // card-body
echo html_writer::end_div(); // card export

echo '</div>'; // .local-mai-tabs-wrapper
echo html_writer::end_div(); // layout

// ======================
// JS: AJAX + ApexCharts + export + tabs + DataTables
// ======================
$ajaxurl   = (new moodle_url('/local/mai/estructura/ajax.php'))->out(false);
$exporturl = (new moodle_url('/local/mai/estructura/export.php'))->out(false);
$sesskey   = sesskey();

?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var ajaxUrl       = '<?php echo $ajaxurl; ?>';
    var exportUrlBase = '<?php echo $exporturl; ?>';
    var sesskey       = '<?php echo $sesskey; ?>';

    var $ = window.jQuery || window.$ || null;

    var form           = document.getElementById('local-mai-estructura-filters');
    var programSel     = document.getElementById('id_programid');       // Vista por programa
    var programSelTerm = document.getElementById('id_programid_term'); // Vista por cuatrimestre
    var termSel        = document.getElementById('id_termid');
    var teacherSel     = document.getElementById('id_teacherid');
    var groupSel       = document.getElementById('id_groupid');

    var generalBody    = document.getElementById('local-mai-estructura-general-body');
    var generalChart   = document.getElementById('local-mai-estructura-general-chart');

    var progTableEl    = document.getElementById('local-mai-estructura-programas-table');
    var progChartEl    = document.getElementById('local-mai-estructura-programas-chart');

    var termSummaryEl  = document.getElementById('local-mai-estructura-term-summary');
    var termChartEl    = document.getElementById('local-mai-estructura-term-chart');
    var termTableEl    = document.getElementById('local-mai-estructura-term-table');

    var exportExcelBtn = document.getElementById('local-mai-estructura-export-excel');
    var exportCsvBtn   = document.getElementById('local-mai-estructura-export-csv');
    var exportPdfBtn   = document.getElementById('local-mai-estructura-export-pdf');
    var exportCard     = document.getElementById('local-mai-estructura-export-card');

    var globalChart    = null;
    var progChart      = null;
    var termChart      = null;

    var programDataTable = null;
    var termDataTable    = null;

    // Cache de datos ya cargados
    var cachedGlobalData       = null;
    var cachedProgramStats     = null;
    var cachedFiltersByProgram = {}; // para la pestaña de cuatrimestre

    // Texto en español para DataTables
    var dtLanguageEs = {
        decimal: '',
        thousands: ',',
        search: 'Buscar:',
        info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
        infoEmpty: 'Mostrando 0 a 0 de 0 registros',
        infoFiltered: '(filtrado de _MAX_ registros en total)',
        lengthMenu: 'Mostrar _MENU_ registros',
        loadingRecords: 'Cargando...',
        processing: 'Procesando...',
        zeroRecords: 'No se encontraron resultados',
        emptyTable: 'No hay datos disponibles en la tabla',
        paginate: {
            first: 'Primero',
            previous: 'Anterior',
            next: 'Siguiente',
            last: 'Último'
        },
        aria: {
            sortAscending: ': activar para ordenar la columna de manera ascendente',
            sortDescending: ': activar para ordenar la columna de manera descendente'
        }
    };

    // ---------- Tabs MAI ----------
    var tabButtons = document.querySelectorAll('.local-mai-tab-btn');
    var tabPanes   = document.querySelectorAll('.local-mai-tab-pane');

    function getActiveTabKey() {
        var activeTabBtn = document.querySelector('.local-mai-tab-btn.active');
        return activeTabBtn ? activeTabBtn.getAttribute('data-tab') : 'general';
    }

    function updateExportCardVisibility() {
        if (!exportCard) {
            return;
        }
        var tabindex = getActiveTabKey();
        if (tabindex === 'general') {
            exportCard.style.display = 'none';
        } else {
            exportCard.style.display = '';
        }
    }

    tabButtons.forEach(function(btn) {
        btn.addEventListener('click', function() {
            var target = btn.getAttribute('data-tab');

            tabButtons.forEach(function(b) {
                b.classList.remove('active');
            });

            tabPanes.forEach(function(p) {
                if (p.getAttribute('data-tabpane') === target) {
                    p.classList.add('active');
                } else {
                    p.classList.remove('active');
                }
            });

            btn.classList.add('active');
            updateExportCardVisibility();
        });
    });

    updateExportCardVisibility();

    // Filtros por vista
    function getProgramFilters() {
        return {
            programid: programSel ? (programSel.value || '0') : '0'
        };
    }

    function getTermFilters() {
        return {
            programid: programSelTerm ? (programSelTerm.value || '0') : '0',
            termid:    termSel ? (termSel.value || '0') : '0',
            teacherid: teacherSel ? (teacherSel.value || '0') : '0',
            groupid:   groupSel ? (groupSel.value || '0') : '0'
        };
    }

    // --------- Render vista general ---------
    function renderGeneral(global) {
        if (!global || !global.total) {
            generalBody.innerHTML = '<span class=\"local-mai-help-text\">No hay datos para mostrar el resumen global.</span>';
            if (globalChart) {
                globalChart.destroy();
                globalChart = null;
            }
            generalChart.innerHTML = '';
            return;
        }

        generalBody.innerHTML = '';

        function statBox(label, value) {
            var box = document.createElement('div');
            box.className = 'local-mai-stat-box';

            var l = document.createElement('div');
            l.className = 'local-mai-stat-label';
            l.textContent = label;

            var v = document.createElement('div');
            v.className = 'local-mai-stat-value';
            v.textContent = value;

            box.appendChild(l);
            box.appendChild(v);
            return box;
        }

        var frag = document.createDocumentFragment();
        frag.appendChild(statBox('Cursos con usuarios inscritos', global.courses));
        frag.appendChild(statBox('Usuarios inscritos', global.total));
        frag.appendChild(statBox('Usuarios activos / inactivos / nunca ingresaron', global.active + ' / ' + global.inactive + ' / ' + global.never));
        frag.appendChild(statBox('Retención global de usuarios activos (%)', global.retention + '%'));

        generalBody.appendChild(frag);

        // Gráfica donut global.
        generalChart.innerHTML = '';
        if (typeof ApexCharts !== 'undefined') {
            var options = {
                chart: {
                    type: 'donut',
                    height: 260
                },
                labels: ['Usuarios activos', 'Usuarios inactivos', 'Usuarios que nunca ingresaron'],
                series: [global.active, global.inactive, global.never],
                dataLabels: {
                    enabled: true
                },
                legend: {
                    position: 'bottom'
                }
            };
            globalChart = new ApexCharts(generalChart, options);
            globalChart.render();
        }
    }

    // --------- Render vista por programa ---------
    function renderPrograms(programstats, selectedProgramId) {
        progTableEl.innerHTML = '';

        var dataToShow = programstats || [];
        var selectedId = selectedProgramId ? String(selectedProgramId) : '0';

        if (!dataToShow.length) {
            if (progChart) {
                progChart.destroy();
                progChart = null;
            }
            progChartEl.innerHTML = '';
            if (programDataTable && programDataTable.destroy) {
                programDataTable.destroy();
                programDataTable = null;
            }
            progTableEl.innerHTML = '<p class=\"local-mai-help-text\">No se encontraron programas académicos con cursos para estos filtros.</p>';
            return;
        }

        // Si hay programa seleccionado, filtramos también del lado del frontend
        if (selectedId !== '0') {
            dataToShow = dataToShow.filter(function(ps) {
                return String(ps.id) === selectedId;
            });
        }

        if (!dataToShow.length) {
            if (progChart) {
                progChart.destroy();
                progChart = null;
            }
            progChartEl.innerHTML = '';
            if (programDataTable && programDataTable.destroy) {
                programDataTable.destroy();
                programDataTable = null;
            }
            progTableEl.innerHTML = '<p class=\"local-mai-help-text\">No hay datos para el programa académico seleccionado.</p>';
            return;
        }

        var html = '<table class=\"local-mai-table\" id=\"local-mai-programs-datatable\">';
        html += '<thead><tr>' +
            '<th>Programa académico</th>' +
            '<th>Cursos en el programa</th>' +
            '<th>Usuarios activos</th>' +
            '<th>Usuarios inactivos</th>' +
            '<th>Usuarios que nunca ingresaron</th>' +
            '<th>Usuarios inscritos</th>' +
            '<th>Retención de usuarios activos (%)</th>' +
            '</tr></thead><tbody>';

        var categories    = [];
        var dataRetention = [];

        dataToShow.forEach(function(ps) {
            var cls = (selectedId !== '0' && String(selectedId) === String(ps.id)) ? 'local-mai-program-highlight' : '';
            html += '<tr class=\"' + cls + '\">' +
                '<td>' + ps.name + '</td>' +
                '<td>' + ps.courses + '</td>' +
                '<td>' + ps.active + '</td>' +
                '<td>' + ps.inactive + '</td>' +
                '<td>' + ps.never + '</td>' +
                '<td>' + ps.total + '</td>' +
                '<td>' + ps.retention + '%</td>' +
                '</tr>';

            categories.push(ps.name);
            dataRetention.push(ps.retention);
        });

        html += '</tbody></table>';
        progTableEl.innerHTML = html;

        // DataTable limpia (sin lengthChange, en español)
        if ($ && $.fn && $.fn.DataTable) {
            if (programDataTable && programDataTable.destroy) {
                programDataTable.destroy();
            }
            programDataTable = $('#local-mai-programs-datatable').DataTable({
                paging: true,
                searching: true,
                info: true,
                lengthChange: false,
                pageLength: 10,
                order: [],
                language: dtLanguageEs
            });
        }

        // Gráfica barras de retención por programa.
        progChartEl.innerHTML = '';
        if (typeof ApexCharts !== 'undefined' && categories.length) {
            var options = {
                chart: {
                    type: 'bar',
                    height: 260
                },
                series: [{
                    name: 'Retención de usuarios activos (%)',
                    data: dataRetention
                }],
                xaxis: {
                    categories: categories
                },
                dataLabels: {
                    enabled: true,
                    formatter: function (val) {
                        return val + '%';
                    }
                }
            };
            progChart = new ApexCharts(progChartEl, options);
            progChart.render();
        }
    }

    // --------- Render vista por cuatrimestre ---------
    function renderTerm(termstats, termcourses, context) {
        termSummaryEl.innerHTML = '';
        termTableEl.innerHTML   = '';
        termChartEl.innerHTML   = '';

        if (termChart) {
            termChart.destroy();
            termChart = null;
        }
        if (termDataTable && termDataTable.destroy) {
            termDataTable.destroy();
            termDataTable = null;
        }

        var pname = (context && context.programname) ? context.programname : '(sin programa)';
        var tname = (context && context.termname) ? context.termname : '(sin cuatrimestre)';

        var header = '<div class=\"local-mai-term-context\">' +
            '<div><span class=\"local-mai-term-label\">Programa:</span> <strong>' + pname + '</strong></div>' +
            '<div><span class=\"local-mai-term-label\">Cuatrimestre:</span> <strong>' + tname + '</strong></div>';

        if (context && context.teachername) {
            header += '<div><span class=\"local-mai-term-label\">Docente:</span> <strong>' + context.teachername + '</strong></div>';
        }
        if (context && context.groupname) {
            header += '<div><span class=\"local-mai-term-label\">Grupo:</span> <strong>' + context.groupname + '</strong></div>';
        }
        header += '</div>';

        if (!termcourses || !termcourses.length) {
            termSummaryEl.innerHTML = header + '<p class=\"local-mai-help-text\">No se encontraron cursos para los filtros seleccionados. Cambia de programa, cuatrimestre, docente o grupo.</p>';
            return;
        }

        var total = termstats.total || 0;
        var summaryHtml = header + '<div class=\"local-mai-term-stats\">';

        function stat(label, value) {
            return '<div class=\"local-mai-stat-box local-mai-stat-box-sm\">' +
                '<div class=\"local-mai-stat-label\">' + label + '</div>' +
                '<div class=\"local-mai-stat-value\">' + value + '</div>' +
                '</div>';
        }

        summaryHtml += stat('Cursos en el cuatrimestre', termstats.courses || 0);
        summaryHtml += stat('Usuarios inscritos', total);
        summaryHtml += stat(
            'Usuarios activos / inactivos / nunca ingresaron',
            (termstats.active || 0) + ' / ' + (termstats.inactive || 0) + ' / ' + (termstats.never || 0)
        );
        summaryHtml += stat('Retención del cuatrimestre (%)', (termstats.retention || 0) + '%');
        summaryHtml += '</div>';

        termSummaryEl.innerHTML = summaryHtml;

        // Tabla detalle por curso
        var tableHtml = '<table class=\"local-mai-table\" id=\"local-mai-term-datatable\">';
        tableHtml += '<thead><tr>' +
            '<th>Curso</th>' +
            '<th>Usuarios activos</th>' +
            '<th>Usuarios inactivos</th>' +
            '<th>Usuarios que nunca ingresaron</th>' +
            '<th>Usuarios inscritos</th>' +
            '<th>Retención de usuarios activos (%)</th>' +
            '</tr></thead><tbody>';

        var categories    = [];
        var dataRetention = [];

        termcourses.forEach(function(c) {
            tableHtml += '<tr>' +
                '<td>' + c.fullname + '</td>' +
                '<td>' + c.active + '</td>' +
                '<td>' + c.inactive + '</td>' +
                '<td>' + c.never + '</td>' +
                '<td>' + c.total + '</td>' +
                '<td>' + c.retention + '%</td>' +
                '</tr>';

            categories.push(c.fullname);
            dataRetention.push(c.retention);
        });

        tableHtml += '</tbody></table>';
        termTableEl.innerHTML = tableHtml;

        if ($ && $.fn && $.fn.DataTable) {
            termDataTable = $('#local-mai-term-datatable').DataTable({
                paging: true,
                searching: true,
                info: true,
                lengthChange: false,
                pageLength: 10,
                order: [],
                language: dtLanguageEs
            });
        }

        // Gráfica barras por curso
        if (typeof ApexCharts !== 'undefined' && categories.length) {
            var options = {
                chart: {
                    type: 'bar',
                    height: 260
                },
                series: [{
                    name: 'Retención de usuarios activos (%)',
                    data: dataRetention
                }],
                xaxis: {
                    categories: categories
                },
                dataLabels: {
                    enabled: true,
                    formatter: function (val) {
                        return val + '%';
                    }
                }
            };
            termChart = new ApexCharts(termChartEl, options);
            termChart.render();
        }
    }

    // Actualiza opciones de cuatrimestre/docente/grupo para la vista de cuatrimestre
    function updateFilterOptions(filtersData) {
        if (!filtersData) {
            return;
        }

        // Cuatrimestres dinámicos
        if (filtersData.terms && termSel) {
            var currentTerm = termSel.value;
            termSel.innerHTML = '';

            var optAll = document.createElement('option');
            optAll.value = '0';
            optAll.textContent = 'Selecciona una opción';
            termSel.appendChild(optAll);

            filtersData.terms.forEach(function(t) {
                var o = document.createElement('option');
                o.value = t.id;
                o.textContent = t.name;
                termSel.appendChild(o);
            });

            if (currentTerm && termSel.querySelector('option[value=\"' + currentTerm + '\"]')) {
                termSel.value = currentTerm;
            } else {
                termSel.value = '0';
            }
        }

        // Docentes dinámicos
        if (filtersData.teachers && teacherSel) {
            var currentTeacher = teacherSel.value;
            teacherSel.innerHTML = '';

            var optT = document.createElement('option');
            optT.value = '0';
            optT.textContent = 'Todos los docentes';
            teacherSel.appendChild(optT);

            filtersData.teachers.forEach(function(t) {
                var o = document.createElement('option');
                o.value = t.id;
                o.textContent = t.fullname;
                teacherSel.appendChild(o);
            });

            if (currentTeacher && teacherSel.querySelector('option[value=\"' + currentTeacher + '\"]')) {
                teacherSel.value = currentTeacher;
            } else {
                teacherSel.value = '0';
            }
        }

        // Grupos dinámicos
        if (filtersData.groups && groupSel) {
            var currentGroup = groupSel.value;
            groupSel.innerHTML = '';

            var optG = document.createElement('option');
            optG.value = '0';
            optG.textContent = 'Todos los grupos';
            groupSel.appendChild(optG);

            filtersData.groups.forEach(function(g) {
            var o = document.createElement('option');
                o.value = g.id;
                o.textContent = g.name;
                groupSel.appendChild(o);
            });

            if (currentGroup && groupSel.querySelector('option[value=\"' + currentGroup + '\"]')) {
                groupSel.value = currentGroup;
            } else {
                groupSel.value = '0';
            }
        }
    }

    // --------- Cargas AJAX ---------
    // General + Programas (solo una vez; luego se filtra en frontend)
    function loadGeneralPrograms() {
        if (globalChart) {
            globalChart.destroy();
            globalChart = null;
        }
        if (progChart) {
            progChart.destroy();
            progChart = null;
        }

        generalBody.innerHTML = '<div class=\"local-mai-inline-loading\"><div class=\"local-mai-loading-spinner\"></div><span>Cargando información general...</span></div>';
        progTableEl.innerHTML = '<div class=\"local-mai-inline-loading\"><div class=\"local-mai-loading-spinner\"></div><span>Cargando programas académicos...</span></div>';

        if (programDataTable && programDataTable.destroy) {
            programDataTable.destroy();
            programDataTable = null;
        }

        var params = new URLSearchParams();
        params.append('programid', 0); // global
        params.append('termid', 0);
        params.append('teacherid', 0);
        params.append('groupid', 0);
        params.append('sesskey', sesskey);

        fetch(ajaxUrl + '?' + params.toString(), {
            credentials: 'same-origin'
        }).then(function(response) {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.json();
        }).then(function(data) {
            cachedGlobalData   = data.global || null;
            cachedProgramStats = data.programstats || [];

            renderGeneral(cachedGlobalData);

            var f = getProgramFilters();
            renderPrograms(cachedProgramStats, f.programid);
        }).catch(function(err) {
            console.error(err);
            generalBody.innerHTML = '<span class=\"local-mai-help-text\">Ocurrió un error al cargar la información general. Intenta recargar la página.</span>';
            progTableEl.innerHTML  = '<span class=\"local-mai-help-text\">Ocurrió un error al cargar los programas académicos.</span>';
        });
    }

    // Solo vista de cuatrimestre (usa su propio selector de programa)
    function loadTermView() {
        var f = getTermFilters();

        if (!f.programid || f.programid === '0') {
            termSummaryEl.innerHTML = '<span class=\"local-mai-help-text\">Selecciona un programa académico para cargar cuatrimestres y cursos.</span>';
            termTableEl.innerHTML   = '';
            termChartEl.innerHTML   = '';
            return;
        }

        if (!f.termid || f.termid === '0') {
            termSummaryEl.innerHTML = '<span class=\"local-mai-help-text\">Selecciona un cuatrimestre para ver el detalle por curso.</span>';
            termTableEl.innerHTML   = '';
            termChartEl.innerHTML   = '';
            return;
        }

        termSummaryEl.innerHTML = '<div class=\"local-mai-inline-loading\"><div class=\"local-mai-loading-spinner\"></div><span>Cargando detalle por cuatrimestre...</span></div>';
        termTableEl.innerHTML   = '';
        termChartEl.innerHTML   = '';

        if (termChart) {
            termChart.destroy();
            termChart = null;
        }
        if (termDataTable && termDataTable.destroy) {
            termDataTable.destroy();
            termDataTable = null;
        }

        var params = new URLSearchParams();
        params.append('programid', f.programid || 0);
        params.append('termid',    f.termid || 0);
        params.append('teacherid', f.teacherid || 0);
        params.append('groupid',   f.groupid || 0);
        params.append('sesskey',   sesskey);

        fetch(ajaxUrl + '?' + params.toString(), {
            credentials: 'same-origin'
        }).then(function(response) {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.json();
        }).then(function(data) {
            renderTerm(data.termstats || {}, data.termcourses || [], data.context || {});
            if (data.filters) {
                cachedFiltersByProgram[f.programid] = data.filters;
                updateFilterOptions(data.filters);
            }
        }).catch(function(err) {
            console.error(err);
            termSummaryEl.innerHTML = '<span class=\"local-mai-help-text\">Ocurrió un error al cargar el detalle por cuatrimestre.</span>';
            termTableEl.innerHTML   = '';
            termChartEl.innerHTML   = '';
        });
    }

    // Cargar SOLO filtros (cuatrimestres) al cambiar programa en pestaña cuatrimestre
    function loadTermFiltersForProgram() {
        if (!programSelTerm) {
            return;
        }
        var pid = programSelTerm.value || '0';

        // Reiniciamos selects dependientes
        if (termSel) {
            termSel.value = '0';
        }
        if (teacherSel) {
            teacherSel.value = '0';
        }
        if (groupSel) {
            groupSel.value = '0';
        }

        if (!pid || pid === '0') {
            termSummaryEl.innerHTML = '<span class=\"local-mai-help-text\">Selecciona un programa académico para cargar cuatrimestres y cursos.</span>';
            termTableEl.innerHTML   = '';
            termChartEl.innerHTML   = '';
            return;
        }

        termSummaryEl.innerHTML = '<div class=\"local-mai-inline-loading\"><div class=\"local-mai-loading-spinner\"></div><span>Actualizando cuatrimestres y filtros...</span></div>';

        // Si ya tenemos filtros en cache para este programa, usamos eso sin llamar al servidor
        if (cachedFiltersByProgram[pid]) {
            updateFilterOptions(cachedFiltersByProgram[pid]);
            termSummaryEl.innerHTML = '<span class=\"local-mai-help-text\">Ahora selecciona un cuatrimestre para ver el detalle por curso.</span>';
            termTableEl.innerHTML   = '';
            termChartEl.innerHTML   = '';
            return;
        }

        var params = new URLSearchParams();
        params.append('mode',      'filters'); // llamada ligera para filtros
        params.append('programid', pid);
        params.append('termid',    0);
        params.append('teacherid', 0);
        params.append('groupid',   0);
        params.append('sesskey',   sesskey);

        fetch(ajaxUrl + '?' + params.toString(), {
            credentials: 'same-origin'
        }).then(function(response) {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.json();
        }).then(function(data) {
            var filters = data.filters || {};
            cachedFiltersByProgram[pid] = filters;
            updateFilterOptions(filters);

            termSummaryEl.innerHTML = '<span class=\"local-mai-help-text\">Selecciona un cuatrimestre para ver el detalle por curso.</span>';
            termTableEl.innerHTML   = '';
            termChartEl.innerHTML   = '';
        }).catch(function(err) {
            console.error(err);
            termSummaryEl.innerHTML = '<span class=\"local-mai-help-text\">Ocurrió un error al actualizar los filtros para este programa.</span>';
            termTableEl.innerHTML   = '';
            termChartEl.innerHTML   = '';
        });
    }

    // --------- Exportar según pestaña activa ---------
    function buildExportUrl(format) {
        var tab = getActiveTabKey();
        var programid = 0;
        var termid    = 0;
        var teacherid = 0;
        var groupid   = 0;

        if (tab === 'programas') {
            var fP = getProgramFilters();
            programid = fP.programid || 0;
        } else if (tab === 'term') {
            var fT = getTermFilters();
            programid = fT.programid || 0;
            termid    = fT.termid || 0;
            teacherid = fT.teacherid || 0;
            groupid   = fT.groupid || 0;
        } else {
            programid = 0;
        }

        var params = new URLSearchParams();
        params.append('format',    format);
        params.append('programid', programid);
        params.append('termid',    termid);
        params.append('teacherid', teacherid);
        params.append('groupid',   groupid);
        params.append('sesskey',   sesskey);

        return exportUrlBase + '?' + params.toString();
    }

    // --------- Eventos ---------

    if (form) {
        form.addEventListener('submit', function(ev) {
            ev.preventDefault();
            loadGeneralPrograms();
        });
    }

    // Cambio de programa en pestaña PROGRAMAS -> solo filtra en frontend si ya tenemos datos
    if (programSel) {
        programSel.addEventListener('change', function() {
            if (cachedProgramStats) {
                var f = getProgramFilters();
                renderPrograms(cachedProgramStats, f.programid);
            } else {
                loadGeneralPrograms();
            }
        });
    }

    // Cambio de programa en pestaña CUATRIMESTRE -> SOLO actualizar filtros (llamada ligera)
    if (programSelTerm) {
        programSelTerm.addEventListener('change', function() {
            loadTermFiltersForProgram();
        });
    }

    // Cuatrimestre, docente y grupo (solo afectan vista por cuatrimestre)
    if (termSel) {
        termSel.addEventListener('change', function() {
            loadTermView();
        });
    }
    if (teacherSel) {
        teacherSel.addEventListener('change', function() {
            loadTermView();
        });
    }
    if (groupSel) {
        groupSel.addEventListener('change', function() {
            loadTermView();
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

    if (exportCsvBtn) {
        exportCsvBtn.addEventListener('click', function() {
            var url = buildExportUrl('csv');
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

    // Carga inicial
    loadGeneralPrograms();
    termSummaryEl.innerHTML = '<span class=\"local-mai-help-text\">Selecciona un programa académico para cargar los cuatrimestres disponibles y después elige un cuatrimestre para ver el detalle por curso.</span>';
});
</script>

<?php
echo $OUTPUT->footer();
