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
// --- fin carga ApexCharts ---

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

echo html_writer::start_div('local-mai-filters mb-4 p-3 bg-light border rounded');

echo html_writer::start_tag('form', [
    'method' => 'get',
    'class'  => 'form-inline flex-wrap',
    'id'     => 'local-mai-filters-form'
]);

// Categoría.
echo html_writer::start_div('form-group mr-3 mb-2');
echo html_writer::label('Categoría', 'id_categoryid', ['class' => 'mr-2']);
echo html_writer::select($categoriesmenu, 'categoryid', $categoryid, null, [
    'id'    => 'id_categoryid',
    'class' => 'custom-select'
]);
echo html_writer::end_div();

// Curso.
echo html_writer::start_div('form-group mr-3 mb-2');
echo html_writer::label('Curso', 'id_courseid', ['class' => 'mr-2']);
echo html_writer::select($coursesmenu, 'courseid', $courseid, null, [
    'id'    => 'id_courseid',
    'class' => 'custom-select'
]);
echo html_writer::end_div();

// Cohorte.
echo html_writer::start_div('form-group mr-3 mb-2');
echo html_writer::label('Cohorte', 'id_cohortid', ['class' => 'mr-2']);
echo html_writer::select($cohortsmenu, 'cohortid', $cohortid, null, [
    'id'    => 'id_cohortid',
    'class' => 'custom-select'
]);
echo html_writer::end_div();

// Grupo.
echo html_writer::start_div('form-group mr-3 mb-2');
echo html_writer::label('Grupo', 'id_groupid', ['class' => 'mr-2']);
echo html_writer::select($groupsmenu, 'groupid', $groupid, null, [
    'id'    => 'id_groupid',
    'class' => 'custom-select'
]);
echo html_writer::end_div();

// Rol.
echo html_writer::start_div('form-group mr-3 mb-2');
echo html_writer::label('Rol', 'id_roleid', ['class' => 'mr-2']);
echo html_writer::select($rolesmenu, 'roleid', $roleid, null, [
    'id'    => 'id_roleid',
    'class' => 'custom-select'
]);
echo html_writer::end_div();

// Botón aplicar.
echo html_writer::start_div('form-group mb-2');
echo html_writer::empty_tag('input', [
    'type'  => 'submit',
    'value' => 'Aplicar filtros',
    'class' => 'btn btn-primary'
]);
echo html_writer::end_div();

echo html_writer::end_tag('form');
echo html_writer::end_div();

// ============================
// CSS
// ============================

