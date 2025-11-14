<?php
// local/mai/estructura.php
/**
 * Vistas clasificadas por estructura académica (frontend con AJAX).
 *
 * @package   local_mai
 */

require(__DIR__ . '/../../config.php');

require_login();

$systemcontext = context_system::instance();
require_capability('local/mai:viewreport', $systemcontext);

$pagetitle = 'Estructura académica - Monitoreo MAI';

$PAGE->set_url(new moodle_url('/local/mai/estructura.php'));
$PAGE->set_context($systemcontext);
$PAGE->set_pagelayout('report');
$PAGE->set_title($pagetitle);

echo $OUTPUT->header();

// ======================
// Cargamos categorías padre (programas) para el select inicial.
// ======================
global $DB;

// Programas = categorías padre.
$programcats = $DB->get_records('course_categories', ['parent' => 0], 'sortorder',
    'id, name, parent');

$programoptions = [0 => 'Todos los programas'];
foreach ($programcats as $pcat) {
    $programoptions[$pcat->id] = format_string($pcat->name);
}

// Cuatrimestres (se cargan por AJAX, aquí solo placeholder).
$termoptions = [0 => 'Todos los cuatrimestres'];

// Docentes (también por AJAX).
$teacheroptions = [0 => 'Todos los docentes'];

// Grupos (también por AJAX).
$groupoptions = [0 => 'Todos los grupos'];

// ======================
// CSS
// ======================
$css = "
.local-mai-page-title {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
}
.local-mai-page-subtitle {
    color: #6c757d;
    margin-bottom: 1.5rem;
}

.local-mai-filters {
    background: linear-gradient(135deg, #f8fafc, #eef4ff);
    border-radius: 12px;
    border: 1px solid #e0e7ff;
}
.local-mai-filters h3 {
    font-size: 1.1rem;
    font-weight: 600;
    margin-bottom: 0.75rem;
}
.local-mai-filters .form-group label {
    font-weight: 500;
    font-size: 0.85rem;
}

.local-mai-card {
    border-radius: 12px;
    border: 1px solid #e4e4e4;
    padding: 18px 20px;
    margin-bottom: 24px;
    background-color: #ffffff;
    box-shadow: 0 4px 14px rgba(15, 23, 42, 0.06);
}
.local-mai-card h3 {
    margin-top: 0;
    margin-bottom: 12px;
    font-size: 1.1rem;
    font-weight: 600;
}

.local-mai-stats-row {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
}
.local-mai-stat-box {
    border-radius: 10px;
    background: #f8fafc;
    padding: 10px 14px;
    border: 1px solid #e2e8f0;
}
.local-mai-stat-label {
    font-size: 0.8rem;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: .04em;
}
.local-mai-stat-value {
    font-size: 1.2rem;
    font-weight: 700;
    color: #0f172a;
    margin-top: 4px;
}

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
    background-color: #f8fafc;
    font-weight: 600;
    color: #475569;
}
.local-mai-program-highlight {
    background-color: #e0f2fe;
}
.local-mai-chart-container {
    min-height: 260px;
}

.local-mai-export-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
    justify-content: flex-start;
}
.local-mai-export-bar small {
    color: #64748b;
}

/* Layout especial de la vista general: 2 columnas */
.local-mai-general-row {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    align-items: stretch;
}
.local-mai-general-chart {
    flex: 2 1 60%;
}
.local-mai-general-stats {
    flex: 1 1 260px;
    display: grid;
    grid-template-columns: 1fr;
    grid-auto-rows: minmax(0, 1fr);
    gap: 12px;
}

@media (max-width: 992px) {
    .local-mai-general-row {
        flex-direction: column;
    }
    .local-mai-general-chart,
    .local-mai-general-stats {
        flex: 1 1 100%;
    }
}

@media (max-width: 768px) {
    .local-mai-stats-row {
        flex-direction: column;
    }
}
";
echo html_writer::tag('style', $css);

// ======================
// Encabezado
// ======================

