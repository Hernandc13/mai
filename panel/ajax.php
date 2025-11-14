<?php
// local/mai/panel/ajax.php

require(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();

$systemcontext = context_system::instance();
require_capability('local/mai:viewreport', $systemcontext);
require_sesskey();

$programid = optional_param('programid', 0, PARAM_INT);
$termid    = optional_param('termid', 0, PARAM_INT);
$teacherid = optional_param('teacherid', 0, PARAM_INT);
$groupid   = optional_param('groupid', 0, PARAM_INT);

$PAGE->set_context($systemcontext);

// Llamamos al lib propio del panel.
$data = local_mai_panel_get_stats($programid, $termid, $teacherid, $groupid);

@header('Content-Type: application/json; charset=utf-8');
echo json_encode($data);
die;
