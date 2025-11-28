<?php
// local/mai/notificaciones/index.php
/**
 * Configuración de reglas de envío automatizado de reportes y alertas inteligentes MAI
 * por programa y cuatrimestre (local_mai_notif_rules).
 *
 * @package   local_mai
 */

require(__DIR__ . '/../../../config.php');
require_login();

global $CFG, $DB, $PAGE, $OUTPUT, $USER;

$systemcontext = context_system::instance();
require_capability('local/mai:viewreport', $systemcontext);

require_once($CFG->libdir . '/moodlelib.php');
require_once($CFG->libdir . '/weblib.php');

// ---------------------------------------------------------------------
// Manejo de acciones (guardar / eliminar / seleccionar regla).
// ---------------------------------------------------------------------
$ruleid = optional_param('ruleid', 0, PARAM_INT);     // Regla actual (0 = nueva).
$op     = optional_param('op', '', PARAM_ALPHA);      // save | delete
$open   = optional_param('open', 0, PARAM_BOOL);      // 1 = abrir modal

$baseurl = new moodle_url('/local/mai/notificaciones/index.php');

if ($op === 'delete' && $ruleid > 0 && confirm_sesskey()) {
    // Borrado de regla.
    $DB->delete_records('local_mai_notif_rules', ['id' => $ruleid]);
    redirect($baseurl, 'Regla eliminada correctamente.', 2);
}

// Guardar regla (crear o actualizar).
if ($op === 'save' && confirm_sesskey()) {
    $data = new stdClass();

    $data->id = $ruleid > 0 ? $ruleid : null;

    $data->name    = required_param('name', PARAM_TEXT);
    $data->enabled = optional_param('enabled', 0, PARAM_BOOL) ? 1 : 0;

    $data->programid = optional_param('programid', 0, PARAM_INT);
    $data->termid    = optional_param('termid', 0, PARAM_INT);

    // Cursos visibles seleccionados.
// Cursos visibles seleccionados (sin duplicados).
$monitored_arr = optional_param_array('monitored_courses', [], PARAM_INT);
$monitored_arr = array_values(array_unique(array_filter(array_map('intval', $monitored_arr))));
$data->monitored_courses = implode(',', $monitored_arr);


    // Reportes.
    $data->reportenabled     = optional_param('reportenabled', 0, PARAM_BOOL) ? 1 : 0;
    $data->report_frequency  = optional_param('report_frequency', 'weekly', PARAM_ALPHA);
    $data->report_format     = optional_param('report_format', 'pdf', PARAM_ALPHA);
    $data->report_recipients = optional_param('report_recipients', '', PARAM_RAW_TRIMMED);
    $data->report_template   = optional_param('report_template', '', PARAM_RAW);

    // Alertas.
    $data->alertsenabled          = optional_param('alertsenabled', 0, PARAM_BOOL) ? 1 : 0;
    $data->alert_days_inactive    = optional_param('alert_days_inactive', 7, PARAM_INT);
    $data->alert_group_inactivity = optional_param('alert_group_inactivity', 50, PARAM_INT);
    $data->alert_recipients       = optional_param('alert_recipients', '', PARAM_RAW_TRIMMED);
    $data->alert_student_message  = optional_param('alert_student_message', '', PARAM_RAW);
    $data->alert_coord_message    = optional_param('alert_coord_message', '', PARAM_RAW);

    // Canales de alerta (checkboxes alert_channels[]).
    $alert_channels_arr = [];
    if (!empty($_POST['alert_channels']) && is_array($_POST['alert_channels'])) {
        foreach ($_POST['alert_channels'] as $ch) {
            $ch = clean_param($ch, PARAM_ALPHA); // email | internal
            if ($ch !== '') {
                $alert_channels_arr[] = $ch;
            }
        }
    }
    $data->alert_channels = implode(',', $alert_channels_arr);

    // Campos de control de ejecución (solo cuando es nuevo).
    if (empty($data->id)) {
        $data->last_report_sent     = 0;
        $data->last_alerts_checked  = 0;
        $data->timecreated          = time();
        $data->timemodified         = time();
        $data->id = $DB->insert_record('local_mai_notif_rules', $data);
    } else {
        $data->timemodified = time();
        $DB->update_record('local_mai_notif_rules', $data);
    }

    // Al guardar, regresamos a la lista sin abrir modal.
    redirect(new moodle_url($baseurl, ['ruleid' => $data->id]), 'Regla guardada correctamente.', 2);
}

// ---------------------------------------------------------------------
// Carga de datos para la vista.
// ---------------------------------------------------------------------

$pagetitle = 'Programación de reportes y alertas MAI';

$PAGE->set_url($baseurl);
$PAGE->set_context($systemcontext);
$PAGE->set_pagelayout('report');
$PAGE->set_title($pagetitle);
$PAGE->set_heading($pagetitle);

$PAGE->requires->jquery();

// 1) Cargar todas las reglas.
$rules = $DB->get_records('local_mai_notif_rules', null, 'programid ASC, termid ASC, name ASC');

// 2) Categorías (programas y cuatrimestres).
$cats     = $DB->get_records('course_categories', null, 'parent ASC, name ASC', 'id, name, parent, visible');
$catsbyid = $cats;

// 3) Cursos visibles (no ocultos).
$courses = $DB->get_records_select(
    'course',
    'id <> 1 AND visible = 1',
    null,
    'fullname ASC',
    'id, fullname, shortname, category'
);

