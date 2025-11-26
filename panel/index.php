<?php
// local/mai/panel/index.php
/**
 * Panel de control con gráficos interactivos (punto 4 MAI).
 *
 * @package   local_mai
 */

require(__DIR__ . '/../../../config.php');

require_login();

global $DB;

$systemcontext = context_system::instance();
require_capability('local/mai:viewreport', $systemcontext);

$pagetitle = 'Panel de control';

$PAGE->set_url(new moodle_url('/local/mai/panel/index.php'));
$PAGE->set_context($systemcontext);
$PAGE->set_pagelayout('report');
$PAGE->set_title($pagetitle);

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

// ======================
// Filtros base (programa / cuatrimestre)
// ======================

// Programas = categorías padre.
$programcats = $DB->get_records('course_categories', ['parent' => 0], 'sortorder',
    'id, name, parent');

$programoptions = [0 => 'Todos los programas'];
foreach ($programcats as $pcat) {
    $programoptions[$pcat->id] = format_string($pcat->name);
}

// Cuatrimestres (dinámicos, se llenan por AJAX).
$termoptions = [0 => 'Todos los cuatrimestres'];

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

#page-local-mai-panel-index {
    background: radial-gradient(circle at top left, #f9fafb 0, #ffffff 55%, #f1f5f9 100%);
}

