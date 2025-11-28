<?php
// local/mai/classes/task/send_scheduled_reports.php
//
// Tarea programada para envío automatizado de reportes MAI por regla
// (programa + cuatrimestre + cursos).
//

namespace local_mai\task;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->libdir . '/pdflib.php');
require_once($CFG->libdir . '/excellib.class.php');
require_once($CFG->libdir . '/enrollib.php');

use core\task\scheduled_task;

/**
 * Tarea programada para envío automatizado de reportes MAI.
 *
 * @package   local_mai
 */
class send_scheduled_reports extends scheduled_task {

    public function get_name() {
        return get_string('task_send_scheduled_reports', 'local_mai');
    }

    public function execute() {
        global $DB, $CFG;

        mtrace('[local_mai] Ejecutando tarea: send_scheduled_reports');

        // Reglas activas (enabled=1).
        $rules = $DB->get_records('local_mai_notif_rules', ['enabled' => 1], 'id ASC');

        if (empty($rules)) {
            mtrace('[local_mai] No hay reglas de programación activas.');
            return;
        }

        $now = time();

        foreach ($rules as $rule) {
            mtrace('--------------------------------------------------');
            mtrace('[local_mai] Procesando regla ID ' . $rule->id . ' - "' . $rule->name . '" (reportes)');

            // 1) ¿La regla tiene reportes activos?
            // OJO: el campo de BD es "reportenabled" (sin "s").
            if (empty($rule->reportenabled)) {
                mtrace('[local_mai] Reportes desactivados para esta regla (reportenabled=0).');
                continue;
            }

            // 2) ¿Toca enviar según la frecuencia y el último envío?
            $last = (int)($rule->last_report_sent ?? 0);
            $diff = $now - $last;
            $freq = $rule->report_frequency ?? 'weekly';

            $shouldsend = false;
            if (empty($last)) {
                // Nunca se ha enviado: se permite el primer envío.
                $shouldsend = true;
            } else {
                switch ($freq) {
                    case 'daily':
                        $shouldsend = ($diff >= DAYSECS);
                        break;
                    case 'weekly':
                        $shouldsend = ($diff >= WEEKSECS);
                        break;
                    case 'monthly':
                        $shouldsend = ($diff >= 30 * DAYSECS);
                        break;
                    default:
                        // Frecuencia desconocida -> no enviamos.
                        $shouldsend = false;
                }
            }

            if (!$shouldsend) {
                mtrace('[local_mai] No corresponde enviar reporte para esta regla según la frecuencia (' . $freq . ').');
                continue;
            }

            // 3) Destinatarios.
            if (empty($rule->report_recipients)) {
                mtrace('[local_mai] No hay destinatarios configurados en esta regla.');
                continue;
            }

            $recipients = preg_split('/[,;]+/', $rule->report_recipients, -1, PREG_SPLIT_NO_EMPTY);
            $recipients = array_map('trim', $recipients);
            if (empty($recipients)) {
                mtrace('[local_mai] Lista de destinatarios vacía después de parsear en esta regla.');
                continue;
            }

            // 4) Resolver cursos monitoreados según la regla.
            $courseids = $this->resolve_courses_for_rule($rule);

            if (empty($courseids)) {
                mtrace('[local_mai] La regla no tiene cursos visibles en el ámbito definido.');
                continue;
            }

            $courseids = array_values(array_unique(array_map('intval', $courseids)));

            // Obtenemos los cursos ordenados por nombre.
            list($insql, $params) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'cid');
            $courses = $DB->get_records_select(
                'course',
                "id $insql",
                $params,
                'fullname ASC',
                'id, fullname, shortname'
            );

            if (empty($courses)) {
                mtrace('[local_mai] No hay cursos visibles en esta regla para generar reporte.');
                continue;
            }

            mtrace('[local_mai] Cursos monitoreados en esta regla: ' . count($courseids) .
                ' (' . implode(',', $courseids) . ')');

            // Ventana: "activo" = últimos 30 días.
            $cutoff        = $now - (30 * DAYSECS);
            $courseblocks  = [];
            $totalcourses  = 0;
            $totalactive   = 0;
            $totalinactive = 0;

            // 5) Construir bloques por curso.
            foreach ($courses as $course) {
                $context = \context_course::instance($course->id);

                // Usuarios matriculados.
                $enrolled = get_enrolled_users(
                    $context,
                    '',
                    0,
                    'u.id, u.firstname, u.lastname, u.email',
                    'u.lastname, u.firstname'
                );

                if (empty($enrolled)) {
                    continue; // Curso sin alumnos.
                }

                $totalcourses++;

                // Último acceso por curso.
                $accesses = $DB->get_records(
                    'user_lastaccess',
                    ['courseid' => $course->id],
                    '',
                    'userid, timeaccess'
                );

                $accessmap = [];
                foreach ($accesses as $a) {
                    $accessmap[$a->userid] = $a->timeaccess;
                }

                $rows = [];
                foreach ($enrolled as $u) {
                    $timeaccess = $accessmap[$u->id] ?? 0;

                    if ($timeaccess == 0) {
                        $segment = 'Nunca ingresó';
                        $totalinactive++;
                    } else if ($timeaccess > $cutoff) {
                        $segment = 'Activo';
                        $totalactive++;
                    } else {
                        $segment = 'Inactivo';
                        $totalinactive++;
                    }

                    $rows[] = (object)[
                        'segment'    => $segment,
                        'fullname'   => fullname($u),
                        'email'      => $u->email,
                        'lastaccess' => $timeaccess,
                    ];
                }

                if (!empty($rows)) {
                    $courseblocks[$course->id] = [
                        'course' => $course,
                        'rows'   => $rows,
                    ];
                }
            }

            if (empty($courseblocks)) {
                mtrace('[local_mai] No se encontraron usuarios matriculados en los cursos de esta regla.');
                continue;
            }

            // 6) Resumen por curso para el correo.
            $coursesdetailhtml = '<ul style="margin:4px 0 0 18px;padding:0;font-size:13px;color:#111827;">';

            foreach ($courseblocks as $block) {
                $course = $block['course'];
                $rows   = $block['rows'];

                $cactive   = 0;
                $cinactive = 0;

                foreach ($rows as $r) {
                    if ($r->segment === 'Activo') {
                        $cactive++;
                    } else {
                        $cinactive++;
                    }
                }

                $coursesdetailhtml .= '<li><strong>' . s($course->fullname) . '</strong>: ' .
                    $cactive . ' activos, ' . $cinactive . ' inactivos</li>';
            }

            $coursesdetailhtml .= '</ul>';

            $summarydata = [
                'active'         => $totalactive,
                'inactive'       => $totalinactive,
                'courses'        => $totalcourses,
                'courses_detail' => $coursesdetailhtml,
            ];

            // 7) Generar archivo adjunto (PDF o XLSX) según la regla.
            $format = $rule->report_format ?? 'pdf';

            if ($format === 'xlsx') {
                list($attachmentpath, $attachmentname) = $this->generate_xlsx_report($courseblocks);
            } else {
                list($attachmentpath, $attachmentname) = $this->generate_pdf_report($courseblocks);
            }

            // 8) Construir correo usando la plantilla de la regla.
            $innerhtml = $rule->report_template ?? '';

            if (empty($innerhtml)) {
                $innerhtml = '
<p style="margin:0 0 8px;">Resumen del periodo:</p>
<ul style="margin:0 0 12px 18px;padding:0;font-size:13px;color:#111827;">
  <li><strong>Estudiantes activos:</strong> {{active}}</li>
  <li><strong>Estudiantes inactivos:</strong> {{inactive}}</li>
  <li><strong>Cursos monitoreados:</strong> {{courses}}</li>
</ul>
<p style="margin:0 0 4px;font-size:13px;color:#111827;">Cursos monitoreados:</p>
{{courses_detail}}';
            }

            $bodyhtml = '
<div style="font-family:system-ui,Arial,sans-serif;max-width:640px;margin:0 auto;padding:16px;background:#f3f4f6;">
  <div style="background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e5e7eb;box-shadow:0 16px 30px rgba(15,23,42,0.12);">
    <div style="background:#8C253E;color:#ffffff;padding:12px 20px;border-bottom:4px solid #FF7000;text-align:center;">
      <div style="font-size:16px;font-weight:600;margin-top:4px;">Reporte automático - Participación</div>
      <div style="font-size:11px;opacity:0.85;margin-top:2px;">Resumen de participación del periodo.</div>
    </div>
    <div style="padding:16px 20px;font-size:13px;color:#111827;line-height:1.5;">
      ' . $innerhtml . '
      <p style="margin:12px 0 0;font-size:12px;color:#6b7280;">El detalle de participación por curso se encuentra en el archivo adjunto.</p>
    </div>
    <div style="padding:10px 20px 14px;font-size:11px;color:#9ca3af;text-align:center;border-top:1px solid #f3f4f6;">
      Mensaje generado automáticamente desde el módulo MAI.
    </div>
  </div>
</div>';

            foreach ($summarydata as $k => $v) {
                $bodyhtml = str_replace('{{' . $k . '}}', $v, $bodyhtml);
            }

            $bodytext    = html_to_text($bodyhtml, 0, false);
            $subject     = 'Reporte automático MAI - Participación';
            $supportuser = \core_user::get_support_user();

            // 9) Enviar a todos los destinatarios de la regla.
            foreach ($recipients as $email) {
                if (empty($email)) {
                    continue;
                }

                $fakeuser = (object)[
                    'id'         => -1,
                    'email'      => $email,
                    'username'   => $email,
                    'firstname'  => 'Coordinador',
                    'lastname'   => 'MAI',
                    'mailformat' => 1,
                ];

                $sent = email_to_user(
                    $fakeuser,
                    $supportuser,
                    $subject,
                    $bodytext,
                    $bodyhtml,
                    $attachmentpath,
                    $attachmentname
                );

                mtrace('[local_mai] Envío de reporte (regla ' . $rule->id . ') a ' . $email . ': ' . ($sent ? 'OK' : 'FALLÓ'));
            }

            // 10) Marcar timestamp de último envío para esta regla.
            $rule->last_report_sent = $now;
            $rule->timemodified    = $now;
            $DB->update_record('local_mai_notif_rules', $rule);

            mtrace('[local_mai] Regla ID ' . $rule->id . ' procesada (reportes).');
        }

