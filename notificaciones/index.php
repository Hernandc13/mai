<?php
// local/mai/notificaciones/index.php
/**
 * Configuración de envío automatizado de reportes y alertas inteligentes MAI.
 *
 * @package   local_mai
 */

require(__DIR__ . '/../../../config.php');
require_login();

global $CFG, $DB, $PAGE, $OUTPUT, $USER;

$systemcontext = context_system::instance();

require_once($CFG->libdir . '/moodlelib.php');
require_once($CFG->libdir . '/weblib.php');

// ---------------------------------------------------------------------
// MODO AJAX: action=save_config | test_email
// ---------------------------------------------------------------------
$action = optional_param('action', '', PARAM_ALPHANUMEXT);

if (!empty($action)) {
    require_sesskey();
    header('Content-Type: application/json; charset=utf-8');

    $response = [
        'status'  => 'error',
        'message' => 'Acción no válida.',
    ];

    try {
        switch ($action) {

            case 'save_config':
                $autoreports_enabled = optional_param('autoreports_enabled', 0, PARAM_BOOL) ? 1 : 0;
                $alerts_enabled      = optional_param('alerts_enabled', 0, PARAM_BOOL) ? 1 : 0;

                $report_frequency   = optional_param('report_frequency', 'daily', PARAM_ALPHA);
                $report_recipients  = optional_param('report_recipients', '',   PARAM_RAW_TRIMMED);
                $report_format      = optional_param('report_format', 'pdf',    PARAM_ALPHA);
                $report_template    = optional_param('report_template', '',     PARAM_RAW);

                $alert_days_inactive    = optional_param('alert_days_inactive',    7,  PARAM_INT);
                $alert_group_inactivity = optional_param('alert_group_inactivity', 50, PARAM_INT);
                $alert_recipients       = optional_param('alert_recipients',       '', PARAM_RAW_TRIMMED);
                $alert_student_message  = optional_param('alert_student_message',  '', PARAM_RAW);
                $alert_coord_message    = optional_param('alert_coord_message',    '', PARAM_RAW);

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
                $alert_channels = implode(',', $alert_channels_arr);

                // Cursos monitoreados (multi-select).
                $monitored_arr = optional_param_array('monitored_courses', [], PARAM_INT);
                $monitored_arr = array_filter(array_map('intval', $monitored_arr));
                $monitored_courses = implode(',', $monitored_arr);

                // Guardar config del plugin local_mai.
                set_config('autoreports_enabled', $autoreports_enabled, 'local_mai');
                set_config('alerts_enabled',      $alerts_enabled,      'local_mai');

                set_config('report_frequency',  $report_frequency,  'local_mai');
                set_config('report_recipients', $report_recipients, 'local_mai');
                set_config('report_format',     $report_format,     'local_mai');
                set_config('report_template',   $report_template,   'local_mai');

                set_config('alert_days_inactive',    $alert_days_inactive,    'local_mai');
                set_config('alert_group_inactivity', $alert_group_inactivity, 'local_mai');
                set_config('alert_recipients',       $alert_recipients,       'local_mai');
                set_config('alert_student_message',  $alert_student_message,  'local_mai');
                set_config('alert_coord_message',    $alert_coord_message,    'local_mai');
                set_config('alert_channels',         $alert_channels,         'local_mai');

                set_config('monitored_courses',      $monitored_courses,      'local_mai');

                $response['status']  = 'ok';
                $response['message'] = 'Configuración guardada correctamente.';
                break;

            case 'test_email':
                $config = get_config('local_mai');
                $to     = $USER;

                $subject = 'Prueba de envío automático MAI';
                $bodytext = "Hola {$USER->firstname},\n\n" .
                    "Este es un correo de prueba del módulo de envío automatizado MAI.\n\n" .
                    "Configuración actual:\n" .
                    "- Envío automatizado: " . (!empty($config->autoreports_enabled) ? 'activo' : 'inactivo') . "\n" .
                    "- Frecuencia: " . ($config->report_frequency ?? 'daily') . "\n" .
                    "- Formato: " . ($config->report_format ?? 'pdf') . "\n" .
                    "- Destinatarios (reales): " . ($config->report_recipients ?? '(no definidos)') . "\n\n" .
                    "Si recibes este mensaje, el servidor de correo está respondiendo correctamente.\n\n" .
                    "Atentamente,\n MAI";

                $bodyhtml = nl2br(s($bodytext));

                $supportuser = \core_user::get_support_user();
                $sent = email_to_user($to, $supportuser, $subject, $bodytext, $bodyhtml);

                if ($sent) {
                    $response['status']  = 'ok';
                    $response['message'] = 'Correo de prueba enviado correctamente al usuario actual.';
                } else {
                    $response['status']  = 'error';
                    $response['message'] = 'No se pudo enviar el correo de prueba. Revisa la configuración SMTP.';
                }
                break;
        }

    } catch (\Throwable $e) {
        $response['status']  = 'error';
        $response['message'] = 'Error en index.php (AJAX): ' . $e->getMessage();
    }

    echo json_encode($response);
    exit;
}

