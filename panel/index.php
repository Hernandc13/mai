<?php
// local/mai/panel/index.php
/**
 * Panel de control con gráficos interactivos (punto 4 MAI).
 *
 * @package   local_mai
 */

require(__DIR__ . '/../../../config.php');

require_login();

$systemcontext = context_system::instance();
require_capability('local/mai:viewreport', $systemcontext);

$pagetitle = 'Panel de control - Monitoreo MAI';

$PAGE->set_url(new moodle_url('/local/mai/panel/index.php'));
$PAGE->set_context($systemcontext);
$PAGE->set_pagelayout('report');
$PAGE->set_title($pagetitle);

echo $OUTPUT->header();

// ======================
// Filtros base (programa / cuatrimestre)
// ======================
global $DB;

// Programas = categorías padre.
$programcats = $DB->get_records('course_categories', ['parent' => 0], 'sortorder',
    'id, name, parent');

$programoptions = [0 => 'Todos los programas'];
foreach ($programcats as $pcat) {
    $programoptions[$pcat->id] = format_string($pcat->name);
}

// Cuatrimestres (dinámicos).
$termoptions = [0 => 'Todos los cuatrimestres'];

// ======================
// CSS
// ======================
$css = "
.local-mai-panel-title {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
}
.local-mai-panel-subtitle {
    color: #6c757d;
    margin-bottom: 1.5rem;
    font-size: 0.95rem;
}