// 4) Estructuras para JS: cuatrimestres por programa y cursos por cuatrimestre.
$termsbyprogram = [];
foreach ($cats as $cat) {
    if ((int)$cat->parent !== 0) {
        $termsbyprogram[$cat->parent][] = [
            'id'   => (int)$cat->id,
            'name' => $cat->name
        ];
    }
}

$coursesbyterm   = [];
$allcourseslist  = [];
foreach ($courses as $c) {
    $allcourseslist[] = [
        'id'       => (int)$c->id,
        'name'     => $c->fullname,
        'category' => (int)$c->category
    ];
    if (!isset($coursesbyterm[$c->category])) {
        $coursesbyterm[$c->category] = [];
    }
    $coursesbyterm[$c->category][] = [
        'id'   => (int)$c->id,
        'name' => $c->fullname
    ];
}

// Mapas rápidos para contar cursos monitoreados.
$coursecountbyterm = [];
foreach ($coursesbyterm as $termid => $list) {
    $coursecountbyterm[$termid] = count($list);
}

$coursecountbyprogram = [];
foreach ($termsbyprogram as $pid => $terms) {
    $total = 0;
    foreach ($terms as $t) {
        $tid = (int)$t['id'];
        if (isset($coursecountbyterm[$tid])) {
            $total += $coursecountbyterm[$tid];
        }
    }
    $coursecountbyprogram[$pid] = $total;
}
$allcoursescount = count($allcourseslist);

// 5) Config global (defaults de textos).
$config = get_config('local_mai');

$default_report_template = $config->report_template ?? '
<div style="font-family:system-ui,Arial,sans-serif;max-width:620px;margin:0 auto;">
  <h2 style="margin:0 0 8px;color:#111827;font-size:18px;">Reporte automático MAI</h2>
  <p style="margin:0 0 10px;color:#4b5563;font-size:13px;">Resumen de participación del periodo.</p>
  <ul style="margin:0 0 12px 18px;padding:0;font-size:13px;color:#111827;">
    <li><strong>Estudiantes activos:</strong> {{active}}</li>
    <li><strong>Estudiantes inactivos:</strong> {{inactive}}</li>
    <li><strong>Cursos monitoreados:</strong> {{courses}}</li>
  </ul>
  <p style="margin:0;font-size:11px;color:#9ca3af;">El detalle por curso se adjunta en el reporte PDF/Excel.</p>
</div>';

$default_student_msg = $config->alert_student_message ??
    '{{fullname}}, te invitamos a continuar tus actividades en la plataforma. Tu progreso es importante.';

$default_coord_msg = $config->alert_coord_message ??
    'Se ha detectado un grupo con alta inactividad. Te sugerimos revisar las actividades y contactar a los estudiantes.';

// 6) Determinar la regla actual (para el formulario del modal).
$current = new stdClass();
if ($ruleid > 0 && isset($rules[$ruleid])) {
    $current = $rules[$ruleid];
} else {
    // Nueva regla: defaults.
    $current->id                     = 0;
    $current->name                   = '';
    $current->enabled                = 1;
    $current->programid              = 0;
    $current->termid                 = 0;
    $current->monitored_courses      = '';
    $current->reportenabled          = 1;
    $current->report_frequency       = 'weekly';
    $current->report_format          = 'pdf';
    $current->report_recipients      = '';
    $current->report_template        = $default_report_template;
    $current->alertsenabled          = 1;
    $current->alert_days_inactive    = 7;
    $current->alert_group_inactivity = 50;
    $current->alert_recipients       = '';
    $current->alert_student_message  = $default_student_msg;
    $current->alert_coord_message    = $default_coord_msg;
    $current->alert_channels         = 'internal';
}

// Arrays para selects.
$current_monitored_courses = [];
if (!empty($current->monitored_courses)) {
    $current_monitored_courses = array_filter(
        array_map('intval', explode(',', $current->monitored_courses))
    );
}

// Helper para nombre de programa / cuatrimestre.
function local_mai_notif_cat_name($catsbyid, $id) {
    if (empty($id)) {
        return 'Todos';
    }
    return isset($catsbyid[$id]) ? $catsbyid[$id]->name : ('ID ' . $id);
}

echo $OUTPUT->header();

// FontAwesome
echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />';

// DataTables (solo para la tabla de reglas).
echo '<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css" />';
echo '<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>';

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
?>
<style>
:root {
    --mai-primary: #8C253E;
    --mai-accent: #FF7000;
    --mai-dark: #111827;
    --mai-gray: #6B7280;
    --mai-light: #F3F4F6;
    --mai-border-soft: #E5E7EB;
}

/* Wrapper general */
.local-mai-notif-wrapper {
    max-width: 1100px;
    margin: 0 auto;
    padding-bottom: 32px;
    font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.local-mai-notif-intro {
    font-size: 0.9rem;
    color: var(--mai-gray);
    margin-bottom: 18px;
}

/* Listado de reglas */
.local-mai-rules-list {
    margin-bottom: 22px;
    border-radius: 14px;
    border: 1px solid #e5e7eb;
    background: #ffffff;
    box-shadow: 0 12px 24px rgba(15, 23, 42, 0.06);
    padding: 14px 18px 10px;
}

.local-mai-rules-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-bottom: 10px;
}

.local-mai-rules-header-title {
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--mai-dark);
}

