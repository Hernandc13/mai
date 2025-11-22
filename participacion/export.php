<?php
// local/mai/participacion/export.php

require(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->libdir . '/pdflib.php');

require_login();

$systemcontext = context_system::instance();
require_capability('local/mai:viewreport', $systemcontext);

$format    = required_param('format', PARAM_ALPHA); // xlsx | pdf | csv
$courseid  = required_param('courseid', PARAM_INT);

$categoryid = optional_param('categoryid', 0, PARAM_INT);
$cohortid   = optional_param('cohortid', 0, PARAM_INT);
$groupid    = optional_param('groupid', 0, PARAM_INT);
$roleid     = optional_param('roleid', 0, PARAM_INT);

$col_email      = optional_param('col_email', 1, PARAM_BOOL);
$col_cohort     = optional_param('col_cohort', 1, PARAM_BOOL);
$col_group      = optional_param('col_group', 1, PARAM_BOOL);
$col_lastaccess = optional_param('col_lastaccess', 1, PARAM_BOOL);
$col_enroltime  = optional_param('col_enroltime', 1, PARAM_BOOL);

require_sesskey();

$PAGE->set_context($systemcontext);

// =====================
// Filtros
// =====================
$filters = [
    'categoryid' => $categoryid,
    'cohortid'   => $cohortid,
    'groupid'    => $groupid,
    'roleid'     => $roleid,
];

// =====================
// Obtenemos datos
// =====================
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

// =====================
// Columnas
// =====================
$columns = [
    'segment'  => 'Segmento',
    'fullname' => 'Nombre completo',
];

if ($col_email) {
    $columns['email'] = 'Correo';
}
if ($col_cohort) {
    $columns['cohort'] = 'Cohorte';
}
if ($col_group) {
    $columns['group'] = 'Grupo';
}
if ($col_lastaccess) {
    $columns['lastaccess'] = 'Último acceso';
}
if ($col_enroltime) {
    $columns['enroltime'] = 'Fecha de matrícula';
}

$columns['completed'] = 'Actividades completadas';
$columns['progress']  = 'Avance (%)';
$columns['minutes']   = 'Minutos (aprox.)';
$columns['clicks']    = 'Clics';

// =====================
// Filas
// =====================
$rows = [];

$addrow = function(string $segment, stdClass $item) use (
    &$rows,
    $col_email, $col_cohort, $col_group, $col_lastaccess, $col_enroltime,
    $formatdate
) {
    $row = [
        'segment'  => $segment,
        'fullname' => $item->fullname ?? '',
    ];

    if ($col_email) {
        $row['email'] = $item->email ?? '';
    }
    if ($col_cohort) {
        $row['cohort'] = $item->cohort ?? '';
    }
    if ($col_group) {
        $row['group'] = $item->group ?? '';
    }
    if ($col_lastaccess) {
        $row['lastaccess'] = isset($item->lastaccess) ? $formatdate($item->lastaccess) : '';
    }
    if ($col_enroltime) {
        $row['enroltime'] = isset($item->enroltime) ? $formatdate($item->enroltime) : '';
    }

    $row['completed'] = $item->completedactivities ?? '';
    $row['progress']  = $item->progress ?? '';
    $row['minutes']   = $item->minutes ?? '';
    $row['clicks']    = $item->clicks ?? '';

    $rows[] = $row;
};

// Activos
foreach ($activos as $a) {
    $addrow('Activo', $a);
}

// Inactivos
foreach ($inactivos as $i) {
    $addrow('Inactivo', $i);
}

// Nunca ingresaron
foreach ($nuncaingresaron as $n) {
    $addrow('Nunca ingresó', $n);
}

$filenamebase = 'mai_participacion_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $course->shortname);
$today = userdate(time(), '%Y%m%d');

// =====================
// XLSX
// =====================
if ($format === 'xlsx') {
    $filename = $filenamebase . '_' . $today;
    \core\dataformat::download_data($filename, 'excel', $columns, $rows);
    die;
}

// =====================
// CSV
// =====================
if ($format === 'csv') {
    $filename = $filenamebase . '_' . $today;
    \core\dataformat::download_data($filename, 'csv', $columns, $rows);
    die;
}

// =====================
// PDF
// =====================
if ($format === 'pdf') {
    $filename = $filenamebase . '_' . $today . '.pdf';

    $pdf = new pdf();
    $pdf->SetCreator('Moodle');
    $pdf->SetAuthor(fullname($USER));
    $pdf->SetTitle('Reporte de participación - ' . format_string($course->fullname));
    $pdf->SetMargins(15, 20, 15);
    $pdf->AddPage();

    $logopath = $CFG->dirroot . '/pix/moodlelogo.png';
    if (file_exists($logopath)) {
        $pdf->Image($logopath, 15, 10, 26);
        $pdf->Ln(20);
    }

    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Reporte de participación estudiantil', 0, 1, 'C');

    $pdf->SetFont('helvetica', '', 11);
    $pdf->Cell(0, 7, 'Curso: ' . format_string($course->fullname), 0, 1, 'C');
    $pdf->Cell(0, 7, 'Fecha de generación: ' . userdate(time(), '%d/%m/%Y %H:%M'), 0, 1, 'C');

    $pdf->Ln(5);

    $html  = '<table border="1" cellpadding="4">';
    $html .= '<thead><tr style="background-color:#f0f0f0;">';
    foreach ($columns as $key => $title) {
        $html .= '<th><b>' . s($title) . '</b></th>';
    }
    $html .= '</tr></thead><tbody>';

    foreach ($rows as $row) {
        $html .= '<tr>';
        foreach ($columns as $key => $title) {
            $val = isset($row[$key]) ? $row[$key] : '';
            $html .= '<td>' . s((string)$val) . '</td>';
        }
        $html .= '</tr>';
    }

    $html .= '</tbody></table>';

    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output($filename, 'D');
    die;
}

print_error('Formato de exportación no soportado');