.local-mai-panel-filters {
    background: linear-gradient(135deg, #f8fafc, #eef4ff);
    border-radius: 12px;
    border: 1px solid #e0e7ff;
}
.local-mai-panel-filters h3 {
    font-size: 1.05rem;
    font-weight: 600;
    margin-bottom: 0.75rem;
}
.local-mai-panel-filters .form-group label {
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
    font-size: 1.05rem;
    font-weight: 600;
}

.local-mai-charts-row {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
}
.local-mai-chart-box {
    flex: 1 1 320px;
}

.local-mai-chart-container {
    min-height: 260px;
}

/* Stats pequeños debajo de las gráficas */
.local-mai-inline-stats {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 8px;
    font-size: 0.8rem;
    color: #64748b;
}
.local-mai-inline-stat-pill {
    padding: 4px 10px;
    border-radius: 999px;
    background-color: #f1f5f9;
}

/* Mensaje ayuda comparación */
.local-mai-help-text {
    font-size: 0.8rem;
    color: #64748b;
    margin-bottom: 10px;
}

/* Responsive */
@media (max-width: 768px) {
    .local-mai-charts-row {
        flex-direction: column;
    }
}
";
echo html_writer::tag('style', $css);

// ======================
// Encabezado
// ======================
echo html_writer::tag('div', 'Panel de control', ['class' => 'local-mai-panel-title']);
echo html_writer::tag('div',
    'Gráficos interactivos de participación, retención y comparación entre cuatrimestres.',
    ['class' => 'local-mai-panel-subtitle']
);

// ======================
// Filtros (programa / cuatrimestre)
// ======================
echo html_writer::start_div('local-mai-panel-filters mb-4 p-3');

echo html_writer::tag('h3', 'Filtros del panel de control');

echo html_writer::start_tag('form', [
    'method' => 'get',
    'class'  => 'form-inline flex-wrap',
    'id'     => 'local-mai-panel-filters'
]);

// Programa.
echo html_writer::start_div('form-group mr-3 mb-2');
echo html_writer::label('Programa académico', 'id_panel_programid', ['class' => 'mr-2']);
echo html_writer::select($programoptions, 'programid', 0, null, [
    'id'    => 'id_panel_programid',
    'class' => 'custom-select'
]);
echo html_writer::end_div();

// Cuatrimestre (para filtrar global, pero la comparación usa todos los del programa).
echo html_writer::start_div('form-group mr-3 mb-2');
echo html_writer::label('Cuatrimestre', 'id_panel_termid', ['class' => 'mr-2']);
echo html_writer::select($termoptions, 'termid', 0, null, [
    'id'    => 'id_panel_termid',
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
// Card 1: Donut + barras por programa
// ======================
echo html_writer::start_div('local-mai-card');
echo html_writer::tag('h3', 'Distribución de participación y retención por programa');

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

// Barra por programa
echo html_writer::start_div('local-mai-chart-box');
echo html_writer::tag('div', '', [
    'id'    => 'local-mai-panel-bar-programas',
    'class' => 'local-mai-chart-container'
]);
echo html_writer::start_div('local-mai-inline-stats', ['id' => 'local-mai-panel-bar-programas-stats']);
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::end_div(); // charts-row
echo html_writer::end_div(); // card

// ======================
// Card 2: Comparación entre cuatrimestres (periodos)
// ======================
echo html_writer::start_div('local-mai-card');
echo html_writer::tag('h3', 'Comparación entre cuatrimestres del programa seleccionado');
echo html_writer::tag('div',
    'Selecciona un programa en los filtros superiores para comparar la tasa de retención por cuatrimestre.',
    ['class' => 'local-mai-help-text']
);

echo html_writer::tag('div', '', [
    'id'    => 'local-mai-panel-bar-terms',
    'class' => 'local-mai-chart-container'
]);
echo html_writer::start_div('local-mai-inline-stats', ['id' => 'local-mai-panel-bar-terms-stats']);
echo html_writer::end_div();

echo html_writer::end_div(); // card

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

    var donutEl      = document.getElementById('local-mai-panel-donut');
    var donutStats   = document.getElementById('local-mai-panel-donut-stats');
    var barProgEl    = document.getElementById('local-mai-panel-bar-programas');
    var barProgStats = document.getElementById('local-mai-panel-bar-programas-stats');
    var barTermsEl   = document.getElementById('local-mai-panel-bar-terms');
    var barTermsStats= document.getElementById('local-mai-panel-bar-terms-stats');

    var donutChart    = null;
    var barProgChart  = null;
    var barTermsChart = null;

    function getFilters() {
        return {
            programid: programSel ? programSel.value : '0',
            termid:    termSel ? termSel.value : '0'
        };
    }

    function clearCharts() {
        if (donutChart)    { donutChart.destroy(); donutChart = null; }
        if (barProgChart)  { barProgChart.destroy(); barProgChart = null; }
        if (barTermsChart) { barTermsChart.destroy(); barTermsChart = null; }
    }

    function renderDonut(global) {
        donutEl.innerHTML = '';
        donutStats.innerHTML = '';

        if (!global || !global.total) {
            donutEl.innerHTML = '<p class=\"text-muted\">No hay datos para la distribución actual.</p>';
            return;
        }

        if (typeof ApexCharts === 'undefined') {
            donutEl.innerHTML = '<p class=\"text-muted\">ApexCharts no está disponible.</p>';
            return;
        }

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

        donutChart = new ApexCharts(donutEl, options);
        donutChart.render();

        var pills = [
            'Cursos: ' + global.courses,
            'Matrículas: ' + global.total,
            'Retención: ' + global.retention + '%'
        ];
        pills.forEach(function(txt) {
            var span = document.createElement('span');
            span.className = 'local-mai-inline-stat-pill';
            span.textContent = txt;
            donutStats.appendChild(span);
        });
    }

    function renderBarProgramas(programstats) {
        barProgEl.innerHTML = '';
        barProgStats.innerHTML = '';

        if (!programstats || !programstats.length) {
            barProgEl.innerHTML = '<p class=\"text-muted\">No hay programas con datos para los filtros seleccionados.</p>';
            return;
        }

        if (typeof ApexCharts === 'undefined') {
            barProgEl.innerHTML = '<p class=\"text-muted\">ApexCharts no está disponible.</p>';
            return;
        }

        var categories = [];
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
                name: 'Retención (%)',
                data: dataRetention
            }],
            xaxis: {
                categories: categories
            },
            dataLabels: {
                enabled: true,
                formatter: function(val) {
                    return val + '%';
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

    function renderBarTerms(termsstats, programid) {
        barTermsEl.innerHTML = '';
        barTermsStats.innerHTML = '';

        if (!programid || programid === '0') {
            barTermsEl.innerHTML = '<p class=\"text-muted\">Selecciona un programa para comparar sus cuatrimestres.</p>';
            return;
        }

        if (!termsstats || !termsstats.length) {
            barTermsEl.innerHTML = '<p class=\"text-muted\">No hay datos por cuatrimestre para este programa.</p>';
            return;
        }

        if (typeof ApexCharts === 'undefined') {
            barTermsEl.innerHTML = '<p class=\"text-muted\">ApexCharts no está disponible.</p>';
            return;
        }

        var categories = [];
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
                name: 'Retención (%) por cuatrimestre',
                data: dataRetention
            }],
            xaxis: {
                categories: categories
            },
            dataLabels: {
                enabled: true,
                formatter: function(val) {
                    return val + '%';
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

    function loadPanel(filters) {
        clearCharts();

        donutEl.innerHTML   = '<p class=\"text-muted\">Cargando distribución...</p>';
        barProgEl.innerHTML = '<p class=\"text-muted\">Cargando retención por programa...</p>';
        barTermsEl.innerHTML= '<p class=\"text-muted\">Cargando comparación por cuatrimestre...</p>';
        donutStats.innerHTML = '';
        barProgStats.innerHTML = '';
        barTermsStats.innerHTML = '';

        var params = new URLSearchParams();
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
            renderBarTerms(data.termsstats || [], filters.programid);
            updateFilterOptions(data.filters || {});
        }).catch(function(err) {
            console.error(err);
            donutEl.innerHTML   = '<p class=\"text-danger\">Error al cargar los datos del panel.</p>';
            barProgEl.innerHTML = '';
            barTermsEl.innerHTML= '';
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
            if (termSel) {
                termSel.value = '0';
            }
            loadPanel(getFilters());
        });
    }

    loadPanel(getFilters());
});
</script>
<?php
echo $OUTPUT->footer();