.local-mai-rules-note {
    font-size: 0.78rem;
    color: var(--mai-gray);
}

.local-mai-rules-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.82rem;
    margin-top: 6px;
}

.local-mai-rules-table th,
.local-mai-rules-table td {
    padding: 6px 8px;
    border-bottom: 1px solid #e5e7eb;
}

.local-mai-rules-table th {
    text-align: left;
    font-weight: 600;
    color: var(--mai-dark);
    background: #f9fafb;
}

.local-mai-rules-table td.local-mai-rules-numcourses {
    text-align: center;
}

/* Estilos DataTables básicos */
.dataTables_filter {
    margin-bottom: 6px;
    font-size: 0.8rem;
}
.dataTables_filter input {
    padding: 4px 6px;
    border-radius: 999px;
    border: 1px solid #d1d5db;
    font-size: 0.8rem;
}
.dataTables_info {
    font-size: 0.75rem;
    color: var(--mai-gray);
}
.dataTables_paginate {
    font-size: 0.8rem;
}

/* Badges */
.local-mai-badge {
    display: inline-flex;
    align-items: center;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 0.7rem;
    font-weight: 600;
}
.local-mai-badge-on {
    background: #ecfdf3;
    color: #15803d;
}
.local-mai-badge-off {
    background: #fef2f2;
    color: #b91c1c;
}

.local-mai-rule-actions a {
    font-size: 0.8rem;
    text-decoration: none;
    margin-right: 6px;
}

/* Grid dentro del modal */
.local-mai-notif-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 20px;
}

/* Cards */
.local-mai-card {
    border-radius: 18px;
    border: 1px solid #e5e7eb;
    background: #ffffff;
    box-shadow: 0 16px 30px rgba(15, 23, 42, 0.08);
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

/* Header horizontal de cada card */
.local-mai-card-header {
    padding: 14px 18px;
    background: linear-gradient(135deg, var(--mai-primary), var(--mai-accent));
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.local-mai-card-header-main {
    display: flex;
    align-items: flex-start;
    gap: 12px;
}

/* Icono redondo */
.local-mai-card-icon {
    flex: 0 0 36px;
    width: 36px;
    height: 36px;
    border-radius: 999px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    background: rgba(255, 255, 255, 0.15);
}

.local-mai-card-title {
    margin: 0;
    font-size: 1.02rem;
    font-weight: 700;
}

.local-mai-card-subtitle {
    font-size: 0.8rem;
    opacity: 0.9;
    margin-top: 2px;
}

/* Pastilla "Bloque X de 3" */
.local-mai-card-header-step {
    font-size: 0.75rem;
    padding: 4px 10px;
    border-radius: 999px;
    border: 1px solid rgba(255,255,255,0.75);
    background: rgba(255,255,255,0.08);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

/* Body de card */
.local-mai-card-body {
    flex: 1;
    padding: 18px 22px;
    background: #ffffff;
}

/* Grid de campos dentro de cada card */
.local-mai-fields-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px 24px;
}

.local-mai-field-group {
    margin-bottom: 0;
}

.local-mai-field-full {
    grid-column: 1 / -1;
}

.local-mai-field-label {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--mai-dark);
    margin-bottom: 6px;
}

.local-mai-field-help {
    font-size: 0.78rem;
    color: var(--mai-gray);
    margin-top: 3px;
}

/* Inputs */
.local-mai-card-body input[type="text"],
.local-mai-card-body input[type="number"],
.local-mai-card-body textarea,
.local-mai-card-body select {
    width: 100%;
    padding: 9px 11px;
    border-radius: 10px;
    border: 1px solid #d1d5db;
    background: var(--mai-light);
    font-size: 0.9rem;
    transition: 0.22s ease;
}

.local-mai-card-body input[type="text"]:focus,
.local-mai-card-body input[type="number"]:focus,
.local-mai-card-body textarea:focus,
.local-mai-card-body select:focus {
    border-color: var(--mai-primary);
    background: #ffffff;
    outline: none;
    box-shadow: 0 0 0 3px rgba(140, 37, 62, 0.25);
}

/* Chips para radios / checkboxes */
.local-mai-inline {
    display: flex;
    flex-wrap: wrap;
    gap: 8px 12px;
    align-items: center;
}

.local-mai-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.8rem;
    padding: 6px 10px;
    border-radius: 999px;
    border: 1px solid #d1d5db;
    background: #f9fafb;
    color: var(--mai-dark);
    cursor: pointer;
    transition: 0.2s ease;
}

.local-mai-chip input {
    transform: scale(1.1);
}

.local-mai-chip:hover {
    background: #e5e7eb;
}

/* Card Acciones (footer) */
.local-mai-card-footer {
    border-radius: 18px;
    border: 1px solid #e5e7eb;
    background: #ffffff;
    box-shadow: 0 16px 30px rgba(15, 23, 42, 0.08);
    margin-top: 20px;
}

.local-mai-card-footer-header {
    padding: 10px 18px;
    border-bottom: 1px solid #e5e7eb;
    background: linear-gradient(90deg, #fdf2f7, #fff7ed);
}

.local-mai-card-footer-title {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--mai-primary);
}

.local-mai-card-footer-body {
    padding: 14px 18px 16px 18px;
}

/* Acciones (botones) */
.local-mai-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