// ---------------------------------------------------------------------
// MODO NORMAL: renderizar página.
// ---------------------------------------------------------------------

$pagetitle = 'Envío automatizado de reportes y alertas';

$PAGE->set_url(new moodle_url('/local/mai/notificaciones/index.php'));
$PAGE->set_context($systemcontext);
$PAGE->set_pagelayout('report');
$PAGE->set_title($pagetitle);
$PAGE->set_heading($pagetitle);


// jQuery
$PAGE->requires->jquery();

// Cargamos config del plugin local_mai.
$config = get_config('local_mai');

// Valores por defecto.
$reportfrequency   = $config->report_frequency ?? 'weekly';
$reportformat      = $config->report_format ?? 'pdf';
$reportrecipients  = $config->report_recipients ?? '';
$reporttemplate    = $config->report_template ?? '
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

$alertdaysinactive     = isset($config->alert_days_inactive) ? (int)$config->alert_days_inactive : 7;
$alertgroupinactivity  = isset($config->alert_group_inactivity) ? (int)$config->alert_group_inactivity : 50;
$alertstudentmsg       = $config->alert_student_message ?? '{{fullname}}, te invitamos a continuar tus actividades en la plataforma. Tu progreso es importante.';
$alertcoordmsg         = $config->alert_coord_message   ?? 'Se ha detectado un grupo con alta inactividad. Te sugerimos revisar las actividades y contactar a los estudiantes.';
$alertrecipients       = $config->alert_recipients ?? '';
$alertchannels         = $config->alert_channels  ?? 'email,internal';
$alerts_enabled        = isset($config->alerts_enabled) ? (int)$config->alerts_enabled : 1;
$autoreports_enabled   = isset($config->autoreports_enabled) ? (int)$config->autoreports_enabled : 1;

// Cursos para selector.
$courses = $DB->get_records_select('course', 'id <> 1 AND visible = 1', null, 'fullname ASC', 'id, fullname, shortname');
$monitored = !empty($config->monitored_courses) ? array_filter(array_map('intval', explode(',', $config->monitored_courses))) : [];

echo $OUTPUT->header();

// FontAwesome
echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />';
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
}

/* Wrapper general */
.local-mai-notif-wrapper {
    max-width: 1100px;
    margin: 0 auto;
    padding-bottom: 32px;
    font-family: "Inter", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
}

.local-mai-notif-intro {
    font-size: 0.9rem;
    color: var(--mai-gray);
    margin-bottom: 18px;
}

/* Grid de columnas principales */
.local-mai-notif-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 20px;
}

/* Cards horizontales */
.local-mai-card {
    border-radius: 18px;
    border: 1px solid #e5e7eb;
    background: #ffffff;
    box-shadow: 0 16px 30px rgba(15, 23, 42, 0.08);
    overflow: hidden;
    display: flex;
    flex-direction: row;
    min-height: 180px;
}

.local-mai-card-horizontal {
    display: flex;
    flex-direction: row;
}

.local-mai-card-header-side {
    width: 260px;
    min-width: 230px;
    padding: 18px 20px;
    background: linear-gradient(135deg, var(--mai-primary), var(--mai-accent));
    color: #ffffff;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.local-mai-card-header-top {
    display: flex;
    gap: 12px;
    align-items: flex-start;
}

/* Icono redondo sin deformarse */
.local-mai-card-icon {
    flex: 0 0 36px;
    width: 36px;
    height: 36px;
    border-radius: 999px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    background: rgba(255, 255, 255, 0.18);
}

.local-mai-card-title {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 700;
}

.local-mai-card-subtitle {
    font-size: 0.82rem;
    opacity: 0.9;
    margin-top: 4px;
}

