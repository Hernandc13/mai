<?php
// local/mai/participacion/export.php

require(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->libdir . '/pdflib.php');
require_once($CFG->libdir . '/excellib.class.php');

require_login();

$systemcontext = context_system::instance();
require_capability('local/mai:viewreport', $systemcontext);

$format    = required_param('format', PARAM_ALPHA); // xlsx | pdf | csv
$courseid  = required_param('courseid', PARAM_INT);

$categoryid = optional_param('categoryid', 0, PARAM_INT);
$cohortid   = optional_param('cohortid', 0, PARAM_INT);
$groupid    = optional_param('groupid', 0, PARAM_INT);
$roleid     = optional_param('roleid', 0, PARAM_INT);

// Segmento a exportar: all | activos | inactivos | nunca.
// Soportamos tanto "segment" como "segmentfilter" por compatibilidad.
$segment = optional_param('segment', '', PARAM_ALPHA);
if ($segment === '') {
    $segment = optional_param('segmentfilter', 'all', PARAM_ALPHA);
}
$allowedsegments = ['all', 'activos', 'inactivos', 'nunca'];
if (!in_array($segment, $allowedsegments, true)) {
    $segment = 'all';
}

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

// Totales para el resumen.
$totalactivos   = count($activos);
$totalinactivos = count($inactivos);
$totalnunca     = count($nuncaingresaron);
$totaltodos     = $totalactivos + $totalinactivos + $totalnunca;

$formatdate = function($timestamp) {
    if (empty($timestamp)) {
        return '-';
    }
    return userdate($timestamp, '%d/%m/%Y');
};

$percent = function(int $n, int $base): int {
    if ($base <= 0) {
        return 0;
    }
    return (int)round(($n * 100) / $base);
};

// ¿Incluir columnas de minutos/clics?
$hasinactive = !empty($inactivos) && ($segment === 'all' || $segment === 'inactivos');

// =====================
// Columnas (títulos COMPLETOS)
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

if ($hasinactive) {
    $columns['minutes'] = 'Minutos (aprox.)';
    $columns['clicks']  = 'Clics';
}

// =====================
// Filas (solo alumnos, sin duplicar resumen)
// =====================
$rows = [];