echo html_writer::tag('div', 'Estructura académica', ['class' => 'local-mai-page-title']);
echo html_writer::tag('div',
    'Analiza la participación por programa académico y cuatrimestre, con métricas agregadas y filtros por docente y grupo.',
    ['class' => 'local-mai-page-subtitle']
);

// ======================
// Filtros
// ======================

echo html_writer::start_div('local-mai-filters mb-4 p-3');

echo html_writer::tag('h3', 'Filtros de estructura académica');

echo html_writer::start_tag('form', [
    'method' => 'get',
    'class'  => 'form-inline flex-wrap',
    'id'     => 'local-mai-estructura-filters'
]);

// Programa.
echo html_writer::start_div('form-group mr-3 mb-2');
echo html_writer::label('Programa académico', 'id_programid', ['class' => 'mr-2']);
echo html_writer::select($programoptions, 'programid', 0, null, [
    'id'    => 'id_programid',
    'class' => 'custom-select'
]);
echo html_writer::end_div();

// Cuatrimestre.
echo html_writer::start_div('form-group mr-3 mb-2');
echo html_writer::label('Cuatrimestre', 'id_termid', ['class' => 'mr-2']);
echo html_writer::select($termoptions, 'termid', 0, null, [
    'id'    => 'id_termid',
    'class' => 'custom-select'
]);
echo html_writer::end_div();

// Docente.
echo html_writer::start_div('form-group mr-3 mb-2');
echo html_writer::label('Docente', 'id_teacherid', ['class' => 'mr-2']);
echo html_writer::select($teacheroptions, 'teacherid', 0, null, [
    'id'    => 'id_teacherid',
    'class' => 'custom-select'
]);
echo html_writer::end_div();

// Grupo.
echo html_writer::start_div('form-group mr-3 mb-2');
echo html_writer::label('Grupo', 'id_groupid', ['class' => 'mr-2']);
echo html_writer::select($groupoptions, 'groupid', 0, null, [
    'id'    => 'id_groupid',
    'class' => 'custom-select'
]);
echo html_writer::end_div();

// Botón aplicar.
echo html_writer::start_div('form-group mb-2');
echo html_writer::empty_tag('input', [
    'type'  => 'submit',
    'value' => 'Aplicar filtro',
    'class' => 'btn btn-primary'
]);
echo html_writer::end_div();

echo html_writer::end_tag('form');
echo html_writer::end_div(); // filtros

// ======================
// Barra de exportación
// ======================

echo html_writer::start_div('local-mai-card mb-3');
echo html_writer::tag('h3', 'Exportar vista actual');

echo html_writer::start_div('local-mai-export-bar');
echo html_writer::tag('small', 'Descarga los datos agregados de la vista actual:');

echo html_writer::tag('button', 'Excel', [
    'type'  => 'button',
    'class' => 'btn btn-success btn-sm ml-2',
    'id'    => 'local-mai-estructura-export-excel'
]);
echo html_writer::tag('button', 'CSV', [
    'type'  => 'button',
    'class' => 'btn btn-outline-secondary btn-sm',
    'id'    => 'local-mai-estructura-export-csv'
]);
echo html_writer::tag('button', 'PDF', [
    'type'  => 'button',
    'class' => 'btn btn-secondary btn-sm',
    'id'    => 'local-mai-estructura-export-pdf'
]);

echo html_writer::end_div();
echo html_writer::end_div();

// ======================
// Vista general (gráfica + 4 cards)
// ======================

echo html_writer::start_div('local-mai-card', ['id' => 'local-mai-estructura-general']);
echo html_writer::tag('h3', 'Vista general de la plataforma');

echo html_writer::start_div('local-mai-general-row');
echo html_writer::tag('div', '', [
    'id'    => 'local-mai-estructura-general-chart',
    'class' => 'local-mai-chart-container local-mai-general-chart'
]);
echo html_writer::tag('div', 'Aplica los filtros para ver la participación.', [
    'id'    => 'local-mai-estructura-general-body',
    'class' => 'local-mai-general-stats'
]);
echo html_writer::end_div(); // row

echo html_writer::end_div(); // card

// ======================
// Vista por programa
// ======================