/* Botones */
.btn-primary {
    background: var(--mai-primary);
    border: none !important;
    padding: 9px 15px;
    border-radius: 999px;
    font-weight: 600;
    font-size: 0.9rem;
    color: #ffffff !important;
    transition: 0.2s ease;
}

.btn-primary:hover,
.btn-primary:focus {
    background: #6d1c31;
    transform: translateY(-1px);
    color: #ffffff !important;
}

.btn-secondary {
    background: var(--mai-accent);
    border: none !important;
    padding: 9px 15px;
    border-radius: 999px;
    font-weight: 600;
    font-size: 0.9rem;
    color: #111827 !important;
    transition: 0.2s ease;
}

.btn-secondary:hover,
.btn-secondary:focus {
    background: #e66000;
    transform: translateY(-1px);
    color: #111827 !important;
}

.local-mai-btn-small {
    font-size: 0.8rem;
    padding: 6px 12px;
}

/* Misma altura para los editores HTML */
.tox.tox-tinymce,
.editor_atto {
    min-height: 260px;
}

.tox .tox-edit-area__iframe {
    min-height: 220px;
}

/* Botón regresar al dashboard MAI */
.local-mai-panel-back {
    display: flex;
    justify-content: flex-start;
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
    color: var(--mai-gray);
    font-size: 0.8rem;
    font-weight: 500;
    text-decoration: none;
    cursor: pointer;
    transition: border-color .15s ease, box-shadow .15s ease, transform .1s ease, color .15s ease;
}

.local-mai-btn-back:hover,
.local-mai-btn-back:focus {
    border-color: rgba(140,37,62,0.35);
    color: var(--mai-primary);
    box-shadow: 0 8px 18px rgba(15,23,42,0.10);
    transform: translateY(-1px);
}

.local-mai-btn-back-icon {
    font-size: 0.85rem;
}

/* MODAL pantalla completa */
.mai-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.55);
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 12px;
}

.mai-modal-visible {
    display: flex;
}

