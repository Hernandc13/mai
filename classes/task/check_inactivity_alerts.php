<?php
// local/mai/classes/task/check_inactivity_alerts.php
//
// Tarea programada para revisar inactividad y enviar alertas MAI.
//

namespace local_mai\task;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/local/mai/notificaciones/lib.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->libdir . '/pdflib.php');
require_once($CFG->libdir . '/excellib.class.php');

use core\task\scheduled_task;

/**
 * Tarea programada para revisar inactividad y disparar alertas MAI.
 *
 * @package   local_mai
 */
class check_inactivity_alerts extends scheduled_task {

    public function get_name() {
        return get_string('task_check_inactividad_alerts', 'local_mai');
    }

    public function execute() {
        global $DB, $CFG;

        mtrace('[local_mai] Ejecutando tarea: check_inactividad_alerts');

        $settings = \local_mai_notificaciones_get_settings();

        if (!\local_mai_notificaciones_should_check_alerts($settings)) {
            mtrace('[local_mai] No corresponde revisar alertas en este momento.');
            return;
        }

        if (empty($settings->alerts_enabled)) {
            mtrace('[local_mai] Sistema de alertas desactivado.');
            \local_mai_notificaciones_mark_alerts_checked();
            return;
        }

        $days      = max(1, (int)$settings->alert_days_inactive);
        $threshold = max(0, (int)$settings->alert_group_inactivity);
        $cutoff    = time() - ($days * DAYSECS);

        // Canales configurados: "email,internal"
        $channels = array_filter(array_map('trim', explode(',', $settings->alert_channels ?? '')));
        $channels = array_map('strtolower', $channels);

        $studentmsg  = $settings->alert_student_message ?? '{{fullname}}, te invitamos a continuar tus actividades en la plataforma. Tu progreso es importante.';
        $coordmsg    = $settings->alert_coord_message   ?? 'Se ha detectado un grupo con alta inactividad. Te sugerimos revisar las actividades y contactar a los estudiantes.';
        $supportuser = \core_user::get_support_user();

        // ======================================================
        // Determinar cursos monitoreados desde la configuración real.
        // ======================================================
        $monitoredcourseids = [];
        $monitoredconfig = get_config('local_mai', 'monitored_courses'); // "3,5" o vacío

        if (!empty($monitoredconfig)) {
            $monitoredcourseids = array_filter(array_map('intval', explode(',', $monitoredconfig)));
        }

        mtrace('[local_mai] Config monitored_courses (raw): ' . ($monitoredconfig === null ? 'NULL' : '"' . $monitoredconfig . '"'));

        // Construir lista de usuarios matriculados en esos cursos.
        $monitoreduserids = [];
        if (!empty($monitoredcourseids)) {
            foreach ($monitoredcourseids as $cid) {
                $context = \context_course::instance($cid);
                $users   = get_enrolled_users($context, '', 0, 'u.id', 'u.id');
                foreach ($users as $u) {
                    $monitoreduserids[$u->id] = true;
                }
            }
        }
        $monitoreduserids = array_keys($monitoreduserids);

        mtrace("[local_mai] Umbral estudiante: {$days} días, umbral grupo: {$threshold}%. Cursos monitoreados: "
            . (empty($monitoredcourseids) ? 'TODOS' : implode(',', $monitoredcourseids)));

        // ======================================================
        // 1) Estudiantes inactivos: respetar canales (email/internal).
        // ======================================================

        // Añadimos todos los campos de nombre que quiere fullname() para evitar warnings.
        $userfields = 'id, firstname, lastname, email, lastaccess,
                       firstnamephonetic, lastnamephonetic, middlename, alternatename';

        $inactiveusers = [];

        if (!empty($monitoreduserids)) {
            list($insql, $params) = $DB->get_in_or_equal($monitoreduserids, SQL_PARAMS_NAMED, 'uid');
            $params['cutoff'] = $cutoff;

            $select = "id $insql
                       AND deleted = 0
                       AND suspended = 0
                       AND (lastaccess = 0 OR lastaccess <= :cutoff)";

            $inactiveusers = $DB->get_records_select('user', $select, $params,
                'lastname, firstname', $userfields);
        } else {
            $inactiveusers = $DB->get_records_select('user',
                'deleted = 0 AND suspended = 0 AND (lastaccess = 0 OR lastaccess <= :cutoff)',
                ['cutoff' => $cutoff],
                'lastname, firstname',
                $userfields
            );
        }

        if (!empty($inactiveusers) && !empty($channels)) {
            mtrace('[local_mai] Usuarios inactivos detectados en cursos monitoreados: ' . count($inactiveusers));

            foreach ($inactiveusers as $u) {

                // ==================================================
                // Cursos del usuario (para mostrar en el mensaje).
                // ==================================================
                $usercourses = enrol_get_users_courses($u->id, true, 'id, fullname');
                $coursenames = [];
                foreach ($usercourses as $c) {
                    if (!empty($monitoredcourseids) && !in_array($c->id, $monitoredcourseids)) {
                        continue;
                    }
                    $coursenames[] = format_string($c->fullname, true,
                        ['context' => \context_course::instance($c->id)]);
                }

                $courseslinehtml = '';
                if (!empty($coursenames)) {
                    if (count($coursenames) === 1) {
                        $courseslinehtml = '<p style="margin:10px 0 0;font-size:13px;color:#111827;"><strong>Curso:</strong> '
                            . s($coursenames[0]) . '</p>';
                    } else {
                        $courseslinehtml = '<p style="margin:10px 0 0;font-size:13px;color:#111827;"><strong>Cursos:</strong> '
                            . s(implode(', ', $coursenames)) . '</p>';
                    }
                }

                // Contenido base con nombre sustituido.
                $bodycontent = str_replace(
                    ['{{fullname}}', '{{fulname}}'],
                    fullname($u),
                    $studentmsg
                );

                // ==========================
                // Email al estudiante (diseño MAI)
                // ==========================
                $messagehtml = '
<div style="font-family:system-ui,Arial,sans-serif;max-width:640px;margin:0 auto;padding:16px;background:#f3f4f6;">
  <div style="background:#ffffff;border-radius:12px;border:1px solid #e5e7eb;overflow:hidden;">
    <div style="background:#8C253E;color:#ffffff;padding:12px 18px;text-align:center;border-bottom:3px solid #FF7000;">
      <h2 style="margin:0;font-size:16px;font-weight:600;">Recordatorio de actividades en la plataforma</h2>
    </div>
    <div style="padding:18px;">
      <div style="margin:0 0 10px;font-size:13px;color:#111827;line-height:1.6;">
        ' . $bodycontent . '
      </div>'
      . $courseslinehtml . '
      <p style="margin:16px 0 0;font-size:12px;color:#6b7280;">
        Si ya retomaste tus actividades recientemente, puedes ignorar este mensaje.
      </p>
    </div>
  </div>
  <p style="margin:12px 0 0;font-size:11px;color:#9ca3af;text-align:center;">
    Mensaje generado autom&aacute;ticamente desde el m&oacute;dulo MAI.
  </p>
</div>';

                $messagetext = html_to_text($messagehtml, 0, false);
                $subject    = 'Recordatorio de actividades en la plataforma';

                // Canal: interno (notificación / mensaje).
                if (in_array('internal', $channels)) {
                    $eventdata = new \core\message\message();
                    $eventdata->component         = 'moodle';
                    $eventdata->name              = 'instantmessage';
                    $eventdata->userfrom          = $supportuser;
                    $eventdata->userto            = $u;
                    $eventdata->subject           = $subject;
                    $eventdata->courseid          = SITEID;
                    $eventdata->fullmessage       = $messagetext;
                    $eventdata->fullmessageformat = FORMAT_PLAIN;
                    $eventdata->fullmessagehtml   = $messagehtml;
                    $eventdata->smallmessage      = 'Recordatorio de actividades en la plataforma.';
                    $eventdata->notification      = 1;

                    $result = message_send($eventdata);
                    mtrace('[local_mai] Notificación interna (moodle/instantmessage) a user ' . $u->id . ': ' . ($result ? 'OK' : 'FAIL'));
                }
            }
        } else if (empty($inactiveusers)) {
            mtrace('[local_mai] No se encontraron usuarios inactivos que cumplan el umbral en cursos monitoreados.');
        } else {
            mtrace('[local_mai] No hay canales de alerta configurados para el estudiante.');
        }

        // ======================================================
        // 2) Alertas por curso + grupo al coordinador (con adjunto).
        // ======================================================
        $groupsummary = [];

        $coursesfilter = '';
        $params = [];
        if (!empty($monitoredcourseids)) {
            list($insql, $params) = $DB->get_in_or_equal($monitoredcourseids, SQL_PARAMS_NAMED, 'cid');
            $coursesfilter = "AND c.id $insql";
        }

        $sqlgroups = "SELECT g.id, g.courseid, g.name, c.fullname AS coursename
                        FROM {groups} g
                        JOIN {course} c ON c.id = g.courseid
                       WHERE c.id <> 1 $coursesfilter";
        $groups = $DB->get_records_sql($sqlgroups, $params);

        foreach ($groups as $g) {
            $sqlmembers = "SELECT u.id, u.firstname, u.lastname, u.email, u.lastaccess,
                                  u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename
                             FROM {groups_members} gm
                             JOIN {user} u ON u.id = gm.userid
                            WHERE gm.groupid = :gid
                              AND u.deleted = 0
                              AND u.suspended = 0";
            $members = $DB->get_records_sql($sqlmembers, ['gid' => $g->id]);

            $total = count($members);
            if ($total === 0) {
                continue;
            }

            $inactive        = 0;
            $membersdetails  = [];

            foreach ($members as $u) {
                if ($u->lastaccess == 0) {
                    $segment = 'Nunca ingresó';
                    $inactive++;
                } else if ($u->lastaccess <= $cutoff) {
                    $segment = 'Inactivo';
                    $inactive++;
                } else {
                    $segment = 'Activo';
                }

                $membersdetails[] = (object)[
                    'fullname'   => fullname($u),
                    'email'      => $u->email,
                    'lastaccess' => $u->lastaccess,
                    'segment'    => $segment,
                ];
            }

            $percent = ($inactive * 100) / $total;

            if ($percent >= $threshold) {
                $groupsummary[] = [
                    'course'  => $g->coursename,
                    'group'   => $g->name,
                    'percent' => round($percent, 1),
                    'members' => $membersdetails,
                ];
            }
        }

        $alertrecipients = $settings->alert_recipients ?? '';

        // Generar adjunto (detalle por curso/grupo/estudiante) si hay datos.
        $attachmentpath = $attachmentname = null;
        if (!empty($alertrecipients) && !empty($groupsummary)) {
            $format = $settings->report_format ?? 'pdf'; // reutilizamos el mismo selector de formato
            list($attachmentpath, $attachmentname) = $this->generate_alerts_report($groupsummary, $cutoff, $format);
        }

        if (!empty($alertrecipients) && !empty($groupsummary)) {
            $emails = preg_split('/[,;]+/', $alertrecipients, -1, PREG_SPLIT_NO_EMPTY);
            $emails = array_map('trim', $emails);

            if (!empty($emails)) {
                $logo_url = $CFG->wwwroot . '/local/mai/img/logo.svg';

                // ==========================
                // Email a coordinadores (mismo diseño MAI)
                // ==========================
                $summaryhtml = '<ul style="margin:0 0 0 18px;padding:0;font-size:13px;color:#111827;">';
                foreach ($groupsummary as $g) {
                    $summaryhtml .= '<li><strong>Curso:</strong> ' . s($g['course']) .
                        ' &nbsp; | &nbsp; <strong>Grupo:</strong> ' . s($g['group']) .
                        ' (' . $g['percent'] . '% inactivos)</li>';
                }
                $summaryhtml .= '</ul>';

                $bodyhtml = '
<div style="font-family:system-ui,Arial,sans-serif;max-width:640px;margin:0 auto;padding:16px;background:#f3f4f6;">
  <div style="background:#ffffff;border-radius:12px;border:1px solid #e5e7eb;overflow:hidden;">
    <div style="background:#8C253E;color:#ffffff;padding:12px 18px;text-align:center;border-bottom:3px solid #FF7000;">
      <h2 style="margin:0;font-size:16px;font-weight:600;">Alerta de inactividad en grupos</h2>
      <p style="margin:4px 0 0;font-size:12px;color:#F9FAFB;">Resumen de grupos con alta inactividad.</p>
    </div>
    <div style="padding:18px;">
      <p style="margin:0 0 10px;font-size:13px;color:#111827;line-height:1.6;">'
        . $coordmsg .
      '</p>
      <div style="margin-top:8px;">' . $summaryhtml . '</div>
      <p style="margin:14px 0 0;font-size:12px;color:#6b7280;">
        El detalle por curso, grupo y estudiante se incluye en el archivo adjunto.
      </p>
    </div>
  </div>
  <p style="margin:12px 0 0;font-size:11px;color:#9ca3af;text-align:center;">
    Mensaje generado autom&aacute;ticamente desde el m&oacute;dulo MAI.
  </p>
</div>';

                $bodytext = html_to_text($bodyhtml, 0, false);
                $subject  = 'Alerta de inactividad en grupos - MAI';

                foreach ($emails as $email) {
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

                    mtrace('[local_mai] Envío de alerta de inactividad a ' . $email . ': ' . ($sent ? 'OK' : 'FALLÓ'));
                }
            }
        } else {
            mtrace('[local_mai] No hay grupos sobre el umbral o no hay destinatarios configurados para alertas de grupo.');
        }

        \local_mai_notificaciones_mark_alerts_checked();
        mtrace('[local_mai] Tarea check_inactividad_alerts finalizada.');
    }

