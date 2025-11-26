<?php
// local/mai/notificaciones/lib.php
/**
 * Funciones auxiliares para el módulo de notificaciones MAI.
 *
 * @package   local_mai
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Devuelve la configuración relevante del módulo de notificaciones.
 *
 * @return stdClass
 */
function local_mai_notificaciones_get_settings(): stdClass {
    $config = get_config('local_mai');
    $settings = new stdClass();

    $settings->autoreports_enabled    = !empty($config->autoreports_enabled);
    $settings->alerts_enabled         = !empty($config->alerts_enabled);
    $settings->report_frequency       = $config->report_frequency ?? 'daily';
    $settings->report_format          = $config->report_format ?? 'xlsx';
    $settings->report_recipients      = $config->report_recipients ?? '';
    $settings->report_template        = $config->report_template ?? '';
    $settings->alert_days_inactive    = isset($config->alert_days_inactive) ? (int)$config->alert_days_inactive : 7;
    $settings->alert_group_inactivity = isset($config->alert_group_inactivity) ? (int)$config->alert_group_inactivity : 50;
    $settings->alert_recipients       = $config->alert_recipients ?? '';
    $settings->alert_student_message  = $config->alert_student_message ?? '';
    $settings->alert_coord_message    = $config->alert_coord_message ?? '';
    $settings->alert_channels         = $config->alert_channels ?? 'email,internal';

    // Timestamps para control interno.
    $settings->last_report_sent     = isset($config->last_report_sent) ? (int)$config->last_report_sent : 0;
    $settings->last_alerts_checked  = isset($config->last_alerts_checked) ? (int)$config->last_alerts_checked : 0;

    return $settings;
}

/**
 * Actualiza el timestamp de último reporte enviado.
 *
 * @return void
 */
function local_mai_notificaciones_mark_report_sent(): void {
    set_config('last_report_sent', time(), 'local_mai');
}

/**
 * Actualiza el timestamp de última revisión de alertas.
 *
 * @return void
 */
function local_mai_notificaciones_mark_alerts_checked(): void {
    set_config('last_alerts_checked', time(), 'local_mai');
}

/**
 * Decide si corresponde enviar un reporte según la frecuencia y la última ejecución.
 *
 * @param stdClass|null $settings
 * @return bool
 */
function local_mai_notificaciones_should_send_report(stdClass $settings = null): bool {
    if ($settings === null) {
        $settings = local_mai_notificaciones_get_settings();
    }

    if (!$settings->autoreports_enabled) {
        return false;
    }

    $now = time();
    $last = $settings->last_report_sent ?? 0;

    // Si nunca se ha enviado, lo permitimos.
    if (empty($last)) {
        return true;
    }

    $diff = $now - $last;

    switch ($settings->report_frequency) {
        case 'daily':
            return $diff >= DAYSECS;
        case 'weekly':
            return $diff >= WEEKSECS;
        case 'monthly':
            // Aproximación de 30 días.
            return $diff >= (30 * DAYSECS);
        default:
            return false;
    }
}

/**
 * Decide si corresponde verificar alertas según la última revisión.
 *
 * @param stdClass|null $settings
 * @return bool
 */
function local_mai_notificaciones_should_check_alerts(stdClass $settings = null): bool {
    if ($settings === null) {
        $settings = local_mai_notificaciones_get_settings();
    }

    if (!$settings->alerts_enabled) {
        return false;
    }

    $now = time();
    $last = $settings->last_alerts_checked ?? 0;

    // Revisamos al menos cada 6 horas por defecto.
    if (empty($last)) {
        return true;
    }

    $diff = $now - $last;
    return $diff >= (6 * HOURSECS);
}