.mai-modal-dialog {
    width: min(1100px, 100%);
    height: 92vh;
    background: #f9fafb;
    border-radius: 18px;
    box-shadow: 0 22px 40px rgba(15, 23, 42, 0.35);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.mai-modal-header {
    padding: 14px 18px;
    border-bottom: 1px solid #e5e7eb;
    background: linear-gradient(90deg, #8C253E, #FF7000);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.mai-modal-header-title {
    font-size: 1rem;
    font-weight: 600;
}

.mai-modal-header-subtitle {
    font-size: 0.8rem;
    opacity: 0.9;
}

.mai-modal-header-left {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.mai-modal-close-link {
    font-size: 0.8rem;
    border-radius: 999px;
    padding: 5px 10px;
    border: 1px solid rgba(255,255,255,0.7);
    color: #111827;
    background: #ffffff;
    text-decoration: none;
    font-weight: 500;
}

.mai-modal-body {
    padding: 16px 18px 18px;
    overflow-y: auto;
}

/* Nota de ayuda dentro del modal: todo es una sola regla */
.mai-rule-help {
    margin-bottom: 12px;
    padding: 10px 12px;
    border-radius: 10px;
    background: #FFF7ED;
    border: 1px dashed rgba(255,112,0,0.55);
    font-size: 0.8rem;
    color: #7c2d12;
}

/* Responsive */
@media (max-width: 900px) {
    .local-mai-fields-grid {
        grid-template-columns: 1fr;
    }
}
.local-mai-card {
    border-radius: 18px;
    border: 1px solid #e5e7eb;
    background: #ffffff;
    box-shadow: 0 16px 30px rgba(15, 23, 42, 0.08);
    overflow: hidden;   /* IMPORTANTE para que el header use el radio del card */
    display: flex;
    flex-direction: column;
}

.local-mai-card-header {
    padding: 14px 18px;
    background: linear-gradient(135deg, var(--mai-primary), var(--mai-accent));
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    border-radius: 18px 18px 0 0; /* header = tapa superior del card */
}

</style>

<div class="local-mai-notif-wrapper">

    <p class="local-mai-notif-intro">
        Administra reglas para el <strong>envío automático de reportes</strong> y
        <strong>alertas de actividad</strong> por programa y cuatrimestre.
    </p>

    <!-- Listado de reglas -->
    <div class="local-mai-rules-list">
        <div class="local-mai-rules-header">
            <div>
                <div class="local-mai-rules-header-title">
                    Reglas configuradas
                </div>
                <div class="local-mai-rules-note">
                    Cada regla define programa, cuatrimestre, cursos, frecuencia y destinatarios.
                </div>
            </div>
            <div>
                <a href="<?php echo (new moodle_url($baseurl, ['open' => 1]))->out(false); ?>"
                   class="btn-secondary local-mai-btn-small">
                    <i class="fa fa-plus"></i> Agregar regla
                </a>
            </div>
        </div>

        <?php if (!empty($rules)) : ?>
            <table class="local-mai-rules-table" id="local-mai-rules-table">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Programa</th>
                        <th>Cuatrimestre</th>
                        <th>Cursos monitoreados</th>
                        <th>Reportes</th>
                        <th>Alertas</th>
                        <th>Estado</th>
                        <th style="width:140px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rules as $r): ?>
                    <?php
                        // Cantidad de cursos monitoreados por regla.
                        $monitoredcount = 0;
                     if (!empty($r->monitored_courses)) {
    $ids = array_unique(array_filter(array_map('intval', explode(',', $r->monitored_courses))));
    $monitoredcount = count($ids);
}else {
                            if (!empty($r->termid) && isset($coursecountbyterm[$r->termid])) {
                                $monitoredcount = $coursecountbyterm[$r->termid];
                            } elseif (!empty($r->programid) && isset($coursecountbyprogram[$r->programid])) {
                                $monitoredcount = $coursecountbyprogram[$r->programid];
                            } else {
                                $monitoredcount = $allcoursescount;
                            }
                        }
                    ?>
                    <tr>
                        <td><?php echo format_string($r->name); ?></td>
                        <td><?php echo s(local_mai_notif_cat_name($catsbyid, $r->programid)); ?></td>
                        <td><?php echo s(local_mai_notif_cat_name($catsbyid, $r->termid)); ?></td>
                        <td class="local-mai-rules-numcourses"><?php echo (int)$monitoredcount; ?></td>
                        <td>
                            <span class="local-mai-badge <?php echo $r->reportenabled ? 'local-mai-badge-on' : 'local-mai-badge-off'; ?>">
                                <?php echo $r->reportenabled ? 'Activa' : 'Inactiva'; ?>
                            </span>
                        </td>
                        <td>
                            <span class="local-mai-badge <?php echo $r->alertsenabled ? 'local-mai-badge-on' : 'local-mai-badge-off'; ?>">
                                <?php echo $r->alertsenabled ? 'Activa' : 'Inactiva'; ?>
                            </span>
                        </td>
                        <td>
                            <span class="local-mai-badge <?php echo $r->enabled ? 'local-mai-badge-on' : 'local-mai-badge-off'; ?>">
                                <?php echo $r->enabled ? 'Activa' : 'Inactiva'; ?>
                            </span>
                        </td>
                        <td class="local-mai-rule-actions">
                            <a href="<?php echo (new moodle_url($baseurl, ['ruleid' => $r->id, 'open' => 1]))->out(); ?>">
                                <i class="fa fa-pen-to-square"></i> Editar
                            </a>
                            <a href="<?php echo (new moodle_url($baseurl, ['ruleid' => $r->id, 'op' => 'delete', 'sesskey' => sesskey()]))->out(); ?>"
                               onclick="return confirm('¿Seguro que deseas eliminar esta regla?');">
                                <i class="fa fa-trash"></i> Eliminar
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="local-mai-rules-note">
                Aún no hay reglas configuradas. Usa el botón <strong>“Agregar regla”</strong> para crear la primera.
            </p>
        <?php endif; ?>
    </div>
</div>

<?php
// Datos para JS (selects dependientes).
$termsjson       = json_encode($termsbyprogram, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$coursesjson     = json_encode($coursesbyterm,   JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$allcoursesjson  = json_encode($allcourseslist,  JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$preselectedjson = json_encode(array_values($current_monitored_courses), JSON_NUMERIC_CHECK);
?>

<!-- MODAL pantalla completa (formulario de regla) -->
<div id="mai-fullscreen-modal" class="mai-modal-overlay<?php echo $open ? ' mai-modal-visible' : ''; ?>">
    <div class="mai-modal-dialog">
        <div class="mai-modal-header">
            <div class="mai-modal-header-left">
                <div class="mai-modal-header-title">
                    <?php echo $current->id ? 'Editar regla de notificaciones MAI' : 'Nueva regla de notificaciones MAI'; ?>
                </div>
                <div class="mai-modal-header-subtitle">
                    Los 3 bloques siguientes pertenecen a la misma regla. Completa y guarda al final.
                </div>
            </div>
            <div>
                <!-- Cierra modal sin guardar (regresa a la tabla) -->
                <a href="<?php echo $baseurl->out(false); ?>" class="mai-modal-close-link">
                    Cerrar sin guardar
                </a>
            </div>
        </div>
        <div class="mai-modal-body">

            <form id="mai-notif-form" method="post" action="<?php echo $baseurl->out(false); ?>">
                <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
                <input type="hidden" name="op" value="save">
                <input type="hidden" name="ruleid" value="<?php echo (int)$current->id; ?>">

                <div class="mai-rule-help">
                    <strong>Nota:</strong> Los bloques <strong>Datos generales</strong>,
                    <strong>Reportes automáticos</strong> y <strong>Alertas de inactividad</strong>
                    forman parte de <u>una sola regla</u>.
                </div>

                <div class="local-mai-notif-grid">
                    <!-- CARD 0: Datos generales de la regla -->
                    <div class="local-mai-card">
                        <div class="local-mai-card-header">
                            <div class="local-mai-card-header-main">
                                <div class="local-mai-card-icon">
                                    <i class="fa-solid fa-sliders"></i>
                                </div>
                                <div>
                                    <h3 class="local-mai-card-title">
                                        Datos generales
                                    </h3>
                                    <div class="local-mai-card-subtitle">
                                        Nombre de la regla y ámbito (programa/cuatrimestre).
                                    </div>
                                </div>
                            </div>
                            <div class="local-mai-card-header-step">
                                Bloque 1 de 3
                            </div>
                        </div>

                        <div class="local-mai-card-body">
                            <div class="local-mai-fields-grid">
                                <div class="local-mai-field-group local-mai-field-full">
                                    <label class="local-mai-field-label" for="name">
                                        Nombre de la regla
                                    </label>
                                    <input type="text"
                                           name="name"
                                           id="name"
                                           class="form-control"
                                           value="<?php echo s($current->name); ?>"
                                           placeholder="Ej. Medicina / 1er cuatrimestre">
                                    <div class="local-mai-field-help">
                                        Nombre corto para identificar la configuración.
                                    </div>
                                </div>

                                <div class="local-mai-field-group">
                                    <label class="local-mai-field-label" for="programid">
                                        Programa académico (categoría raíz)
                                    </label>
                                    <select name="programid" id="programid" class="form-control">
                                        <option value="0"<?php echo empty($current->programid) ? ' selected' : ''; ?>>Todos los programas</option>
                                        <?php foreach ($cats as $cat): ?>
                                            <?php if ((int)$cat->parent === 0): ?>
                                                <option value="<?php echo $cat->id; ?>"<?php echo ($current->programid == $cat->id) ? ' selected' : ''; ?>>
                                                    <?php echo s($cat->name); ?>
                                                </option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="local-mai-field-help">
                                        Programa al que aplica la regla.
                                    </div>
                                </div>

                                <div class="local-mai-field-group">
                                    <label class="local-mai-field-label" for="termid">
                                        Cuatrimestre (subcategoría)
                                    </label>
                                    <select name="termid" id="termid" class="form-control">
                                        <option value="0">Todos los cuatrimestres</option>
                                    </select>
                                    <div class="local-mai-field-help">
                                        Se cargan los cuatrimestres del programa elegido.
                                    </div>
                                </div>

                                <div class="local-mai-field-group">
                                    <label class="local-mai-field-label" for="enabled">
                                        Estado de la regla
                                    </label>
                                    <div>
                                        <label>
                                            <input type="checkbox" name="enabled" id="enabled" value="1"
                                                <?php echo !empty($current->enabled) ? 'checked' : ''; ?>>
                                            Regla activa
                                        </label>
                                    </div>
                                    <div class="local-mai-field-help">
                                        Si la desactivas, no se generarán reportes ni alertas.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- CARD 1: Envío de reportes -->
                    <div class="local-mai-card">
                        <div class="local-mai-card-header">
                            <div class="local-mai-card-header-main">
                                <div class="local-mai-card-icon">
                                    <i class="fa-solid fa-chart-line"></i>
                                </div>
                                <div>
                                    <h3 class="local-mai-card-title">Reportes automáticos</h3>
                                    <div class="local-mai-card-subtitle">
                                        Frecuencia, cursos y destinatarios de los reportes.
                                    </div>
                                </div>
                            </div>
                            <div class="local-mai-card-header-step">
                                Bloque 2 de 3
                            </div>
                        </div>

                        <div class="local-mai-card-body">
                            <div class="local-mai-fields-grid">

                                <div class="local-mai-field-group">
                                    <label class="local-mai-field-label" for="reportenabled">
                                        Envío automatizado
                                    </label>
                                    <div>
                                        <label>
                                            <input type="checkbox" name="reportenabled" id="reportenabled" value="1"
                                                <?php echo !empty($current->reportenabled) ? 'checked' : ''; ?>>
                                            Habilitar reportes automáticos
                                        </label>
                                    </div>
                                    <div class="local-mai-field-help">
                                        Si está activo, el cron enviará reportes con esta frecuencia.
                                    </div>
                                </div>

                                <div class="local-mai-field-group">
                                    <label class="local-mai-field-label" for="report_frequency">
                                        Frecuencia de envío
                                    </label>
                                    <select name="report_frequency" id="report_frequency" class="form-control">
                                        <option value="daily"   <?php echo $current->report_frequency === 'daily' ? 'selected' : ''; ?>>Diaria</option>
                                        <option value="weekly"  <?php echo $current->report_frequency === 'weekly' ? 'selected' : ''; ?>>Semanal</option>
                                        <option value="monthly" <?php echo $current->report_frequency === 'monthly' ? 'selected' : ''; ?>>Mensual</option>
                                    </select>
                                    <div class="local-mai-field-help">
                                        Cada cuánto se envía el resumen.
                                    </div>
                                </div>

                                <div class="local-mai-field-group local-mai-field-full">
                                    <label class="local-mai-field-label" for="monitored_courses">
                                        Cursos monitoreados (solo visibles)
                                    </label>
                                    <select name="monitored_courses[]" id="monitored_courses" class="form-control" multiple size="7">
                                        <!-- Opciones se llenan por JS -->
                                    </select>
                                    <div class="local-mai-field-help">
                                        Si no eliges cursos, se tomará todo el ámbito (programa/cuatrimestre).
                                    </div>
                                </div>

                                <div class="local-mai-field-group local-mai-field-full">
                                    <label class="local-mai-field-label" for="report_recipients">
                                        Destinatarios del reporte
                                    </label>
                                    <textarea
                                           name="report_recipients"
                                           id="report_recipients"
                                           rows="3"
                                           class="form-control"
                                           placeholder="correo1@dominio.com, correo2@dominio.com"><?php
                                           echo s($current->report_recipients ?? '');
                                           ?></textarea>
                                    <div class="local-mai-field-help">
                                        Correos separados por coma.
                                    </div>
                                </div>

                                <div class="local-mai-field-group">
                                    <label class="local-mai-field-label">
                                        Formato del adjunto
                                    </label>
                                    <div class="local-mai-inline">
                                        <label class="local-mai-chip">
                                            <input type="radio" name="report_format" value="xlsx" <?php echo $current->report_format === 'xlsx' ? 'checked' : ''; ?>>
                                            Excel (.xlsx)
                                        </label>
                                        <label class="local-mai-chip">
                                            <input type="radio" name="report_format" value="pdf" <?php echo $current->report_format === 'pdf' ? 'checked' : ''; ?>>
                                            PDF (.pdf)
                                        </label>
                                    </div>
                                </div>

                                <div class="local-mai-field-group local-mai-field-full">
                                    <label class="local-mai-field-label" for="report_template">
                                        Plantilla de correo al coordinador
                                    </label>
                                    <textarea name="report_template"
                                              id="report_template"
                                              rows="6"
                                              class="form-control"><?php
                                              echo $current->report_template ?: $default_report_template;
                                              ?></textarea>
                                    <div class="local-mai-field-help">
                                        Placeholders: <code>{{active}}</code>, <code>{{inactive}}</code>, <code>{{courses}}</code>.
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>

                    <!-- CARD 2: Alertas inteligentes -->
                    <div class="local-mai-card">
                        <div class="local-mai-card-header">
                            <div class="local-mai-card-header-main">
                                <div class="local-mai-card-icon">
                                    <i class="fa-solid fa-bell"></i>
                                </div>
                                <div>
                                    <h3 class="local-mai-card-title">Alertas de inactividad</h3>
                                    <div class="local-mai-card-subtitle">
                                        Umbrales y mensajes para estudiantes y coordinadores.
                                    </div>
                                </div>
                            </div>
                            <div class="local-mai-card-header-step">
                                Bloque 3 de 3
                            </div>
                        </div>

                        <div class="local-mai-card-body">
                            <div class="local-mai-fields-grid">

                                <div class="local-mai-field-group">
                                    <label class="local-mai-field-label" for="alertsenabled">
                                        Sistema de alertas
                                    </label>
                                    <div>
                                        <label>
                                            <input type="checkbox" name="alertsenabled" id="alertsenabled" value="1"
                                                <?php echo !empty($current->alertsenabled) ? 'checked' : ''; ?>>
                                            Habilitar alertas automáticas
                                        </label>
                                    </div>
                                    <div class="local-mai-field-help">
                                        Aplica a los cursos definidos en la regla.
                                    </div>
                                </div>

                                <div class="local-mai-field-group">
                                    <label class="local-mai-field-label" for="alert_days_inactive">
                                        Días sin ingresar (por estudiante)
                                    </label>
                                    <input type="number"
                                           name="alert_days_inactive"
                                           id="alert_days_inactive"
                                           class="form-control"
                                           min="1"
                                           value="<?php echo (int)$current->alert_days_inactive; ?>">
                                </div>

                                <div class="local-mai-field-group">
                                    <label class="local-mai-field-label" for="alert_group_inactivity">
                                        % de inactividad por grupo
                                    </label>
                                    <input type="number"
                                           name="alert_group_inactivity"
                                           id="alert_group_inactivity"
                                           class="form-control"
                                           min="0"
                                           max="100"
                                           value="<?php echo (int)$current->alert_group_inactivity; ?>">
                                    <div class="local-mai-field-help">
                                        Porcentaje mínimo de inactivos para alerta de grupo.
                                    </div>
                                </div>

                                <div class="local-mai-field-group local-mai-field-full">
                                    <label class="local-mai-field-label" for="alert_recipients">
                                        Destinatarios de alertas por grupo
                                    </label>
                                    <textarea
                                           name="alert_recipients"
                                           id="alert_recipients"
                                           rows="3"
                                           class="form-control"
                                           placeholder="correo1@dominio.com, correo2@dominio.com"><?php
                                           echo s($current->alert_recipients ?? '');
                                           ?></textarea>
                                    <div class="local-mai-field-help">
                                        Correos para alertas de inactividad (separados por coma).
                                    </div>
                                </div>

                                <div class="local-mai-field-group local-mai-field-full">
                                    <label class="local-mai-field-label">
                                        Canales de notificación al estudiante
                                    </label>
                                    <div class="local-mai-inline">
                                        <label class="local-mai-chip">
                                            <input type="checkbox" name="alert_channels[]" value="email"
                                                <?php echo (strpos($current->alert_channels ?? '', 'email') !== false) ? 'checked' : ''; ?>>
                                            Correo electrónico
                                        </label>
                                        <label class="local-mai-chip">
                                            <input type="checkbox" name="alert_channels[]" value="internal"
                                                <?php echo (strpos($current->alert_channels ?? 'internal', 'internal') !== false) ? 'checked' : ''; ?>>
                                            Mensaje interno
                                        </label>
                                    </div>
                                </div>

                                <div class="local-mai-field-group local-mai-field-full">
                                    <label class="local-mai-field-label" for="alert_student_message">
                                        Mensaje para el estudiante
                                    </label>
                                    <textarea name="alert_student_message"
                                              id="alert_student_message"
                                              rows="4"
                                              class="form-control"><?php
                                              echo $current->alert_student_message ?: $default_student_msg;
                                              ?></textarea>
                                    <div class="local-mai-field-help">
                                        Puedes usar <code>{{fullname}}</code> para el nombre del estudiante.
                                    </div>
                                </div>

                                <div class="local-mai-field-group local-mai-field-full">
                                    <label class="local-mai-field-label" for="alert_coord_message">
                                        Mensaje para coordinador/tutor
                                    </label>
                                    <textarea name="alert_coord_message"
                                              id="alert_coord_message"
                                              rows="4"
                                              class="form-control"><?php
                                              echo $current->alert_coord_message ?: $default_coord_msg;
                                              ?></textarea>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

                <!-- CARD 3: Acciones -->
                <div class="local-mai-card local-mai-card-footer">
                    <div class="local-mai-card-footer-header">
                        <div class="local-mai-card-footer-title">
                            Guardar configuración de la regla
                        </div>
                    </div>
                    <div class="local-mai-card-footer-body">
                        <div class="local-mai-inline" style="justify-content: space-between; align-items:center;">
                            <div>
                                <div class="local-mai-field-help">
                                    El cron usará esta regla para generar reportes y alertas
                                    según el programa, cuatrimestre y cursos definidos.
                                </div>
                            </div>
                            <div class="local-mai-actions">
                                <button type="submit" class="btn btn-primary" id="mai-notif-save-btn">
                                    <i class="fa fa-save"></i>
                                    <?php echo $current->id ? 'Actualizar regla' : 'Crear regla'; ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

            </form>

        </div>
    </div>
</div>

<script>
(function($) {
    // ------------------------
    // DataTables en tabla de reglas
    // ------------------------
    $(function() {
        var $tbl = $('#local-mai-rules-table');
        if ($tbl.length) {
            $tbl.DataTable({
                paging: true,
                searching: true,
                info: true,
                lengthChange: false,   // sin menú de "entries"
                ordering: true,
                order: [[0, 'asc']],
                autoWidth: false,
                language: {
                    decimal:        "",
                    emptyTable:     "No hay reglas registradas",
                    info:           "Mostrando _START_ a _END_ de _TOTAL_ reglas",
                    infoEmpty:      "Mostrando 0 a 0 de 0 reglas",
                    infoFiltered:   "(filtrado de _MAX_ reglas en total)",
                    thousands:      ",",
                    loadingRecords: "Cargando...",
                    processing:     "Procesando...",
                    search:         "Buscar:",
                    zeroRecords:    "No se encontraron resultados",
                    paginate: {
                        first:    "Primero",
                        last:     "Último",
                        next:     "Siguiente",
                        previous: "Anterior"
                    }
                }
            });
        }
    });

    // ------------------------
    // JS selects dependientes y cursos
    // ------------------------
    var termsByProgram     = <?php echo $termsjson; ?> || {};
    var coursesByTerm      = <?php echo $coursesjson; ?> || {};
    var allCourses         = <?php echo $allcoursesjson; ?> || [];
    var preselectedCourses = <?php echo $preselectedjson; ?> || [];

    var initialProgram = <?php echo (int)$current->programid; ?>;
    var initialTerm    = <?php echo (int)$current->termid; ?>;

    function rebuildTermOptions() {
        var programId = parseInt($('#programid').val(), 10) || 0;
        var $term = $('#termid');

        $term.empty();
        $term.append($('<option>', { value: 0, text: 'Todos los cuatrimestres' }));

        if (programId > 0 && termsByProgram[programId]) {
            termsByProgram[programId].forEach(function(t) {
                var opt = $('<option>', { value: t.id, text: t.name });
                if (t.id === initialTerm) {
                    opt.attr('selected', 'selected');
                }
                $term.append(opt);
            });
        }

        if (programId !== initialProgram) {
            $term.val('0');
        }
    }

    function rebuildCourseOptions() {
        var programId = parseInt($('#programid').val(), 10) || 0;
        var termId    = parseInt($('#termid').val(), 10) || 0;
        var $courses  = $('#monitored_courses');

        var currentSelected = ($courses.val() || []).map(function(v) { return parseInt(v, 10); });
        var baseSelected    = currentSelected.length ? currentSelected : preselectedCourses;
        var selectedMap     = {};
        baseSelected.forEach(function(id) { selectedMap[id] = true; });

        var list = [];

        if (termId > 0 && coursesByTerm[termId]) {
            list = coursesByTerm[termId].slice();
        } else if (programId > 0 && termsByProgram[programId]) {
            termsByProgram[programId].forEach(function(t) {
                if (coursesByTerm[t.id]) {
                    list = list.concat(coursesByTerm[t.id]);
                }
            });
        } else {
            list = allCourses.slice();
        }

        $courses.empty();
        list.forEach(function(c) {
            var opt = $('<option>', { value: c.id, text: c.name });
            if (selectedMap[c.id]) {
                opt.attr('selected', 'selected');
            }
            $courses.append(opt);
        });
    }

    $(function() {
        // Solo configuramos selects si el modal existe (la página puede venir sin open=1).
        if ($('#mai-fullscreen-modal').length) {
            rebuildTermOptions();
            rebuildCourseOptions();

            $('#programid').on('change', function() {
                rebuildTermOptions();
                rebuildCourseOptions();
            });

            $('#termid').on('change', function() {
                rebuildCourseOptions();
            });
        }
    });

})(jQuery);
</script>

<?php
// Editores HTML para los campos largos (aunque estén en el modal).
$editor = editors_get_preferred_editor();
$editor->use_editor('report_template', []);
$editor->use_editor('alert_student_message', []);
$editor->use_editor('alert_coord_message', []);

echo $OUTPUT->footer();
