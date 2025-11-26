<?php
// local/mai/notificaciones/ajax.php
/**
 * Endpoint AJAX para guardar config y enviar prueba de correo.
 *
 * @package   local_mai
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/moodlelib.php');

require_login();
require_sesskey();

$systemcontext = context_system::instance();
require_capability('local_mai:viewreport', $systemcontext);

header('Content-Type: application/json; charset=utf-8');

$action = required_param('action', PARAM_ALPHANUMEXT); // save_config | test_email

$response = [
    'status'  => 'error',
    'message' => 'Acción no válida.'
];

try {

    switch ($action) {

        case 'save_config':

            $autoreports_enabled = optional_param('autoreports_enabled', 0, PARAM_BOOL) ? 1 : 0;
            $alerts_enabled      = optional_param('alerts_enabled', 0, PARAM_BOOL) ? 1 : 0;

            $report_frequency   = optional_param('report_frequency', 'daily', PARAM_ALPHA);
            $report_recipients  = optional_param('report_recipients', '', PARAM_RAW_TRIMMED);
            $report_format      = optional_param('report_format', 'pdf', PARAM_ALPHA);
            $report_template    = optional_param('report_template', '', PARAM_RAW);

            $alert_days_inactive    = optional_param('alert_days_inactive', 7, PARAM_INT);
            $alert_group_inactivity = optional_param('alert_group_inactivity', 50, PARAM_INT);
            $alert_recipients       = optional_param('alert_recipients', '', PARAM_RAW_TRIMMED);
            $alert_student_message  = optional_param('alert_student_message', '', PARAM_RAW);
            $alert_coord_message    = optional_param('alert_coord_message', '', PARAM_RAW);

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

            $response['status']  = 'ok';
            $response['message'] = 'Configuración guardada correctamente.';
            break;

        case 'test_email':

            global $USER;

            $config = get_config('local_mai');
            $to = $USER;

            $subject = 'Prueba de envío automático MAI';
            $body = "Hola {$USER->firstname},\n\n".
                "Este es un correo de prueba del módulo de envío automatizado MAI.\n\n".
                "Configuración actual:\n".
                "- Envío automatizado: " . (!empty($config->autoreports_enabled) ? 'activo' : 'inactivo') . "\n".
                "- Frecuencia: " . ($config->report_frequency ?? 'daily') . "\n".
                "- Formato: " . ($config->report_format ?? 'pdf') . "\n".
                "- Destinatarios (reales): " . ($config->report_recipients ?? '(no definidos)') . "\n\n".
                "Si recibes este mensaje, el servidor de correo está respondiendo correctamente.\n\n".
                "Atentamente,\nSistema MAI";

            $supportuser = \core_user::get_support_user();
            $sent = email_to_user($to, $supportuser, $subject, $body);

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
    $response['message'] = 'Error en ajax.php: ' . $e->getMessage();
}

echo json_encode($response);
exit;
