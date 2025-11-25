<?php
// local/mai/estructura/ajax.php

require(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();

$systemcontext = context_system::instance();
require_capability('local/mai:viewreport', $systemcontext);
require_sesskey();

// Modo de la llamada: 'stats' (completo) o 'filters' (solo filtros ligeros).
$mode      = optional_param('mode', 'stats', PARAM_ALPHA);

// Filtros que vienen del frontend.
$programid = optional_param('programid', 0, PARAM_INT);
$termid    = optional_param('termid', 0, PARAM_INT);
$teacherid = optional_param('teacherid', 0, PARAM_INT);
$groupid   = optional_param('groupid', 0, PARAM_INT);

$PAGE->set_context($systemcontext);

if ($mode === 'filters') {
    // Llamada ligera: solo devolvemos estructura de filtros
    // (cuatrimestres del programa, etc.), sin recorrer todos los cursos.
    $data = local_mai_estructura_get_filters($programid, $termid, $teacherid, $groupid);
} else {
    // Llamada completa: stats globales, por programa, por cuatrimestre, etc.
    $data = local_mai_estructura_get_stats($programid, $termid, $teacherid, $groupid);
}

@header('Content-Type: application/json; charset=utf-8');
echo json_encode($data);
die;