$css = "
.local-mai-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 0.85rem;
    font-weight: 600;
    color: #fff;
}
.local-mai-badge.green { background-color: #28a745; }
.local-mai-badge.yellow { background-color: #ffc107; color: #333; }
.local-mai-badge.red { background-color: #dc3545; }

.local-mai-card {
    border-radius: 10px;
    border: 1px solid #e4e4e4;
    padding: 16px 18px;
    margin-bottom: 24px;
    background-color: #ffffff;
    box-shadow: 0 2px 6px rgba(0,0,0,0.03);
}
.local-mai-card h3 {
    margin-top: 0;
    margin-bottom: 12px;
    font-size: 1.1rem;
}
.local-mai-card table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
}
.local-mai-card table th,
.local-mai-card table td {
    padding: 6px 8px;
    border-bottom: 1px solid #f0f0f0;
}
.local-mai-card table th {
    background-color: #f8f9fa;
    font-weight: 600;
}
#local-mai-participation-loading {
    margin-bottom: 1rem;
}
.local-mai-gauges-row {
    display: flex;
    flex-wrap: nowrap;
    justify-content: center;
    align-items: flex-start;
    gap: 40px;
    overflow-x: auto;
}
.local-mai-gauge {
    flex: 0 0 260px;
    text-align: center;
}
.local-mai-gauge-title {
    font-weight: 600;
    margin-bottom: 4px;
}
    .local-mai-export-columns label {
    font-weight: 400;
    cursor: pointer;
}
.local-mai-export-buttons .btn {
    min-width: 150px;
}

";
echo html_writer::tag('style', $css);

// ============================
// Contenedores para loader, gauges y tablas
// ============================

echo html_writer::start_div('local-mai-participation-wrapper');

// Loader / mensajes.
echo html_writer::tag('div',
    'Selecciona un curso y haz clic en "Aplicar filtros" para ver la participación.',
    ['id' => 'local-mai-participation-loading', 'class' => 'alert alert-info']
);

// Tarjeta de gauges (semáforo).
echo html_writer::start_div('local-mai-card');
echo html_writer::tag('h3', 'Semáforo de participación');
// Tarjeta de exportación.
echo html_writer::start_div('local-mai-card');
echo html_writer::tag('h3', 'Exportar resultados');

echo html_writer::start_div('local-mai-export-columns mb-3');
echo html_writer::tag('p', 'Columnas adicionales a incluir en la exportación:');

echo html_writer::start_tag('label', ['class' => 'mr-3']);
echo html_writer::empty_tag('input', [
    'type'  => 'checkbox',
    'id'    => 'col_email',
    'checked' => 'checked'
]) . ' Correo';
echo html_writer::end_tag('label');

echo html_writer::start_tag('label', ['class' => 'mr-3']);
echo html_writer::empty_tag('input', [
    'type'  => 'checkbox',
    'id'    => 'col_cohort',
    'checked' => 'checked'
]) . ' Cohorte';
echo html_writer::end_tag('label');

echo html_writer::start_tag('label', ['class' => 'mr-3']);
echo html_writer::empty_tag('input', [
    'type'  => 'checkbox',
    'id'    => 'col_group',
    'checked' => 'checked'
]) . ' Grupo';
echo html_writer::end_tag('label');

echo html_writer::start_tag('label', ['class' => 'mr-3']);
echo html_writer::empty_tag('input', [
    'type'  => 'checkbox',
    'id'    => 'col_lastaccess',
    'checked' => 'checked'
]) . ' Último acceso';
echo html_writer::end_tag('label');

echo html_writer::start_tag('label', ['class' => 'mr-3']);
echo html_writer::empty_tag('input', [
    'type'  => 'checkbox',
    'id'    => 'col_enroltime',
    'checked' => 'checked'
]) . ' Fecha de matrícula';
echo html_writer::end_tag('label');

echo html_writer::end_div(); // export-columns

echo html_writer::start_div('local-mai-export-buttons');
echo html_writer::tag('button', 'Exportar a Excel', [
    'type'  => 'button',
    'class' => 'btn btn-success mr-2',
    'id'    => 'local-mai-export-excel'
]);
echo html_writer::tag('button', 'Exportar a PDF', [
    'type'  => 'button',
    'class' => 'btn btn-secondary mr-2',
    'id'    => 'local-mai-export-pdf'
]);
echo html_writer::tag('button', 'Exportar CSV', [
    'type'  => 'button',
    'class' => 'btn btn-outline-secondary',
    'id'    => 'local-mai-export-csv'
]);
echo html_writer::end_div();


echo html_writer::end_div(); // tarjeta export

echo html_writer::start_div('local-mai-gauges-row', ['id' => 'local-mai-participation-gauges']);
echo html_writer::end_div();
echo html_writer::end_div();

// Tablas de detalle.
echo html_writer::start_div('', ['id' => 'local-mai-participation-tables']);
echo html_writer::end_div();

echo html_writer::end_div(); // wrapper

// ============================
// JS inline (sin AMD)
// ============================

$ajaxurl    = (new moodle_url('/local/mai/participacion/ajax.php'))->out(false);
$exporturl  = (new moodle_url('/local/mai/participacion/export.php'))->out(false);
$sesskey    = sesskey();


?>
<script>
document.addEventListener('DOMContentLoaded', function() {
   var ajaxUrl       = '<?php echo $ajaxurl; ?>';
    var exportUrlBase = '<?php echo $exporturl; ?>';
    var sesskey       = '<?php echo $sesskey; ?>';

    var form      = document.getElementById('local-mai-filters-form');
    var loadingEl = document.getElementById('local-mai-participation-loading');
    var gaugesEl  = document.getElementById('local-mai-participation-gauges');
    var tablesEl  = document.getElementById('local-mai-participation-tables');

    var exportExcelBtn = document.getElementById('local-mai-export-excel');
    var exportPdfBtn   = document.getElementById('local-mai-export-pdf');
    var exportCsvBtn   = document.getElementById('local-mai-export-csv');


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
        params.append('format',    format);
        params.append('courseid',  filters.courseid);
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


    function renderGauges(counts, total) {
        gaugesEl.innerHTML = '';

        if (!total) {
            gaugesEl.innerHTML = '<p class="text-muted">No hay estudiantes que coincidan con los filtros seleccionados.</p>';
            return;
        }

        var keys   = ['active', 'inactive', 'never'];
        var titles = ['Activos', 'Inactivos', 'Nunca ingresaron'];
        var colors = ['#28a745', '#ffc107', '#dc3545'];

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
            gaugeWrapper.appendChild(chartDiv);

            gaugesEl.appendChild(gaugeWrapper);

            if (typeof ApexCharts !== 'undefined') {
                var options = {
                    chart: {
                        type: 'radialBar',
                        height: 180
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
                                size: '60%'
                            },
                            track: {
                                background: '#f0f0f0',
                                strokeWidth: '100%'
                            },
                            dataLabels: {
                                name: {
                                    fontSize: '14px',
                                    offsetY: 20
                                },
                                value: {
                                    fontSize: '18px',
                                    formatter: function(val) {
                                        return Math.round(val) + '%';
                                    },
                                    offsetY: -10
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

    function renderTables(data) {
        tablesEl.innerHTML = '';

        var activos   = data.activos || [];
        var inactivos = data.inactivos || [];
        var nunca     = data.nuncaingresaron || [];

        if (!activos.length && !inactivos.length && !nunca.length) {
            tablesEl.innerHTML = '<div class="alert alert-warning">No se encontraron estudiantes con los filtros seleccionados.</div>';
            return;
        }

        function buildTableCard(titleHtml, rows, headers, rowRenderer) {
            var html = '<div class="local-mai-card">';
            html += '<h3>' + titleHtml + '</h3>';
            if (!rows.length) {
                html += '<p class="text-muted">No se encontraron estudiantes.</p></div>';
                return html;
            }
            html += '<div class="table-responsive"><table class="table table-sm">';
            html += '<thead><tr>';
            headers.forEach(function(h) { html += '<th>' + h + '</th>'; });
            html += '</tr></thead><tbody>';
            rows.forEach(function(r) { html += rowRenderer(r); });
            html += '</tbody></table></div></div>';
            return html;
        }

        var cardActivos = buildTableCard(
            'Activos <span class="local-mai-badge green ml-2">●</span>',
            activos,
            ['Nombre completo', 'Correo', 'Completadas', 'Avance', 'Último acceso'],
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

        var cardInactivos = buildTableCard(
            'Inactivos <span class="local-mai-badge yellow ml-2">●</span>',
            inactivos,
            ['Nombre completo', 'Correo', 'Último acceso', 'Minutos (aprox.)', 'Clics'],
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

        var cardNunca = buildTableCard(
            'Nunca ingresaron <span class="local-mai-badge red ml-2">●</span>',
            nunca,
            ['Nombre completo', 'Correo', 'Cohorte', 'Grupo', 'Fecha de matrícula'],
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

        tablesEl.innerHTML = cardActivos + cardInactivos + cardNunca;
    }

function loadParticipation(filters) {
    // Si no hay curso seleccionado, mostramos advertencia clara.
    if (!filters.courseid || filters.courseid === '0') {
        loadingEl.className = 'alert alert-warning';
        loadingEl.textContent = 'Debes seleccionar un curso antes de aplicar filtros.';
        loadingEl.style.display = 'block';
        gaugesEl.innerHTML = '';
        tablesEl.innerHTML = '';
        return;
    }

    // Si sí hay curso, mostramos mensaje de carga normal.
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
    }).catch(function(err) {
        console.error(err);
        loadingEl.className = 'alert alert-danger';
        loadingEl.textContent = 'Ocurrió un error al cargar la información de participación.';
        loadingEl.style.display = 'block';
        gaugesEl.innerHTML = '';
        tablesEl.innerHTML = '';
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