.local-mai-card-header-tags {
    margin-top: 14px;
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.local-mai-pill {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    border-radius: 999px;
    border: 1px solid rgba(255,255,255,0.35);
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
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

/* Inputs premium */
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
    margin-top: 18px;
    flex-direction: column;
    padding: 0;
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

.local-mai-status {
    margin-top: 8px;
    font-size: 0.8rem;
}

.local-mai-status-success {
    color: #16a34a;
    font-weight: 600;
}

.local-mai-status-error {
    color: #dc2626;
    font-weight: 600;
}

/* Botones premium */
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

/* Misma altura para todos los editores HTML de esta página */
.tox.tox-tinymce,
.editor_atto {
    min-height: 260px;
}

/* por si aplica TinyMCE con iframe interno */
.tox .tox-edit-area__iframe {
    min-height: 220px;
}

@media (max-width: 900px) {
    .local-mai-card-horizontal {
        flex-direction: column;
    }
    .local-mai-card-header-side {
        width: 100%;
        min-width: 0;
    }
    .local-mai-fields-grid {
        grid-template-columns: 1fr;
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
</style>

<div class="local-mai-notif-wrapper">

    <p class="local-mai-notif-intro">
        Configura el <strong>envío automático de reportes</strong> y las
        <strong>alertas de actividad</strong> para coordinadores, tutores y estudiantes.
    </p>

    <form id="mai-notif-form">

        <div class="local-mai-notif-grid">
            <!-- CARD 1: Envío de reportes (horizontal) -->
            <div class="local-mai-card local-mai-card-horizontal">
                <div class="local-mai-card-header-side">
                    <div class="local-mai-card-header-top">
                        <div class="local-mai-card-icon">
                            <i class="fa-solid fa-chart-line"></i>
                        </div>
                        <div>
                            <h3 class="local-mai-card-title">Envío automatizado de reportes</h3>
                            <div class="local-mai-card-subtitle">
                                Frecuencia, destinatarios, formato y cursos a monitorear.
                            </div>
                        </div>
                    </div>
                    <div class="local-mai-card-header-tags">
                        <span class="local-mai-pill">Reportes MAI</span>
                        <span class="local-mai-pill">Coordinadores</span>
                    </div>
                </div>

                <div class="local-mai-card-body">
                    <div class="local-mai-fields-grid">

                        <div class="local-mai-field-group">
                            <label class="local-mai-field-label" for="autoreports_enabled">
                                Activar envío automatizado
                            </label>
                            <div>
                                <label>
                                    <input type="checkbox" name="autoreports_enabled" id="autoreports_enabled" value="1" <?php echo $autoreports_enabled ? 'checked' : ''; ?>>
                                    Habilitar este módulo
                                </label>
                            </div>
                            <div class="local-mai-field-help">
                                Cuando está activo, se generan y envían reportes recurrentes según la frecuencia seleccionada.
                            </div>
                        </div>

                        <div class="local-mai-field-group">
                            <label class="local-mai-field-label" for="report_frequency">
                                Frecuencia de envío
                            </label>
                            <select name="report_frequency" id="report_frequency" class="form-control">
                                <option value="daily"   <?php echo $reportfrequency === 'daily' ? 'selected' : ''; ?>>Diaria</option>
                                <option value="weekly"  <?php echo $reportfrequency === 'weekly' ? 'selected' : ''; ?>>Semanal</option>
                                <option value="monthly" <?php echo $reportfrequency === 'monthly' ? 'selected' : ''; ?>>Mensual</option>
                            </select>
                            <div class="local-mai-field-help">
                                Define cada cuánto tiempo se envían los reportes consolidados.
                            </div>
                        </div>

                        <div class="local-mai-field-group local-mai-field-full">
                            <label class="local-mai-field-label" for="monitored_courses">
                                Cursos monitoreados
                            </label>
                            <select name="monitored_courses[]" id="monitored_courses" class="form-control" multiple size="7">
                                <?php foreach ($courses as $c): ?>
                                    <option value="<?php echo $c->id; ?>" <?php echo in_array($c->id, $monitored) ? 'selected' : ''; ?>>
                                        <?php echo s($c->fullname); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="local-mai-field-help">
                                Solo los cursos seleccionados se incluirán en los reportes automáticos.
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
                                   placeholder="correo1@dominio.com, correo2@dominio.com"><?php echo s($reportrecipients); ?></textarea>
                            <div class="local-mai-field-help">
                                Correos de coordinadores/tutores (separados por coma).
                            </div>
                        </div>

                        <div class="local-mai-field-group">
                            <label class="local-mai-field-label">
                                Formato del reporte adjunto
                            </label>
                            <div class="local-mai-inline">
                                <label class="local-mai-chip">
                                    <input type="radio" name="report_format" value="xlsx" <?php echo $reportformat === 'xlsx' ? 'checked' : ''; ?>>
                                    Excel (.xlsx)
                                </label>
                                <label class="local-mai-chip">
                                    <input type="radio" name="report_format" value="pdf" <?php echo $reportformat === 'pdf' ? 'checked' : ''; ?>>
                                    PDF (.pdf)
                                </label>
                            </div>
                            <div class="local-mai-field-help">
                                El adjunto incluirá el detalle de participación por curso.
                            </div>
                        </div>

                        <div class="local-mai-field-group local-mai-field-full">
                            <label class="local-mai-field-label" for="report_template">
                                Plantilla de correo al coordinador
                            </label>
                            <textarea name="report_template"
                                      id="report_template"
                                      rows="6"
                                      class="form-control"><?php echo $reporttemplate; ?></textarea>
                            <div class="local-mai-field-help">
                                Placeholders: <code>{{active}}</code> (estudiantes activos),
                                <code>{{inactive}}</code> (estudiantes inactivos),
                                <code>{{courses}}</code> (cursos monitoreados).
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- CARD 2: Alertas inteligentes (horizontal) -->
            <div class="local-mai-card local-mai-card-horizontal">
                <div class="local-mai-card-header-side">
                    <div class="local-mai-card-header-top">
                        <div class="local-mai-card-icon">
                            <i class="fa-solid fa-bell"></i>
                        </div>
                        <div>
                            <h3 class="local-mai-card-title">Alertas y notificaciones</h3>
                            <div class="local-mai-card-subtitle">
                                Umbrales de inactividad y mensajes al estudiante y coordinador.
                            </div>
                        </div>
                    </div>
                    <div class="local-mai-card-header-tags">
                        <span class="local-mai-pill">Alertas automáticas</span>
                        <span class="local-mai-pill">Seguimiento</span>
                    </div>
                </div>

                <div class="local-mai-card-body">
                    <div class="local-mai-fields-grid">

                        <div class="local-mai-field-group">
                            <label class="local-mai-field-label" for="alerts_enabled">
                                Activar sistema de alertas
                            </label>
                            <div>
                                <label>
                                    <input type="checkbox" name="alerts_enabled" id="alerts_enabled" value="1" <?php echo $alerts_enabled ? 'checked' : ''; ?>>
                                    Habilitar alertas automáticas
                                </label>
                            </div>
                            <div class="local-mai-field-help">
                                Si está activo, el sistema enviará alertas basadas en inactividad individual y por grupo.
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
                                   value="<?php echo $alertdaysinactive; ?>">
                            <div class="local-mai-field-help">
                                Número de días consecutivos sin acceso para generar alerta.
                            </div>
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
                                   value="<?php echo $alertgroupinactivity; ?>">
                            <div class="local-mai-field-help">
                                Porcentaje mínimo de estudiantes inactivos para disparar alerta de grupo.
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
                                   placeholder="correo1@dominio.com, correo2@dominio.com"><?php echo s($alertrecipients); ?></textarea>
                            <div class="local-mai-field-help">
                                Correos para el resumen de inactividad por curso/grupo (separados por coma).
                            </div>
                        </div>

                        <div class="local-mai-field-group local-mai-field-full">
                            <label class="local-mai-field-label">
                                Canal de notificación al estudiante
                            </label>
                            <div class="local-mai-inline">
                                <label class="local-mai-chip">
                                    <input type="checkbox" name="alert_channels[]" value="email"
                                        <?php echo (strpos($alertchannels, 'email') !== false) ? 'checked' : ''; ?>>
                                    Correo electrónico
                                </label>
                                <label class="local-mai-chip">
                                    <input type="checkbox" name="alert_channels[]" value="internal"
                                        <?php echo (strpos($alertchannels, 'internal') !== false) ? 'checked' : ''; ?>>
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
                                      class="form-control"><?php echo $alertstudentmsg; ?></textarea>
                            <div class="local-mai-field-help">
                                Puedes usar <code>{{fullname}}</code> para insertar el nombre del estudiante.
                            </div>
                        </div>

                        <div class="local-mai-field-group local-mai-field-full">
                            <label class="local-mai-field-label" for="alert_coord_message">
                                Mensaje para coordinador/tutor
                            </label>
                            <textarea name="alert_coord_message"
                                      id="alert_coord_message"
                                      rows="4"
                                      class="form-control"><?php echo $alertcoordmsg; ?></textarea>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <!-- CARD 3: Acciones -->
        <div class="local-mai-card local-mai-card-footer">
            <div class="local-mai-card-footer-header">
                <div class="local-mai-card-footer-title">
                    Acciones sobre la configuración
                </div>
            </div>
            <div class="local-mai-card-footer-body">
                <div class="local-mai-inline" style="justify-content: space-between; align-items:center;">
                    <div>
                        <div class="local-mai-field-help">
                            Guarda los cambios o envía un correo de prueba con la configuración actual.
                        </div>
                    </div>
                    <div class="local-mai-actions">
                        <button type="submit" class="btn btn-primary" id="mai-notif-save-btn">
                            <i class="fa fa-save"></i> Guardar configuración
                        </button>
                        <button type="button" class="btn btn-secondary" id="mai-notif-test-btn">
                            <i class="fa fa-paper-plane"></i> Enviar correo de prueba
                        </button>
                    </div>
                </div>
                <div class="local-mai-status" id="mai-notif-status"></div>
            </div>
        </div>

        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
    </form>
</div>

<script>
(function($) {

    var ajaxUrl = '<?php echo $PAGE->url->out(false); ?>';
    console.log('MAI notificaciones JS cargado. AJAX URL:', ajaxUrl);

    function showStatus(message, isError) {
        var $status = $('#mai-notif-status');
        $status
            .removeClass('local-mai-status-success local-mai-status-error')
            .addClass(isError ? 'local-mai-status-error' : 'local-mai-status-success')
            .text(message);
    }

    // Guardar configuración
    $('#mai-notif-form').on('submit', function(e) {
        e.preventDefault();

        var $btn = $('#mai-notif-save-btn');
        $btn.prop('disabled', true).text('Guardando...');

        var data = $(this).serializeArray();
        data.push({name: 'action', value: 'save_config'});

        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: $.param(data),
            dataType: 'json'
        }).done(function(resp) {
            console.log('MAI save_config response:', resp);
            if (resp && resp.status === 'ok') {
                showStatus(resp.message || 'Configuración guardada correctamente.', false);
            } else {
                showStatus(resp && resp.message ? resp.message : 'Ocurrió un problema al guardar la configuración.', true);
            }
        }).fail(function(xhr, textStatus, errorThrown) {
            console.error('MAI save_config AJAX error:', textStatus, errorThrown, xhr.responseText);
            showStatus('Error de comunicación con el servidor.', true);
        }).always(function() {
            $btn.prop('disabled', false).text('Guardar configuración');
        });
    });

    // Enviar correo de prueba
    $('#mai-notif-test-btn').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).text('Enviando prueba...');

        var data = $('#mai-notif-form').serializeArray();
        data.push({name: 'action', value: 'test_email'});

        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: $.param(data),
            dataType: 'json'
        }).done(function(resp) {
            console.log('MAI test_email response:', resp);
            if (resp && resp.status === 'ok') {
                showStatus(resp.message || 'Correo de prueba enviado correctamente.', false);
            } else {
                showStatus(resp && resp.message ? resp.message : 'No se pudo enviar el correo de prueba.', true);
            }
        }).fail(function(xhr, textStatus, errorThrown) {
            console.error('MAI test_email AJAX error:', textStatus, errorThrown, xhr.responseText);
            showStatus('Error de comunicación con el servidor al enviar la prueba.', true);
        }).always(function() {
            $btn.prop('disabled', false).text('Enviar correo de prueba');
        });
    });

})(jQuery);
</script>

<?php
// Convertimos textareas clave en editor HTML (usa el editor por defecto de Moodle).
$editor = editors_get_preferred_editor();
$editor->use_editor('report_template', []);
$editor->use_editor('alert_student_message', []);
$editor->use_editor('alert_coord_message', []);

echo $OUTPUT->footer();