    /**
     * Genera un reporte de alertas por curso/grupo/estudiante.
     */
    protected function generate_alerts_report(array $groupsummary, int $cutoff, string $format): array {
        $grouped = [];
        foreach ($groupsummary as $g) {
            $grouped[$g['course']][] = $g;
        }

        if ($format === 'xlsx') {
            return $this->generate_alerts_report_xlsx($grouped);
        } else {
            return $this->generate_alerts_report_pdf($grouped);
        }
    }

    /**
     * Reporte PDF de alertas.
     */
    protected function generate_alerts_report_pdf(array $grouped): array {
        global $CFG;

        $pdf = new \pdf();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetFont('helvetica', '', 9);

        $logopath = $CFG->dirroot . '/local/mai/img/logo.svg';
        $haslogo  = file_exists($logopath);

        foreach ($grouped as $coursename => $groups) {
            $pdf->AddPage();

            if ($haslogo) {
                $pdf->ImageSVG($logopath, 15, 10, 35);
                $pdf->SetY(15);
            } else {
                $pdf->SetY(20);
            }

            // Título del reporte.
            $pdf->SetFont('helvetica', 'B', 14);
            $pdf->Cell(0, 6, 'Alerta de inactividad en grupos', 0, 1, 'C');

            // Nombre del curso.
            $pdf->SetFont('helvetica', '', 11);
            $pdf->Cell(0, 6, 'Curso: ' . $coursename, 0, 1, 'C');

            // Fecha.
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(0, 5, 'Fecha de generación: ' . userdate(time()), 0, 1, 'C');

            $pdf->Ln(4);

            foreach ($groups as $g) {
                // Título del grupo en negritas.
                $pdf->SetFont('helvetica', 'B', 10);
                $pdf->Cell(0, 5, 'Grupo: ' . $g['group'] . ' (' . $g['percent'] . '% inactivos)', 0, 1, 'L');
                $pdf->Ln(1);

                // Fuente normal para la tabla.
                $pdf->SetFont('helvetica', '', 9);

                // Encabezados de la tabla en negritas, filas normales.
                $html  = '<table cellspacing="0" cellpadding="3" width="100%">';
                $html .= '<tr style="font-weight:bold;background-color:#f3f4f6;border-bottom:0.5px solid #000;">';
                $html .= '<td width="18%"><strong>Segmento</strong></td>';
                $html .= '<td width="32%"><strong>Nombre completo</strong></td>';
                $html .= '<td width="30%"><strong>Correo</strong></td>';
                $html .= '<td width="20%"><strong>Último acceso</strong></td>';
                $html .= '</tr>';

                foreach ($g['members'] as $m) {
                    $last = $m->lastaccess ? userdate($m->lastaccess) : '-';
                    $html .= '<tr style="border-bottom:0.5px solid #e5e7eb;">';
                    $html .= '<td>' . s($m->segment) . '</td>';
                    $html .= '<td>' . s($m->fullname) . '</td>';
                    $html .= '<td>' . s($m->email) . '</td>';
                    $html .= '<td>' . s($last) . '</td>';
                    $html .= '</tr>';
                }

                $html .= '</table>';

                $pdf->writeHTML($html, true, false, true, false, '');
                $pdf->Ln(3);
            }
        }

        $tempdir  = make_temp_directory('local_mai_alerts');
        $filename = 'alerta_inactividad_grupos_' . date('Ymd_His') . '.pdf';
        $fullpath = $tempdir . '/' . $filename;

        $pdf->Output($fullpath, 'F');

        return [$fullpath, $filename];
    }

