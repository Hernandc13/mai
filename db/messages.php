<?php
// local/mai/db/messages.php

defined('MOODLE_INTERNAL') || die();

$messageproviders = [
    // Recordatorio individual de inactividad para estudiantes.
    'alert_student_inactivity' => [
        'capability' => '', // sin restricción extra
        'defaults'   => [
            // Permitido, pero sin forzar “activado por defecto”.
            'popup' => MESSAGE_PERMITTED,
            //'email' => MESSAGE_PERMITTED,
        ],
    ],
];
