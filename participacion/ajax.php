<?php
// local/mai/participacion/ajax.php

define('AJAX_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();

$systemcontext = context_system::instance();
require_capability('local/mai:viewreport', $systemcontext);

$courseid   = required_param('courseid', PARAM_INT);
$categoryid = optional_param('categoryid', 0, PARAM_INT);
$cohortid   = optional_param('cohortid', 0, PARAM_INT);
$groupid    = optional_param('groupid', 0, PARAM_INT);
$roleid     = optional_param('roleid', 0, PARAM_INT);

require_sesskey();

$PAGE->set_context($systemcontext);

@header('Content-Type: application/json; charset=utf-8');

try {
    $filters = [
        'categoryid' => $categoryid,
        'cohortid'   => $cohortid,
        'groupid'    => $groupid,
        'roleid'     => $roleid,
    ];

    $data = local_mai_get_participation_data($courseid, $filters);

    $course          = $data['course'];
    $activos         = $data['activos'];
    $inactivos       = $data['inactivos'];
    $nuncaingresaron = $data['nuncaingresaron'];

    $formatdate = function($timestamp) {
        if (empty($timestamp)) {
            return '-';
        }
        return userdate($timestamp, get_string('strftimedatetime', 'langconfig'));
    };

    $activesout = [];
    foreach ($activos as $row) {
        $activesout[] = [
            'fullname'            => $row->fullname,
            'email'               => $row->email,
            'completedactivities' => $row->completedactivities,
            'progress'            => $row->progress,
            'lastaccess'          => $formatdate($row->lastaccess),
        ];
    }

    $inactivesout = [];
    foreach ($inactivos as $row) {
        $inactivesout[] = [
            'fullname'   => $row->fullname,
            'email'      => $row->email,
            'lastaccess' => $formatdate($row->lastaccess),
            'minutes'    => $row->minutes,
            'clicks'     => $row->clicks,
        ];
    }

    $neverout = [];
    foreach ($nuncaingresaron as $row) {
        $neverout[] = [
            'fullname'  => $row->fullname,
            'email'     => $row->email,
            'cohort'    => $row->cohort ?: '-',
            'group'     => $row->group ?: '-',
            'enroltime' => $formatdate($row->enroltime),
        ];
    }

    $totalstudents = count($activos) + count($inactivos) + count($nuncaingresaron);

    $response = [
        'coursefullname' => format_string($course->fullname),
        'counts' => [
            'active'   => count($activos),
            'inactive' => count($inactivos),
            'never'    => count($nuncaingresaron),
        ],
        'total'          => $totalstudents,
        'labels'         => ['Activos', 'Inactivos', 'Nunca ingresaron'],
        'activos'         => $activesout,
        'inactivos'       => $inactivesout,
        'nuncaingresaron' => $neverout,
    ];

    echo json_encode($response);
    die;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error'   => true,
        'message' => $e->getMessage()
    ]);
    die;
}