echo html_writer::start_div('local-mai-card', ['id' => 'local-mai-estructura-programas']);
echo html_writer::tag('h3', 'Vista por programa académico');
echo html_writer::tag('div', '', [
    'id' => 'local-mai-estructura-programas-table'
]);
echo html_writer::tag('div', '', [
    'id'    => 'local-mai-estructura-programas-chart',
    'class' => 'local-mai-chart-container'
]);
echo html_writer::end_div();

// ======================
// Vista por cuatrimestre
// ======================

echo html_writer::start_div('local-mai-card', ['id' => 'local-mai-estructura-term']);
echo html_writer::tag('h3', 'Vista por cuatrimestre');
echo html_writer::tag('div', '', [
    'id' => 'local-mai-estructura-term-summary'
]);
echo html_writer::tag('div', '', [
    'id'    => 'local-mai-estructura-term-chart',
    'class' => 'local-mai-chart-container'
]);
echo html_writer::tag('div', '', [
    'id' => 'local-mai-estructura-term-table'
]);
echo html_writer::end_div();

// ======================
// JS: AJAX + ApexCharts + export
// ======================

$ajaxurl   = (new moodle_url('/local/mai/estructura/ajax.php'))->out(false);
$exporturl = (new moodle_url('/local/mai/estructura/export.php'))->out(false);
$sesskey   = sesskey();

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
    var ajaxUrl       = '<?php echo $ajaxurl; ?>';
    var exportUrlBase = '<?php echo $exporturl; ?>';
    var sesskey       = '<?php echo $sesskey; ?>';

    var form          = document.getElementById('local-mai-estructura-filters');
    var programSel    = document.getElementById('id_programid');
    var termSel       = document.getElementById('id_termid');
    var teacherSel    = document.getElementById('id_teacherid');
    var groupSel      = document.getElementById('id_groupid');

    var generalBody   = document.getElementById('local-mai-estructura-general-body');
    var generalChart  = document.getElementById('local-mai-estructura-general-chart');
    var progTableEl   = document.getElementById('local-mai-estructura-programas-table');
    var progChartEl   = document.getElementById('local-mai-estructura-programas-chart');
    var termSummaryEl = document.getElementById('local-mai-estructura-term-summary');
    var termChartEl   = document.getElementById('local-mai-estructura-term-chart');
    var termTableEl   = document.getElementById('local-mai-estructura-term-table');

    var exportExcelBtn = document.getElementById('local-mai-estructura-export-excel');
    var exportCsvBtn   = document.getElementById('local-mai-estructura-export-csv');
    var exportPdfBtn   = document.getElementById('local-mai-estructura-export-pdf');

    var globalChart   = null;
    var progChart     = null;
    var termChart     = null;

    function getFilters() {
        return {
            programid: programSel ? programSel.value : '0',
            termid:    termSel ? termSel.value : '0',
            teacherid: teacherSel ? teacherSel.value : '0',
            groupid:   groupSel ? groupSel.value : '0'
        };
    }

    function clearCharts() {
        if (globalChart) { globalChart.destroy(); globalChart = null; }
        if (progChart)   { progChart.destroy();   progChart   = null; }
        if (termChart)   { termChart.destroy();   termChart   = null; }
    }

    function renderGeneral(global) {
        if (!global || !global.total) {
            generalBody.textContent = 'No hay datos disponibles para los filtros seleccionados.';
            if (globalChart) { globalChart.destroy(); globalChart = null; }
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
        frag.appendChild(statBox('Total de cursos', global.courses));
        frag.appendChild(statBox('Total de matrículas', global.total));
        frag.appendChild(statBox('Activos / Inactivos / Nunca', global.active + ' / ' + global.inactive + ' / ' + global.never));
        frag.appendChild(statBox('Tasa de retención global', global.retention + '%'));
        generalBody.appendChild(frag);

        // Gráfica donut global.
        generalChart.innerHTML = '';
        if (typeof ApexCharts !== 'undefined') {
            var options = {
                chart: {
                    type: 'donut',
                    height: 260
                },
                labels: ['Activos', 'Inactivos', 'Nunca ingresó'],
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

    function renderPrograms(programstats, selectedProgramId) {
        progTableEl.innerHTML = '';

        if (!programstats || !programstats.length) {
            progTableEl.innerHTML = '<p class=\"text-muted\">No se encontraron programas con cursos.</p>';
            if (progChart) { progChart.destroy(); progChart = null; }
            progChartEl.innerHTML = '';
            return;
        }

        var html = '<div class=\"table-responsive\"><table class=\"local-mai-table\">';
        html += '<thead><tr>' +
            '<th>Programa</th>' +
            '<th>Cursos</th>' +
            '<th>Activos</th>' +
            '<th>Inactivos</th>' +
            '<th>Nunca ingresó</th>' +
            '<th>Matrículas</th>' +
            '<th>Retención (%)</th>' +
        '</tr></thead><tbody>';

        var categories = [];
        var dataRetention = [];

        programstats.forEach(function(ps) {
            var cls = (selectedProgramId && String(selectedProgramId) === String(ps.id))
                ? 'local-mai-program-highlight'
                : '';

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

        html += '</tbody></table></div>';
        progTableEl.innerHTML = html;

        // Gráfica barras de retención por programa.
        progChartEl.innerHTML = '';
        if (typeof ApexCharts !== 'undefined' && categories.length) {
            var options = {
                chart: {
                    type: 'bar',
                    height: 260
                },
                series: [{
                    name: 'Retención (%)',
                    data: dataRetention
                }],
                xaxis: {
                    categories: categories
                },
                dataLabels: {
                    enabled: true,
                    formatter: function (val) { return val + '%'; }
                }
            };
            progChart = new ApexCharts(progChartEl, options);
            progChart.render();
        }
    }

    function renderTerm(view, termstats, termcourses, context) {
        termSummaryEl.innerHTML = '';
        termTableEl.innerHTML   = '';
        termChartEl.innerHTML   = '';
        if (termChart) { termChart.destroy(); termChart = null; }

        if (view !== 'term') {
            termSummaryEl.innerHTML = '<p class=\"text-muted\">Selecciona un programa y un cuatrimestre para ver el detalle.</p>';
            return;
        }

        var pname = context.programname || '(sin programa)';
        var tname = context.termname || '(sin cuatrimestre)';
        var header = '<p>Programa: <strong>' + pname + '</strong> &nbsp; | &nbsp; ' +
                     'Cuatrimestre: <strong>' + tname + '</strong>';
        if (context.teachername) {
            header += ' &nbsp; | &nbsp; Docente: <strong>' + context.teachername + '</strong>';
        }
        if (context.groupname) {
            header += ' &nbsp; | &nbsp; Grupo: <strong>' + context.groupname + '</strong>';
        }
        header += '</p>';

        if (!termcourses || !termcourses.length) {
            termSummaryEl.innerHTML = header +
                '<p class=\"text-muted\">No se encontraron cursos para los filtros seleccionados.</p>';
            return;
        }

        var total = termstats.total || 0;
        var summaryHtml = header + '<div class=\"local-mai-stats-row\">';

        function stat(label, value) {
            return '<div class=\"local-mai-stat-box\">' +
                    '<div class=\"local-mai-stat-label\">' + label + '</div>' +
                    '<div class=\"local-mai-stat-value\">' + value + '</div>' +
                   '</div>';
        }

        summaryHtml += stat('Cursos en el cuatrimestre', termstats.courses || 0);
        summaryHtml += stat('Matrículas (activos+inactivos+nunca)', total);
        summaryHtml += stat('Activos / Inactivos / Nunca ingresó',
            (termstats.active || 0) + ' / ' + (termstats.inactive || 0) + ' / ' + (termstats.never || 0));
        summaryHtml += stat('Tasa de retención en el cuatrimestre', (termstats.retention || 0) + '%');
        summaryHtml += '</div>';

        termSummaryEl.innerHTML = summaryHtml;

        var tableHtml = '<div class=\"table-responsive\"><table class=\"local-mai-table\">';
        tableHtml += '<thead><tr>' +
            '<th>Curso</th>' +
            '<th>Activos</th>' +
            '<th>Inactivos</th>' +
            '<th>Nunca ingresó</th>' +
            '<th>Matrículas</th>' +
            '<th>Retención (%)</th>' +
        '</tr></thead><tbody>';

        var categories = [];
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

        tableHtml += '</tbody></table></div>';
        termTableEl.innerHTML = tableHtml;

        if (typeof ApexCharts !== 'undefined' && categories.length) {
            var options = {
                chart: {
                    type: 'bar',
                    height: 260
                },
                series: [{
                    name: 'Retención (%)',
                    data: dataRetention
                }],
                xaxis: {
                    categories: categories
                },
                dataLabels: {
                    enabled: true,
                    formatter: function (val) { return val + '%'; }
                }
            };
            termChart = new ApexCharts(termChartEl, options);
            termChart.render();
        }
    }

    function updateFilterOptions(filtersData) {
        if (!filtersData) { return; }

        // Cuatrimestres dinámicos.
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

        // Docentes dinámicos.
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

        // Grupos dinámicos.
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

    function loadEstructura(filters) {
        clearCharts();

        generalBody.textContent = 'Cargando información...';
        progTableEl.innerHTML   = '<p class=\"text-muted\">Cargando programas...</p>';
        termSummaryEl.innerHTML = '<p class=\"text-muted\">Cargando cuatrimestre...</p>';
        termTableEl.innerHTML   = '';

        var params = new URLSearchParams();
        params.append('programid', filters.programid || 0);
        params.append('termid',    filters.termid || 0);
        params.append('teacherid', filters.teacherid || 0);
        params.append('groupid',   filters.groupid || 0);
        params.append('sesskey',   sesskey);

        fetch(ajaxUrl + '?' + params.toString(), {
            credentials: 'same-origin'
        }).then(function(response) {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.json();
        }).then(function(data) {
            renderGeneral(data.global || null);
            renderPrograms(data.programstats || [], filters.programid);
            renderTerm(data.view, data.termstats || {}, data.termcourses || [], data.context || {});
            updateFilterOptions(data.filters || {});
        }).catch(function(err) {
            console.error(err);
            generalBody.textContent = 'Ocurrió un error al cargar la información.';
            progTableEl.innerHTML   = '';
            termSummaryEl.innerHTML = '';
            termTableEl.innerHTML   = '';
        });
    }

    function buildExportUrl(format) {
        var filters = getFilters();
        var params = new URLSearchParams();
        params.append('format',    format);
        params.append('programid', filters.programid || 0);
        params.append('termid',    filters.termid || 0);
        params.append('teacherid', filters.teacherid || 0);
        params.append('groupid',   filters.groupid || 0);
        params.append('sesskey',   sesskey);
        return exportUrlBase + '?' + params.toString();
    }

    if (form) {
        form.addEventListener('submit', function(ev) {
            ev.preventDefault();
            loadEstructura(getFilters());
        });
    }

    // Cuando se cambia el programa, reiniciamos otros filtros y recargamos.
    if (programSel) {
        programSel.addEventListener('change', function() {
            if (termSel)    { termSel.value = '0'; }
            if (teacherSel) { teacherSel.value = '0'; }
            if (groupSel)   { groupSel.value = '0'; }
            loadEstructura(getFilters());
        });
    }

    if (exportExcelBtn) {
        exportExcelBtn.addEventListener('click', function() {
            var url = buildExportUrl('xlsx');
            if (url) { window.location.href = url; }
        });
    }
    if (exportCsvBtn) {
        exportCsvBtn.addEventListener('click', function() {
            var url = buildExportUrl('csv');
            if (url) { window.location.href = url; }
        });
    }
    if (exportPdfBtn) {
        exportPdfBtn.addEventListener('click', function() {
            var url = buildExportUrl('pdf');
            if (url) { window.location.href = url; }
        });
    }

    // Carga inicial.
    loadEstructura(getFilters());
});
</script>
<?php

echo $OUTPUT->footer();