        mtrace('[local_mai] Tarea send_scheduled_reports finalizada.');
    }

    /**
     * Resuelve los IDs de cursos visibles que aplican a una regla.
     *
     * Prioridad:
     *  1) monitored_courses explícitos
     *  2) cursos del cuatrimestre (termid)
     *  3) cursos del programa (programid + sus subcategorías directas)
     *  4) todos los cursos visibles (excepto el curso sitio)
     *
     * @param \stdClass $rule
     * @return int[]
     */
    protected function resolve_courses_for_rule(\stdClass $rule): array {
        global $DB;

        // 1) Cursos seleccionados explícitamente.
        if (!empty($rule->monitored_courses)) {
            $ids = array_unique(array_filter(array_map('intval', explode(',', $rule->monitored_courses))));
            if ($ids) {
                list($inSql, $params) = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
                $params['visible'] = 1;
                $params['siteid']  = 1;
                return $DB->get_fieldset_select(
                    'course',
                    'id',
                    "visible = :visible AND id <> :siteid AND id $inSql",
                    $params
                );
            }
        }

        // 2) Cuatrimestre concreto.
        if (!empty($rule->termid)) {
            return $DB->get_fieldset_select(
                'course',
                'id',
                'category = :cat AND visible = 1 AND id <> 1',
                ['cat' => $rule->termid]
            );
        }

        // 3) Programa: cursos en la categoría raíz + sus subcategorías directas.
        if (!empty($rule->programid)) {
            $termcats = $DB->get_fieldset_select(
                'course_categories',
                'id',
                'parent = :p',
                ['p' => $rule->programid]
            );

            $catids   = $termcats;
            $catids[] = (int)$rule->programid;
            $catids   = array_unique(array_filter($catids));

            if ($catids) {
                list($inSql, $params) = $DB->get_in_or_equal($catids, SQL_PARAMS_NAMED);
                $params['visible'] = 1;
                $params['siteid']  = 1;
                return $DB->get_fieldset_select(
                    'course',
                    'id',
                    "visible = :visible AND id <> :siteid AND category $inSql",
                    $params
                );
            }
        }

        // 4) Fallback: todos los cursos visibles (excepto el curso sitio).
        return $DB->get_fieldset_select(
            'course',
            'id',
            'visible = 1 AND id <> 1',
            []
        );
    }

    /**
     * Genera un PDF con un bloque por curso.
     *
     * @param array $courseblocks
     * @return array [ruta, nombrearchivo]
     */
    protected function generate_pdf_report(array $courseblocks): array {
        global $CFG;

        $pdf = new \pdf();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetFont('helvetica', '', 9);

        $logopath = $CFG->dirroot . '/local/mai/img/logo.svg';
        $haslogo  = file_exists($logopath);

        foreach ($courseblocks as $block) {
            $course = $block['course'];
            $rows   = $block['rows'];

            $pdf->AddPage();

            if ($haslogo) {
                $pdf->ImageSVG($logopath, 15, 10, 35);
                $pdf->SetY(15);
            } else {
                $pdf->SetY(20);
            }

            $pdf->SetFont('helvetica', 'B', 14);
            $pdf->Cell(0, 6, 'Reporte de participación estudiantil', 0, 1, 'C');

            $pdf->SetFont('helvetica', '', 11);
            $pdf->Cell(0, 6, 'Curso: ' . $course->fullname, 0, 1, 'C');

            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(0, 5, 'Fecha de generación: ' . userdate(time()), 0, 1, 'C');

            $pdf->Ln(4);

            $html  = '<table cellspacing="0" cellpadding="3" width="100%">';
            $html .= '<tr style="font-weight:bold;background-color:#f3f4f6;border-bottom:0.5px solid #000;">';
            $html .= '<td width="16%">Segmento</td>';
            $html .= '<td width="30%">Nombre completo</td>';
            $html .= '<td width="30%">Correo</td>';
            $html .= '<td width="24%">Último acceso</td>';
            $html .= '</tr>';

            foreach ($rows as $r) {
                $last = $r->lastaccess ? userdate($r->lastaccess) : '-';

                $html .= '<tr style="border-bottom:0.5px solid #e5e7eb;">';
                $html .= '<td>' . s($r->segment) . '</td>';
                $html .= '<td>' . s($r->fullname) . '</td>';
                $html .= '<td>' . s($r->email) . '</td>';
                $html .= '<td>' . s($last) . '</td>';
                $html .= '</tr>';
            }

            $html .= '</table>';

            $pdf->writeHTML($html, true, false, true, false, '');
        }

        $tempdir  = make_temp_directory('local_mai_reports');
        $filename = 'reporte_participacion_' . date('Ymd_His') . '.pdf';
        $fullpath = $tempdir . '/' . $filename;

        $pdf->Output($fullpath, 'F');

        return [$fullpath, $filename];
    }

    /**
     * Genera un Excel con una pestaña por curso.
     *
     * @param array $courseblocks
     * @return array [ruta, nombrearchivo]
     */
    protected function generate_xlsx_report(array $courseblocks): array {
        // Carpeta temporal de Moodle: $CFG->tempdir/local_mai_reports.
        $tempdir  = make_temp_directory('local_mai_reports');
        $filename = 'reporte_participacion_' . date('Ymd_His') . '.xlsx';
        $fullpath = $tempdir . '/' . $filename;

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

        // Quitamos la hoja por defecto y luego creamos una por curso.
        $spreadsheet->removeSheetByIndex(0);

        $usednames = [];

        foreach ($courseblocks as $block) {
            $course = $block['course'];
            $rows   = $block['rows'];

            $basename = $course->fullname ?: $course->fullname;
            $basename = clean_param($basename, PARAM_NOTAGS);
            $basename = preg_replace('/[:\\\\\\/\\?\\*\\[\\]]/', '', $basename);
            $basename = trim($basename);
            if ($basename === '') {
                $basename = 'Curso ' . $course->id;
            }

            $base = \core_text::substr($basename, 0, 28);
            $sheetname = $base;
            $suffix = 1;
            while (isset($usednames[$sheetname])) {
                $suffix++;
                $extra = ' (' . $suffix . ')';
                $sheetname = \core_text::substr($base, 0, 31 - strlen($extra)) . $extra;
            }
            $usednames[$sheetname] = true;

            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle($sheetname);

            // Encabezados.
            $sheet->setCellValue('A1', 'Segmento');
            $sheet->setCellValue('B1', 'Nombre completo');
            $sheet->setCellValue('C1', 'Correo');
            $sheet->setCellValue('D1', 'Último acceso');

            $sheet->getStyle('A1:D1')->getFont()->setBold(true);
            $sheet->getStyle('A1:D1')->getAlignment()->setHorizontal(
                \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER
            );

            $rownum = 2;
            foreach ($rows as $r) {
                $last = $r->lastaccess ? userdate($r->lastaccess) : '-';

                $sheet->setCellValue('A' . $rownum, $r->segment);
                $sheet->setCellValue('B' . $rownum, $r->fullname);
                $sheet->setCellValue('C' . $rownum, $r->email);
                $sheet->setCellValue('D' . $rownum, $last);

                $rownum++;
            }

            $sheet->getColumnDimension('A')->setWidth(14);
            $sheet->getColumnDimension('B')->setWidth(28);
            $sheet->getColumnDimension('C')->setWidth(30);
            $sheet->getColumnDimension('D')->setWidth(18);
        }

        if ($spreadsheet->getSheetCount() === 0) {
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle('Reporte');
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($fullpath);

        return [$fullpath, $filename];
    }
}