/* Layout principal */
.local-mai-panel-layout {
    width: 100%;
    max-width: 1200px;
    margin: 10px auto 32px;
    padding: 8px 12px 24px;
    display: flex;
    flex-direction: column;
    gap: 16px;
    font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

/* Encabezado del panel */
.local-mai-panel-header {
    display: flex;
    flex-direction: column;
    gap: 4px;
    margin-bottom: 4px;
}

.local-mai-panel-title {
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0;
    color: var(--mai-maroon);
}

.local-mai-panel-subtitle {
    color: var(--mai-text-muted);
    margin: 0;
    font-size: 0.95rem;
}

.local-mai-panel-bullets {
    margin: 6px 0 8px 0;
    padding-left: 18px;
    color: var(--mai-text-muted);
    font-size: 0.82rem;
}
.local-mai-panel-bullets li {
    margin-bottom: 2px;
}

/* Card genérica */
.local-mai-card {
    position: relative;
    border-radius: 20px;
    border: 1px solid transparent;
    background: linear-gradient(#ffffff, #ffffff) padding-box,
                radial-gradient(circle at top left, rgba(140,37,62,0.10), rgba(255,112,0,0.03)) border-box;
    margin-bottom: 8px;
    box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
    overflow: hidden;
    background-clip: padding-box, border-box;
    padding: 0;
    transition: transform 0.16s ease, box-shadow 0.16s ease;
}

.local-mai-card:hover {
    transform: translateY(-1px);
    box-shadow: 0 22px 45px rgba(15,23,42,0.14);
}

/* Header de card con icono */
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
    font-size: 0.8rem;
    color: var(--mai-text-muted);
    margin: 2px 0 0;
}

/* Variantes de header por card */
.local-mai-card-header--global .local-mai-card-header-icon {
    background: rgba(37, 99, 235, 0.10);
    color: #2563eb;
}
.local-mai-card-header--progress .local-mai-card-header-icon {
    background: rgba(14, 165, 233, 0.14);
    color: #0ea5e9;
}
.local-mai-card-header--terms .local-mai-card-header-icon {
    background: rgba(249, 115, 22, 0.14);
    color: var(--mai-orange);
}
.local-mai-card-header--filters .local-mai-card-header-icon {
    background: rgba(148, 163, 184, 0.16);
    color: var(--mai-text-main);
}

.local-mai-card-body {
    padding: 12px 18px 16px;
}

/* Filtros panel */
.local-mai-panel-filters-box {
    border-radius: 18px;
    border: 1px solid rgba(140,37,62,0.08);
    background: linear-gradient(135deg, #f8fafc, #eef2ff);
    padding: 12px 16px 14px;
    box-shadow: 0 10px 26px rgba(15,23,42,0.05);
}

.local-mai-panel-filters-title {
    font-size: 0.95rem;
    font-weight: 600;
    margin: 0 0 4px;
    color: var(--mai-text-main);
}

.local-mai-panel-filters-help {
    font-size: 0.8rem;
    color: var(--mai-text-muted);
    margin-bottom: 10px;
}

/* Form filtros */
.local-mai-panel-filters-form {
    display: flex;
    flex-wrap: wrap;
    gap: 10px 16px;
    align-items: flex-end;
}

.local-mai-filter-group {
    display: flex;
    flex-direction: column;
    min-width: 200px;
}

.local-mai-filter-group label {
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--mai-text-muted);
    margin: 0 0 2px 2px;
}

.local-mai-filter-group select.custom-select {
    border-radius: 999px;
    border: 1px solid var(--mai-border-soft);
    font-size: 0.84rem;
    padding: 7px 34px 7px 12px;
    background-color: #f9fafb;
    transition: box-shadow 0.18s ease, border-color 0.18s ease, background-color 0.18s ease, transform 0.1s ease;
}

.local-mai-filter-group select.custom-select:hover {
    background-color: #ffffff;
}

.local-mai-filter-group select.custom-select:focus {
    outline: none;
    border-color: var(--mai-orange);
    background-color: #ffffff;
    box-shadow: 0 0 0 1px rgba(255,112,0,0.25);
    transform: translateY(-1px);
}

/* Botón aplicar filtro */
.local-mai-btn-primary {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background-color: var(--mai-maroon);
    border: none;
    color: #ffffff;
    border-radius: 12px;
    padding: 8px 18px;
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
    filter: brightness(1.04);
    transform: translateY(-1px);
}

/* Filas de gráficas */
.local-mai-charts-row {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
}

.local-mai-chart-box {
    flex: 1 1 320px;
}

/* Contenedor de gráficas */
.local-mai-chart-container {
    min-height: 260px;
}

/* Stats pequeños debajo de las gráficas */
.local-mai-inline-stats {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 8px;
    font-size: 0.78rem;
    color: var(--mai-text-muted);
}

.local-mai-inline-stat-pill {
    padding: 4px 10px;
    border-radius: 999px;
    background-color: #f1f5f9;
}

/* Texto ayuda */
.local-mai-help-text {
    font-size: 0.8rem;
    color: var(--mai-text-muted);
    margin-bottom: 8px;
}

/* Inline loading */
.local-mai-inline-loading {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 0.82rem;
    color: var(--mai-text-muted);
}
.local-mai-inline-spinner {
    width: 18px;
    height: 18px;
    border-radius: 999px;
    border: 2px solid #e5e7eb;
    border-top-color: var(--mai-orange);
    animation: local-mai-spin 0.7s linear infinite;
}

@keyframes local-mai-spin {
    to { transform: rotate(360deg); }
}

/* Responsive */
@media (max-width: 768px) {
    .local-mai-charts-row {
        flex-direction: column;
    }
    .local-mai-panel-filters-form {
        flex-direction: column;
        align-items: stretch;
    }
    .local-mai-filter-group {
        min-width: 100%;
    }
}
    .local-mai-panel-filters-box {
    border-radius: 18px;
    border: 1px solid rgba(140,37,62,0.08);
    background: linear-gradient(135deg, #f8fafc, #eef2ff);
    padding: 12px 16px 14px;
    box-shadow: 0 10px 26px rgba(15,23,42,0.05);
}

/* NUEVO: header de filtros con icono */
.local-mai-panel-filters-header {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    margin-bottom: 8px;
}

.local-mai-panel-filters-icon {
    width: 28px;
    height: 28px;
    border-radius: 999px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(140,37,62,0.10);
    color: var(--mai-maroon);
    font-size: 0.9rem;
}

.local-mai-panel-filters-text {
    display: flex;
    flex-direction: column;
}

.local-mai-panel-filters-title {
    font-size: 0.95rem;
    font-weight: 600;
    margin: 0 0 2px;
    color: var(--mai-text-main);
}

.local-mai-panel-filters-help {
    font-size: 0.8rem;
    color: var(--mai-text-muted);
    margin: 0;
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

// Font Awesome para iconos de los headers
echo html_writer::empty_tag('link', [
    'rel'  => 'stylesheet',
    'href' => 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css'
]);

// ======================
// Layout principal
// ======================
echo html_writer::start_div('local-mai-panel-layout');

// Encabezado
echo html_writer::start_div('local-mai-panel-header');
echo html_writer::tag(
    'div',
    'Visualiza indicadores globales de participación, retención y avance de actividades.',
    ['class' => 'local-mai-panel-subtitle']
);
echo html_writer::end_div(); // panel-header

// ======================
// Filtros (programa / cuatrimestre)
// ======================
echo html_writer::start_div('local-mai-panel-filters-box');

// Header: icono de filtro + textos
echo html_writer::start_div('local-mai-panel-filters-header');

// Icono
echo html_writer::tag(
    'span',
    '<i class="fa-solid fa-filter"></i>',
    ['class' => 'local-mai-panel-filters-icon']
);

// Textos
echo html_writer::start_div('local-mai-panel-filters-text');
echo html_writer::tag('div', 'Filtros de búsqueda', ['class' => 'local-mai-panel-filters-title']);
echo html_writer::tag(
    'div',
    'Elige el programa y, opcionalmente, un cuatrimestre para ajustar los gráficos.',
    ['class' => 'local-mai-panel-filters-help']
);
echo html_writer::end_div(); // .local-mai-panel-filters-text

echo html_writer::end_div(); // .local-mai-panel-filters-header

// Formulario de filtros
echo html_writer::start_tag('form', [
    'method' => 'get',
    'class'  => 'local-mai-panel-filters-form',
    'id'     => 'local-mai-panel-filters'
]);

// Programa.
echo html_writer::start_div('local-mai-filter-group');
echo html_writer::label('Programa académico', 'id_panel_programid');
echo html_writer::select($programoptions, 'programid', 0, null, [
    'id'    => 'id_panel_programid',
    'class' => 'custom-select'
]);
echo html_writer::end_div();

// Cuatrimestre.
echo html_writer::start_div('local-mai-filter-group');
echo html_writer::label('Cuatrimestre', 'id_panel_termid');
echo html_writer::select($termoptions, 'termid', 0, null, [
    'id'    => 'id_panel_termid',
    'class' => 'custom-select'
]);
echo html_writer::end_div();

// Botón aplicar.
echo html_writer::start_div('local-mai-filter-group');
echo html_writer::empty_tag('input', [
    'type'  => 'submit',
    'value' => 'Aplicar filtro',
    'class' => 'local-mai-btn-primary'
]);
echo html_writer::end_div();

echo html_writer::end_tag('form');
echo html_writer::end_div(); // filtros

// ======================
// Card 1: Donut + barras por programa (retención)
// ======================
echo html_writer::start_div('local-mai-card');

// header card 1
echo html_writer::start_div('local-mai-card-header local-mai-card-header--global');
echo html_writer::start_div('local-mai-card-header-main');
echo html_writer::tag('span', '<i class="fa-solid fa-chart-pie"></i>', [
    'class' => 'local-mai-card-header-icon'
]);
echo html_writer::start_div('local-mai-card-header-text');
echo html_writer::tag('div', 'Participación global y retención por programa', ['class' => 'local-mai-card-title']);
echo html_writer::tag(
    'div',
    'Distribución de estudiantes y retención promedio agregada por programa académico.',
    ['class' => 'local-mai-card-subtitle']
);
echo html_writer::end_div(); // header-text
echo html_writer::end_div(); // header-main
echo html_writer::end_div(); // card-header

echo html_writer::start_div('local-mai-card-body');

echo html_writer::start_div('local-mai-charts-row');

// Donut global
echo html_writer::start_div('local-mai-chart-box');
echo html_writer::tag('div', '', [
    'id'    => 'local-mai-panel-donut',
    'class' => 'local-mai-chart-container'
]);
echo html_writer::start_div('local-mai-inline-stats', ['id' => 'local-mai-panel-donut-stats']);
echo html_writer::end_div();
echo html_writer::end_div();

// Barra por programa (retención)
echo html_writer::start_div('local-mai-chart-box');
echo html_writer::tag('div', '', [
    'id'    => 'local-mai-panel-bar-programas',
    'class' => 'local-mai-chart-container'
]);
echo html_writer::start_div('local-mai-inline-stats', ['id' => 'local-mai-panel-bar-programas-stats']);
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::end_div(); // charts-row
echo html_writer::end_div(); // card-body
echo html_writer::end_div(); // card

// ======================
// Card 2: Promedio de actividades completadas
// ======================
echo html_writer::start_div('local-mai-card');

// header card 2
echo html_writer::start_div('local-mai-card-header local-mai-card-header--progress');
echo html_writer::start_div('local-mai-card-header-main');
echo html_writer::tag('span', '<i class="fa-solid fa-list-check"></i>', [
    'class' => 'local-mai-card-header-icon'
]);
echo html_writer::start_div('local-mai-card-header-text');
echo html_writer::tag('div', 'Promedio de actividades completadas', ['class' => 'local-mai-card-title']);
echo html_writer::tag(
    'div',
    'Porcentaje promedio de actividades completadas en cada programa académico.',
    ['class' => 'local-mai-card-subtitle']
);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div(); // card-header

echo html_writer::start_div('local-mai-card-body');

echo html_writer::tag('div', '', [
    'id'    => 'local-mai-panel-bar-progress',
    'class' => 'local-mai-chart-container'
]);
echo html_writer::start_div('local-mai-inline-stats', ['id' => 'local-mai-panel-bar-progress-stats']);
echo html_writer::end_div();

echo html_writer::end_div(); // card-body
echo html_writer::end_div(); // card

// ======================
// Card 3: Comparación entre cuatrimestres
// ======================
echo html_writer::start_div('local-mai-card');

// header card 3
echo html_writer::start_div('local-mai-card-header local-mai-card-header--terms');
echo html_writer::start_div('local-mai-card-header-main');
echo html_writer::tag('span', '<i class="fa-solid fa-layer-group"></i>', [
    'class' => 'local-mai-card-header-icon'
]);
echo html_writer::start_div('local-mai-card-header-text');
echo html_writer::tag('div', 'Comparación de retención entre cuatrimestres', ['class' => 'local-mai-card-title']);
echo html_writer::tag(
    'div',
    'Compara la tasa de retención entre los cuatrimestres del programa seleccionado.',
    ['class' => 'local-mai-card-subtitle']
);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div(); // card-header

echo html_writer::start_div('local-mai-card-body');

echo html_writer::tag('div', '', [
    'id'    => 'local-mai-panel-bar-terms',
    'class' => 'local-mai-chart-container'
]);
echo html_writer::start_div('local-mai-inline-stats', ['id' => 'local-mai-panel-bar-terms-stats']);
echo html_writer::end_div();

echo html_writer::end_div(); // card-body
echo html_writer::end_div(); // card

echo html_writer::end_div(); // .local-mai-panel-layout

// ======================
// JS: usamos AJAX propio del panel, que a su vez llama a lib de estructura.
// ======================
$ajaxurl = (new moodle_url('/local/mai/panel/ajax.php'))->out(false);
$sesskey = sesskey();

// Carga segura de ApexCharts (sin AMD).
echo '<script>
    window.__apex_define = window.define;
    window.define = undefined;
</script>';
echo '<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>';
echo '<script>
    window.define = window.__apex_define;
</script>';

?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var ajaxUrl = '<?php echo $ajaxurl; ?>';
    var sesskey = '<?php echo $sesskey; ?>';

    var form       = document.getElementById('local-mai-panel-filters');
    var programSel = document.getElementById('id_panel_programid');
    var termSel    = document.getElementById('id_panel_termid');

    var donutEl        = document.getElementById('local-mai-panel-donut');
    var donutStats     = document.getElementById('local-mai-panel-donut-stats');
    var barProgEl      = document.getElementById('local-mai-panel-bar-programas');
    var barProgStats   = document.getElementById('local-mai-panel-bar-programas-stats');
    var barProgressEl  = document.getElementById('local-mai-panel-bar-progress');
    var barProgressStats = document.getElementById('local-mai-panel-bar-progress-stats');
    var barTermsEl     = document.getElementById('local-mai-panel-bar-terms');
    var barTermsStats  = document.getElementById('local-mai-panel-bar-terms-stats');

    var donutChart      = null;
    var barProgChart    = null;
    var barProgressChart= null;
    var barTermsChart   = null;

    function getFilters() {
        return {
            programid: programSel ? programSel.value : '0',
            termid:    termSel ? termSel.value : '0'
        };
    }

    function clearCharts() {
        if (donutChart)        { donutChart.destroy(); donutChart = null; }
        if (barProgChart)      { barProgChart.destroy(); barProgChart = null; }
        if (barProgressChart)  { barProgressChart.destroy(); barProgressChart = null; }
        if (barTermsChart)     { barTermsChart.destroy(); barTermsChart = null; }
    }

    // --------- Render donut global ---------
    function renderDonut(global) {
        donutEl.innerHTML = '';
        donutStats.innerHTML = '';

        if (!global || !global.total) {
            donutEl.innerHTML = '<p class=\"local-mai-help-text\">No hay datos para la distribución actual.</p>';
            return;
        }

        if (typeof ApexCharts === 'undefined') {
            donutEl.innerHTML = '<p class=\"local-mai-help-text\">ApexCharts no está disponible.</p>';
            return;
        }

        var options = {
            chart: {
                type: 'donut',
                height: 260
            },
            labels: ['Activos', 'Inactivos', 'Nunca ingresó'],
            series: [global.active, global.inactive, global.never],
            colors: ['#16a34a', '#f97316', '#94a3b8'],
            dataLabels: {
                enabled: true,
                formatter: function (val, opts) {
                    var total = opts.w.globals.seriesTotals.reduce(function(a, b){ return a + b; }, 0);
                    var value = opts.w.globals.series[opts.seriesIndex];
                    if (!total) { return '0%'; }
                    var pct = (value / total) * 100;
                    return pct.toFixed(1) + '%';
                }
            },
            legend: {
                position: 'bottom'
            },
            tooltip: {
                y: {
                    formatter: function(val) {
                        return val + ' estudiantes';
                    }
                }
            }
        };

        donutChart = new ApexCharts(donutEl, options);
        donutChart.render();

        var pills = [
            'Cursos con matrículas: ' + global.courses,
            'Total de matrículas: ' + global.total,
            'Retención global: ' + (global.retention || 0) + '%'
        ];
        var avgcomp = (global.avgcompletion !== undefined && global.avgcompletion !== null)
            ? global.avgcompletion : null;
        if (avgcomp !== null) {
            pills.push('Promedio global de actividades completadas: ' + avgcomp + '%');
        }

        pills.forEach(function(txt) {
            var span = document.createElement('span');
            span.className = 'local-mai-inline-stat-pill';
            span.textContent = txt;
            donutStats.appendChild(span);
        });
    }

    // --------- Render barra de retención por programa ---------
    function renderBarProgramas(programstats) {
        barProgEl.innerHTML = '';
        barProgStats.innerHTML = '';

        if (!programstats || !programstats.length) {
            barProgEl.innerHTML = '<p class=\"local-mai-help-text\">No hay programas con datos para los filtros seleccionados.</p>';
            return;
        }

        if (typeof ApexCharts === 'undefined') {
            barProgEl.innerHTML = '<p class=\"local-mai-help-text\">ApexCharts no está disponible.</p>';
            return;
        }

        var categories    = [];
        var dataRetention = [];

        programstats.forEach(function(ps) {
            categories.push(ps.name);
            dataRetention.push(ps.retention);
        });

        var options = {
            chart: {
                type: 'bar',
                height: 260
            },
            series: [{
                name: 'Retención de usuarios activos (%)',
                data: dataRetention
            }],
            colors: ['#8C253E'],
            xaxis: {
                categories: categories,
                labels: {
                    rotate: -25
                }
            },
            yaxis: {
                max: 100,
                labels: {
                    formatter: function(val){ return val + '%'; }
                },
                title: {
                    text: 'Retención (%)'
                }
            },
            dataLabels: {
                enabled: true,
                formatter: function(val) {
                    return val + '%';
                }
            },
            tooltip: {
                y: {
                    formatter: function(val) { return val + '%'; }
                }
            }
        };

        barProgChart = new ApexCharts(barProgEl, options);
        barProgChart.render();

        var span = document.createElement('span');
        span.className = 'local-mai-inline-stat-pill';
        span.textContent = 'Programas con datos: ' + programstats.length;
        barProgStats.appendChild(span);
    }

    // --------- Render barra de promedio de actividades completadas ---------
    // Espera que cada objeto de programstats tenga ps.avgcompletion (0–100).
    function renderBarProgress(programstats, global) {
        barProgressEl.innerHTML = '';
        barProgressStats.innerHTML = '';

        if (!programstats || !programstats.length) {
            barProgressEl.innerHTML = '<p class=\"local-mai-help-text\">No hay datos de avance promedio para los programas seleccionados.</p>';
            return;
        }

        if (typeof ApexCharts === 'undefined') {
            barProgressEl.innerHTML = '<p class=\"local-mai-help-text\">ApexCharts no está disponible.</p>';
            return;
        }

        var categories   = [];
        var dataProgress = [];

        programstats.forEach(function(ps) {
            categories.push(ps.name);
            var avg = (ps.avgcompletion !== undefined && ps.avgcompletion !== null) ? ps.avgcompletion : 0;
            dataProgress.push(avg);
        });

        var options = {
            chart: {
                type: 'bar',
                height: 260
            },
            series: [{
                name: 'Promedio de actividades completadas (%)',
                data: dataProgress
            }],
            colors: ['#0ea5e9'],
            xaxis: {
                categories: categories,
                labels: {
                    rotate: -25
                }
            },
            yaxis: {
                max: 100,
                labels: {
                    formatter: function(val){ return val + '%'; }
                },
                title: {
                    text: 'Avance (%)'
                }
            },
            dataLabels: {
                enabled: true,
                formatter: function(val) {
                    return val + '%';
                }
            },
            tooltip: {
                y: {
                    formatter: function(val) { return val + '%'; }
                }
            }
        };

        barProgressChart = new ApexCharts(barProgressEl, options);
        barProgressChart.render();

        if (global && global.avgcompletion !== undefined && global.avgcompletion !== null) {
            var span = document.createElement('span');
            span.className = 'local-mai-inline-stat-pill';
            span.textContent = 'Promedio global de actividades completadas: ' + global.avgcompletion + '%';
            barProgressStats.appendChild(span);
        }

        var span2 = document.createElement('span');
        span2.className = 'local-mai-inline-stat-pill';
        span2.textContent = 'Programas analizados: ' + programstats.length;
        barProgressStats.appendChild(span2);
    }

    // --------- Render barra de retención por cuatrimestre ---------
    function renderBarTerms(termsstats, programid) {
        barTermsEl.innerHTML = '';
        barTermsStats.innerHTML = '';

        if (!programid || programid === '0') {
            barTermsEl.innerHTML = '<p class=\"local-mai-help-text\">Selecciona un programa para comparar la retención entre sus cuatrimestres.</p>';
            return;
        }

        if (!termsstats || !termsstats.length) {
            barTermsEl.innerHTML = '<p class=\"local-mai-help-text\">No hay datos por cuatrimestre para este programa.</p>';
            return;
        }

        if (typeof ApexCharts === 'undefined') {
            barTermsEl.innerHTML = '<p class=\"local-mai-help-text\">ApexCharts no está disponible.</p>';
            return;
        }

        var categories    = [];
        var dataRetention = [];

        termsstats.forEach(function(ts) {
            categories.push(ts.name);
            dataRetention.push(ts.retention);
        });

        var options = {
            chart: {
                type: 'bar',
                height: 260
            },
            series: [{
                name: 'Retención por cuatrimestre (%)',
                data: dataRetention
            }],
            colors: ['#FF7000'],
            xaxis: {
                categories: categories,
                labels: {
                    rotate: -25
                }
            },
            yaxis: {
                max: 100,
                labels: {
                    formatter: function(val){ return val + '%'; }
                },
                title: {
                    text: 'Retención (%)'
                }
            },
            dataLabels: {
                enabled: true,
                formatter: function(val) {
                    return val + '%';
                }
            },
            tooltip: {
                y: {
                    formatter: function(val) { return val + '%'; }
                }
            }
        };

        barTermsChart = new ApexCharts(barTermsEl, options);
        barTermsChart.render();

        var span = document.createElement('span');
        span.className = 'local-mai-inline-stat-pill';
        span.textContent = 'Cuatrimestres comparados: ' + termsstats.length;
        barTermsStats.appendChild(span);
    }

    // --------- Actualiza opciones de cuatrimestre ---------
    function updateFilterOptions(filtersData) {
        if (!filtersData) { return; }

        if (filtersData.terms && termSel) {
            var currentTerm = termSel.value;
            termSel.innerHTML = '';

            var optAll = document.createElement('option');
            optAll.value = '0';
            optAll.textContent = 'Todos los cuatrimestres';
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
    }

    // --------- Carga ligera de términos (filtros rápidos) ---------
    function loadTermsForProgram(programid) {
        if (!termSel) {
            return;
        }

        termSel.innerHTML = '';
        var optLoading = document.createElement('option');
        optLoading.value = '0';
        optLoading.textContent = 'Cargando cuatrimestres...';
        termSel.appendChild(optLoading);

        var params = new URLSearchParams();
        params.append('mode',      'filters');
        params.append('programid', programid || 0);
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
            updateFilterOptions(data.filters || {});
        }).catch(function(err) {
            console.error(err);
            termSel.innerHTML = '';
            var optAll = document.createElement('option');
            optAll.value = '0';
            optAll.textContent = 'Todos los cuatrimestres';
            termSel.appendChild(optAll);
        });
    }

    // --------- Carga principal del panel ---------
    function loadPanel(filters) {
        clearCharts();

        donutEl.innerHTML = '<div class=\"local-mai-inline-loading\"><div class=\"local-mai-inline-spinner\"></div><span>Cargando distribución de estudiantes...</span></div>';
        barProgEl.innerHTML = '<div class=\"local-mai-inline-loading\"><div class=\"local-mai-inline-spinner\"></div><span>Cargando retención por programa...</span></div>';
        barProgressEl.innerHTML = '<div class=\"local-mai-inline-loading\"><div class=\"local-mai-inline-spinner\"></div><span>Cargando promedio de actividades completadas...</span></div>';
        barTermsEl.innerHTML = '<div class=\"local-mai-inline-loading\"><div class=\"local-mai-inline-spinner\"></div><span>Cargando comparación por cuatrimestre...</span></div>';

        donutStats.innerHTML = '';
        barProgStats.innerHTML = '';
        barProgressStats.innerHTML = '';
        barTermsStats.innerHTML = '';

        var params = new URLSearchParams();
        params.append('mode',      'stats');
        params.append('programid', filters.programid || 0);
        params.append('termid',    filters.termid || 0);
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
            renderDonut(data.global || null);
            renderBarProgramas(data.programstats || []);
            renderBarProgress(data.programstats || [], data.global || null);
            renderBarTerms(data.termsstats || [], filters.programid);
            updateFilterOptions(data.filters || {});
        }).catch(function(err) {
            console.error(err);
            donutEl.innerHTML = '<p class=\"local-mai-help-text\" style=\"color:#b91c1c;\">Error al cargar los datos del panel.</p>';
            barProgEl.innerHTML = '';
            barProgressEl.innerHTML = '';
            barTermsEl.innerHTML = '';
        });
    }

    if (form) {
        form.addEventListener('submit', function(ev) {
            ev.preventDefault();
            loadPanel(getFilters());
        });
    }

    if (programSel) {
        programSel.addEventListener('change', function() {
            // Solo actualiza cuatrimestres; la recarga pesada se hace con el botón.
            loadTermsForProgram(programSel.value);
        });
    }

    // Carga inicial (sin filtros, todo el sitio)
    loadPanel(getFilters());
});
</script>
<?php
echo $OUTPUT->footer();