    /**
     * Reporte XLSX de alertas (sin enviar nada a stdout).
     *
     * @param array $grouped  [ 'Nombre curso' => [ [ 'course','group','percent','members'=>[...] ], ... ] ]
     * @return array [ruta, nombrearchivo]
     */
    protected function generate_alerts_report_xlsx(array $grouped): array {
        // Carpeta temporal donde se guardarán los reportes de alertas.
        $tempdir  = make_temp_directory('local_mai_alerts');
        $filename = 'alerta_inactividad_grupos_' . date('Ymd_His') . '.xlsx';
        $fullpath = $tempdir . '/' . $filename;

        // Crear workbook de PhpSpreadsheet (usando el namespace completo).
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

        // Quitamos la hoja por defecto y luego creamos una por curso.
        $spreadsheet->removeSheetByIndex(0);

        $usednames = [];

        foreach ($grouped as $coursename => $groups) {
            // ===== Nombre de la hoja (por curso) =====
            $basename = clean_param($coursename, PARAM_NOTAGS);
            // Excel no permite : \ / ? * [ ]
            $basename = preg_replace('/[:\\\\\\/\\?\\*\\[\\]]/', '', $basename);
            $basename = trim($basename);
            if ($basename === '') {
                $basename = 'Curso';
            }

            // Máx. 31 caracteres, guardamos base más corta para permitir sufijos.
            $base = \core_text::substr($basename, 0, 28);
            $sheetname = $base;
            $suffix = 1;
            while (isset($usednames[$sheetname])) {
                $suffix++;
                $extra = ' (' . $suffix . ')';
                $sheetname = \core_text::substr($base, 0, 31 - strlen($extra)) . $extra;
            }
            $usednames[$sheetname] = true;

            // Crear hoja para este curso.
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle($sheetname);

            // ===== Encabezados =====
            $sheet->setCellValue('A1', 'Grupo');
            $sheet->setCellValue('B1', 'Segmento');
            $sheet->setCellValue('C1', 'Nombre completo');
            $sheet->setCellValue('D1', 'Correo');
            $sheet->setCellValue('E1', 'Último acceso');
            $sheet->setCellValue('F1', '% de inactividad (grupo)');

            // Estilo de encabezado: negritas y centrado.
            $sheet->getStyle('A1:F1')->getFont()->setBold(true);
            $sheet->getStyle('A1:F1')->getAlignment()->setHorizontal(
                \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER
            );

            // ===== Datos =====
            $row = 2;
            foreach ($groups as $g) {
                foreach ($g['members'] as $m) {
                    $last = $m->lastaccess ? userdate($m->lastaccess) : '-';

                    $sheet->setCellValue('A' . $row, $g['group']);
                    $sheet->setCellValue('B' . $row, $m->segment);
                    $sheet->setCellValue('C' . $row, $m->fullname);
                    $sheet->setCellValue('D' . $row, $m->email);
                    $sheet->setCellValue('E' . $row, $last);
                    $sheet->setCellValue('F' . $row, $g['percent']);

                    $row++;
                }

                // Fila en blanco entre grupos del mismo curso.
                $row++;
            }

            // Anchos de columna.
            $sheet->getColumnDimension('A')->setWidth(18);
            $sheet->getColumnDimension('B')->setWidth(14);
            $sheet->getColumnDimension('C')->setWidth(28);
            $sheet->getColumnDimension('D')->setWidth(30);
            $sheet->getColumnDimension('E')->setWidth(18);
            $sheet->getColumnDimension('F')->setWidth(18);
        }

        // Si por alguna razón no se creó ninguna hoja, crea una vacía para evitar errores.
        if ($spreadsheet->getSheetCount() === 0) {
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle('Reporte');
        }

        // Guardar a archivo físico (NO a php://output).
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($fullpath);

        return [$fullpath, $filename];
    }

}
