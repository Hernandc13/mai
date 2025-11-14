<?php
// local/mai/estructura/export.php

require(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->libdir . '/pdflib.php');

require_login();

$systemcontext = context_system::instance();
require_capability('local/mai:viewreport', $systemcontext);

$format    = required_param('format', PARAM_ALPHA); // xlsx | csv | pdf
$programid = optional_param('programid', 0, PARAM_INT);
$termid    = optional_param('termid', 0, PARAM_INT);
$teacherid = optional_param('teacherid', 0, PARAM_INT);
$groupid   = optional_param('groupid', 0, PARAM_INT); // 👈 nuevo filtro grupo

require_sesskey();
$PAGE->set_context($systemcontext);

// Obtenemos stats (ya con filtro de grupo).
$data   = local_mai_estructura_get_stats($programid, $termid, $teacherid, $groupid);
$view   = $data['view'];
$global = $data['global'];
$programstats = $data['programstats'];
$termstats    = $data['termstats'];
$termcourses  = $data['termcourses'];
$context      = $data['context'];

$filenamebase = 'mai_estructura';
$today        = userdate(time(), '%Y%m%d');

// ===============================
// Armamos columnas/filas según vista
// ===============================

$columns = [];
$rows    = [];

if ($view === 'term' && !empty($termcourses)) {
    // Export vista cuatrimestre (detalle por curso).
    $columns = [
        'program'   => 'Programa',
        'term'      => 'Cuatrimestre',
        'group'     => 'Grupo',          // 👈 nueva columna
        'course'    => 'Curso',
        'active'    => 'Activos',
        'inactive'  => 'Inactivos',
        'never'     => 'Nunca ingresó',
        'total'     => 'Matrículas',
        'retention' => 'Retención (%)',
    ];

    $groupname = $context['groupname'] ?? '';

    foreach ($termcourses as $c) {
        $rows[] = [
            'program'   => $context['programname'] ?? '',
            'term'      => $context['termname'] ?? '',
            'group'     => $groupname,         // mismo grupo para todas las filas en este filtro
            'course'    => $c['fullname'],
            'active'    => $c['active'],
            'inactive'  => $c['inactive'],
            'never'     => $c['never'],
            'total'     => $c['total'],
            'retention' => $c['retention'],
        ];
    }

    $filenamebase .= '_term';
} else {
    // Export vista general / por programa (ya viene filtrada por grupo si se pasó groupid).
    $columns = [
        'program'   => 'Programa',
        'courses'   => 'Cursos',
        'active'    => 'Activos',
        'inactive'  => 'Inactivos',
        'never'     => 'Nunca ingresó',
        'total'     => 'Matrículas',
        'retention' => 'Retención (%)',
    ];

    foreach ($programstats as $ps) {
        $rows[] = [
            'program'   => $ps['name'],
            'courses'   => $ps['courses'],
            'active'    => $ps['active'],
            'inactive'  => $ps['inactive'],
            'never'     => $ps['never'],
            'total'     => $ps['total'],
            'retention' => $ps['retention'],
        ];
    }

    $filenamebase .= '_programas';
}

// Si quieres distinguir exportes por grupo en el nombre del archivo:
if ($groupid) {
    $filenamebase .= '_grupo';
}

// ===============================
// Excel / CSV vía core\dataformat
// ===============================
if ($format === 'xlsx') {
    $filename = $filenamebase . '_' . $today;
    \core\dataformat::download_data($filename, 'excel', $columns, $rows);
    die;
}

if ($format === 'csv') {
    $filename = $filenamebase . '_' . $today;
    \core\dataformat::download_data($filename, 'csv', $columns, $rows);
    die;
}

// ===============================
// PDF simple con encabezado + tabla
// ===============================
if ($format === 'pdf') {
    $filename = $filenamebase . '_' . $today . '.pdf';

    $pdf = new pdf();
    $pdf->SetCreator('Moodle');
    $pdf->SetAuthor(fullname($USER));
    $pdf->SetTitle('Estructura académica - Monitoreo MAI');
    $pdf->SetMargins(15, 20, 15);
    $pdf->AddPage();

    $logopath = $CFG->dirroot . '/pix/moodlelogo.png';
    if (file_exists($logopath)) {
        $pdf->Image($logopath, 15, 10, 26);
        $pdf->Ln(20);
    }

    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Estructura académica - participación', 0, 1, 'C');

    $pdf->SetFont('helvetica', '', 11);
    if ($view === 'term') {
        $line = 'Programa: ' . ($context['programname'] ?? '') .
            ' | Cuatrimestre: ' . ($context['termname'] ?? '');
        if (!empty($context['teachername'])) {
            $line .= ' | Docente: ' . $context['teachername'];
        }
        if (!empty($context['groupname'])) { // 👈 mostramos grupo si aplica
            $line .= ' | Grupo: ' . $context['groupname'];
        }
        $pdf->Cell(0, 7, $line, 0, 1, 'C');
    } else {
        $pdf->Cell(0, 7, 'Vista por programa académico', 0, 1, 'C');
    }
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
