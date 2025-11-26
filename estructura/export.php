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
$groupid   = optional_param('groupid', 0, PARAM_INT); // filtro grupo

require_sesskey();
$PAGE->set_context($systemcontext);

// Obtenemos stats (ya con filtro de grupo).
$data         = local_mai_estructura_get_stats($programid, $termid, $teacherid, $groupid);
$view         = $data['view'];
$global       = $data['global'];
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
        'group'     => 'Grupo',
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
            'group'     => $groupname,
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
// PDF con mismo diseño que participación
// - Logo SVG local/mai/img/logo.svg
// - Título y subtítulos centrados
// - Tabla con MultiCell y SIN encabezados en páginas 2+
// ===============================
if ($format === 'pdf') {

    core_php_time_limit::raise(0);
    raise_memory_limit(MEMORY_EXTRA);

    $filename = $filenamebase . '_' . $today . '.pdf';

    $pdf = new pdf();
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);

    $pdf->SetCreator('Moodle');
    $pdf->SetAuthor(fullname($USER));
    $pdf->SetTitle('Reporte de estructura académica - Monitoreo MAI');
    $pdf->SetMargins(15, 20, 15);
    $pdf->AddPage();

    $margins    = $pdf->getMargins();
    $leftmargin = $margins['left'];
    $usablew    = $pdf->getPageWidth() - $margins['left'] - $margins['right'];

    // Logo personalizado (igual que participación).
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
        // Fallback a logo de Moodle.
        $pnglogo = $CFG->dirroot . '/pix/moodlelogo.png';
        if (file_exists($pnglogo)) {
            $pdf->Image($pnglogo, $leftmargin, 10, 26);
            $pdf->Ln(20);
        }
    }

    // Título principal
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Reporte de estructura académica', 0, 1, 'C');

    // Subtítulos según vista
    $pdf->SetFont('helvetica', '', 11);
    if ($view === 'term') {
        $line = 'Programa: ' . ($context['programname'] ?? '(sin programa)') .
            ' | Cuatrimestre: ' . ($context['termname'] ?? '(sin cuatrimestre)');
        if (!empty($context['teachername'])) {
            $line .= ' | Docente: ' . $context['teachername'];
        }
        if (!empty($context['groupname'])) {
            $line .= ' | Grupo: ' . $context['groupname'];
        }
        $pdf->Cell(0, 7, $line, 0, 1, 'C');
    } else {
        $pdf->Cell(0, 7, 'Vista por programa académico', 0, 1, 'C');
    }

    $pdf->Cell(0, 7, 'Fecha de generación: ' . userdate(time(), '%d/%m/%Y %H:%M'), 0, 1, 'C');

    $pdf->Ln(6);

    // Si no hay filas, generamos PDF con mensaje y salimos.
    if (empty($rows)) {
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 8, 'No hay datos para los filtros seleccionados.', 0, 1, 'C');
        $pdf->Output($filename, 'D');
        die;
    }

    $colkeys = array_keys($columns);

    // Pesos por columna (similar concepto al de participación).
    // Estas proporciones se usan para repartir el ancho disponible.
    $weights = [
        'program'   => 2.4,
        'term'      => 2.0,
        'group'     => 1.6,
        'course'    => 3.0,
        'courses'   => 1.6,
        'active'    => 1.4,
        'inactive'  => 1.4,
        'never'     => 1.4,
        'total'     => 1.6,
        'retention' => 1.6,
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
    $x      = $leftmargin;

    foreach ($colkeys as $key) {
        $title = $columns[$key];
        $w     = $colwidths[$key];

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

    // Columnas numéricas centradas
    $centerkeys = ['courses', 'active', 'inactive', 'never', 'total', 'retention'];

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
            // Nueva página SIN encabezados (mismo comportamiento que participación).
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