$addrow = function(string $segmentlabel, stdClass $item) use (
    &$rows,
    $col_email, $col_cohort, $col_group, $col_lastaccess, $col_enroltime,
    $formatdate, $hasinactive
) {
    $row = [
        'segment'  => $segmentlabel,
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

    if ($hasinactive) {
        $row['minutes'] = $item->minutes ?? '';
        $row['clicks']  = $item->clicks ?? '';
    }

    $rows[] = $row;
};

// Activos (si el segmento lo incluye).
if ($segment === 'all' || $segment === 'activos') {
    foreach ($activos as $a) {
        $addrow('Activo', $a);
    }
}

// Inactivos (si el segmento lo incluye).
if ($segment === 'all' || $segment === 'inactivos') {
    foreach ($inactivos as $i) {
        $addrow('Inactivo', $i);
    }
}

// Nunca ingresaron (si el segmento lo incluye).
if ($segment === 'all' || $segment === 'nunca') {
    foreach ($nuncaingresaron as $n) {
        $addrow('Nunca ingresó', $n);
    }
}

// =====================
// Texto de resumen (reutilizado para PDF, Excel y CSV)
// =====================
$summarytitle = 'Resumen de participación por curso';

$segmentlabels = [
    'all'       => 'Todos los estudiantes',
    'activos'   => 'Solo activos',
    'inactivos' => 'Solo inactivos',
    'nunca'     => 'Solo nunca ingresaron',
];
$segmentlabel = $segmentlabels[$segment] ?? 'Todos los estudiantes';

$summarylines = [];
$summarylines[] = 'Segmento exportado: ' . $segmentlabel;

if ($segment === 'all') {
    $summarylines[] = 'Total inscritos: ' . $totaltodos;
    $summarylines[] = 'Activos: ' . $totalactivos . ' (' . $percent($totalactivos, $totaltodos) . '%)';
    $summarylines[] = 'Inactivos: ' . $totalinactivos . ' (' . $percent($totalinactivos, $totaltodos) . '%)';
    $summarylines[] = 'Nunca ingresaron: ' . $totalnunca . ' (' . $percent($totalnunca, $totaltodos) . '%)';
} else if ($segment === 'activos') {
    $summarylines[] = 'Total inscritos en el reporte: ' . $totalactivos;
    if ($totaltodos > 0) {
        $summarylines[] = 'Participación sobre el curso: ' .
            $percent($totalactivos, $totaltodos) . '% de los inscritos';
    }
} else if ($segment === 'inactivos') {
    $summarylines[] = 'Total inscritos en el reporte: ' . $totalinactivos;
    if ($totaltodos > 0) {
        $summarylines[] = 'Proporción de inactivos en el curso: ' .
            $percent($totalinactivos, $totaltodos) . '% de los inscritos';
    }
} else if ($segment === 'nunca') {
    $summarylines[] = 'Total inscritos en el reporte: ' . $totalnunca;
    if ($totaltodos > 0) {
        $summarylines[] = 'Nunca ingresaron (sobre el curso): ' .
            $percent($totalnunca, $totaltodos) . '% de los inscritos';
    }
}

$filenamebase = 'mai_participacion_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $course->shortname);
$today        = userdate(time(), '%Y%m%d');

// =====================
// XLSX (resumen arriba, tabla abajo)
// =====================
if ($format === 'xlsx') {
    $filename = $filenamebase . '_' . $today;

    $workbook  = new MoodleExcelWorkbook($filename);
    $workbook->send($filename . '.xlsx');
    $worksheet = $workbook->add_worksheet('Participación');

    $row = 0;

    // Resumen (columna B)
    $worksheet->write_string($row++, 1, $summarytitle);
    foreach ($summarylines as $line) {
        $worksheet->write_string($row++, 1, $line);
    }

    // Línea en blanco
    $row++;

    // Encabezados de la tabla
    $colkeys = array_keys($columns);
    $col     = 0;
    foreach ($colkeys as $key) {
        $worksheet->write_string($row, $col++, $columns[$key]);
    }
    $row++;

    // Filas de datos
    foreach ($rows as $r) {
        $col = 0;
        foreach ($colkeys as $key) {
            $value = isset($r[$key]) ? (string)$r[$key] : '';
            $worksheet->write_string($row, $col++, $value);
        }
        $row++;
    }

    $workbook->close();
    die;
}

// =====================
// CSV (resumen arriba, tabla abajo)
// =====================
if ($format === 'csv') {
    $filename = $filenamebase . '_' . $today . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');

    // BOM UTF-8 para que Excel no rompa acentos
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Resumen
    fputcsv($output, ['', $summarytitle]);
    foreach ($summarylines as $line) {
        fputcsv($output, ['', $line]);
    }

    // Línea en blanco
    fputcsv($output, []);

    // Encabezados de la tabla
    $colkeys = array_keys($columns);
    $header  = [];
    foreach ($colkeys as $key) {
        $header[] = $columns[$key];
    }
    fputcsv($output, $header);

    // Filas de datos
    foreach ($rows as $r) {
        $line = [];
        foreach ($colkeys as $key) {
            $line[] = isset($r[$key]) ? $r[$key] : '';
        }
        fputcsv($output, $line);
    }

    fclose($output);
    die;
}

// =====================
// PDF  (sin encabezados en páginas 2+)
// =====================
if ($format === 'pdf') {

    core_php_time_limit::raise(0);
    raise_memory_limit(MEMORY_EXTRA);

    $filename = $filenamebase . '_' . $today . '.pdf';

    $pdf = new pdf();
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);

    $pdf->SetCreator('Moodle');
    $pdf->SetAuthor(fullname($USER));
    $pdf->SetTitle('Reporte de participación - ' . format_string($course->fullname));
    $pdf->SetMargins(15, 20, 15);
    $pdf->AddPage();

    $margins    = $pdf->getMargins();
    $leftmargin = $margins['left'];
    $usablew    = $pdf->getPageWidth() - $margins['left'] - $margins['right'];

    // Logo personalizado (SVG)
    $logopath = $CFG->dirroot . '/local/mai/img/logo.svg';
    if (file_exists($logopath) && method_exists($pdf, 'ImageSVG')) {
        $pdf->ImageSVG(
            '@' . file_get_contents($logopath),
            $x = $leftmargin,
            $y = 10,
            $w = 26,
            $h = '',
            $link = '',
            $align = '',
            $palign = '',
            $border = 0,
            $fitonpage = false
        );
        $pdf->Ln(22);
    } else {
        $pnglogo = $CFG->dirroot . '/pix/moodlelogo.png';
        if (file_exists($pnglogo)) {
            $pdf->Image($pnglogo, $leftmargin, 10, 26);
            $pdf->Ln(20);
        }
    }

    // Título
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Reporte de participación estudiantil', 0, 1, 'C');

    // Subtítulos
    $pdf->SetFont('helvetica', '', 11);
    $pdf->Cell(0, 7, 'Curso: ' . format_string($course->fullname), 0, 1, 'C');
    $pdf->Cell(0, 7, 'Fecha de generación: ' . userdate(time(), '%d/%m/%Y %H:%M'), 0, 1, 'C');

    $pdf->Ln(6);

    // =====================
    // Resumen de participación por curso
    // =====================
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 7, $summarytitle, 0, 1, 'L');

    $pdf->SetFont('helvetica', '', 10);
    foreach ($summarylines as $line) {
        $pdf->Cell(0, 6, $line, 0, 1, 'L');
    }

    $pdf->Ln(4);

    // =====================
    // Tabla de detalle
    // =====================
    $colkeys = array_keys($columns);

    // Pesos por columna
    $weights  = [
        'segment'   => 2.0,
        'fullname'  => 2.8,
        'email'     => 2.4,
        'cohort'    => 1.6,
        'group'     => 1.6,
        'lastaccess'=> 2.6,
        'enroltime' => 2.4,
        'completed' => 2.6,
        'progress'  => 1.8,
        'minutes'   => 2.2,
        'clicks'    => 1.2,
    ];

    $sumweights = 0;
    foreach ($colkeys as $key) {
        $sumweights += $weights[$key] ?? 1;
    }

    $colwidths = [];
    foreach ($colkeys as $key) {
        $w = $weights[$key] ?? 1;
        $colwidths[$key] = $usablew * ($w / max($sumweights, 1));
    }

    $pdf->SetTextColor(0, 0, 0);

    // -------- Encabezado SOLO en la PRIMER página ----------
    $pdf->SetFillColor(240, 240, 240);
    $pdf->SetFont('helvetica', 'B', 8);

    $headerHeight = 0;
    foreach ($colkeys as $key) {
        $title = $columns[$key];
        $headerHeight = max(
            $headerHeight,
            $pdf->getStringHeight($colwidths[$key], $title)
        );
    }

    $startY = $pdf->GetY();
    $x = $leftmargin;

    foreach ($colkeys as $key) {
        $title = $columns[$key];
        $w = $colwidths[$key];

        $pdf->MultiCell(
            $w,
            $headerHeight,
            $title,
            0,
            'C',
            true,
            0,
            $x,
            $startY,
            true,
            0,
            false,
            true,
            $headerHeight,
            'M'
        );
        $x += $w;
    }

    $pdf->SetXY($leftmargin, $startY + $headerHeight + 1.5);

    // -------- Filas ----------
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetFillColor(255, 255, 255);

    $centerkeys = ['completed', 'progress', 'lastaccess', 'enroltime'];

    foreach ($rows as $row) {

        $rowHeight = 0;
        foreach ($colkeys as $key) {
            $val = isset($row[$key]) ? (string)$row[$key] : '';
            $rowHeight = max(
                $rowHeight,
                $pdf->getStringHeight($colwidths[$key], $val)
            );
        }

        $pageHeight = $pdf->getPageHeight();
        $breakY     = $pageHeight - $pdf->getBreakMargin();

        if ($pdf->GetY() + $rowHeight > $breakY) {
            // Nueva página SIN encabezados
            $pdf->AddPage();
            $pdf->SetFont('helvetica', '', 8);
        }

        $y = $pdf->GetY();
        $x = $leftmargin;

        foreach ($colkeys as $key) {
            $val = isset($row[$key]) ? (string)$row[$key] : '';
            $w   = $colwidths[$key];

            $align = in_array($key, $centerkeys, true) ? 'C' : 'L';

            $pdf->MultiCell(
                $w,
                $rowHeight,
                $val,
                0,
                $align,
                false,
                0,
                $x,
                $y,
                true,
                0,
                false,
                true,
                $rowHeight,
                'M'
            );
            $x += $w;
        }

        $pdf->SetXY($leftmargin, $y + $rowHeight + 0.5);
    }

    $pdf->Output($filename, 'D');
    die;
}

print_error('Formato de exportación no soportado');
