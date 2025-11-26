<?php
// local/mai/cli/reset_notificaciones.php
// Script CLI para resetear contadores de notificaciones MAI.
//
// Uso:
//   php local/mai/cli/reset_notificaciones.php

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

cli_heading('Reinicio de contadores de notificaciones MAI');

// Ponemos a 0 los timestamps para que las tareas vuelvan a ejecutarse como "primera vez".
set_config('last_report_sent', 0, 'local_mai');
set_config('last_alerts_checked', 0, 'local_mai');

cli_write("Se han reiniciado last_report_sent y last_alerts_checked de local_mai.\n");
