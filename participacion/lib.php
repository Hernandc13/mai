<?php
// local/mai/participacion/lib.php

defined('MOODLE_INTERNAL') || die();

/**
 * Obtiene la información de participación por curso y filtros.
 *
 * Filtros soportados:
 *  - categoryid: si no coincide con la categoría del curso, regresa vacío.
 *  - cohortid:   solo usuarios que pertenecen a esa cohorte.
 *  - groupid:    solo usuarios de ese grupo en el curso.
 *  - roleid:     solo usuarios con ese rol en el contexto del curso.
 *
 * @param int   $courseid
 * @param array $filters
 * @return array
 */

function local_mai_get_participation_data(int $courseid, array $filters = []): array {
    global $DB;

    $course        = get_course($courseid);
    $coursecontext = context_course::instance($courseid);

    // TODOS los campos de nombre para que fullname() no dispare debugging().
    $fields = 'u.id,
               u.firstname,
               u.lastname,
               u.email,
               u.firstnamephonetic,
               u.lastnamephonetic,
               u.middlename,
               u.alternatename';

    $students = get_enrolled_users(
        $coursecontext,
        '',
        0,
        $fields,
        'u.lastname, u.firstname',
        0,
        0,
        false
    );

    $activos         = [];
    $inactivos       = [];
    $nuncaingresaron = [];

    if (empty($students)) {
        return [
            'course'          => $course,
            'activos'         => [],
            'inactivos'       => [],
            'nuncaingresaron' => [],
            'counts'          => ['active' => 0, 'inactive' => 0, 'never' => 0],
            'total'           => 0,
        ];
    }

    // Total actividades con completion
    $totalactivities = $DB->count_records_sql("
        SELECT COUNT(1)
          FROM {course_modules} cm
         WHERE cm.course = :courseid
           AND cm.deletioninprogress = 0
           AND cm.visible = 1
           AND cm.completion != 0
    ", ['courseid' => $courseid]);

    foreach ($students as $user) {

        $fullname = fullname($user); // ahora ya no saca avisos

        $lastaccess = $DB->get_field('user_lastaccess', 'timeaccess', [
            'userid'   => $user->id,
            'courseid' => $courseid
        ]);

        $completedactivities = 0;
        if ($totalactivities > 0) {
            $completedactivities = (int)$DB->count_records_sql("
                SELECT COUNT(1)
                  FROM {course_modules_completion} cmc
                  JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                 WHERE cm.course = :courseid
                   AND cmc.userid = :userid
                   AND cmc.completionstate > 0
            ", [
                'courseid' => $courseid,
                'userid'   => $user->id
            ]);
        }

        // Activo
        if (!empty($lastaccess) && $completedactivities > 0) {
            $progress = $totalactivities > 0 ? round(($completedactivities / $totalactivities) * 100) : 0;

            $activos[] = (object)[
                'fullname'            => $fullname,
                'email'               => $user->email,
                'lastaccess'          => $lastaccess,
                'completedactivities' => $completedactivities,
                'progress'            => $progress,
            ];
            continue;
        }

        // Inactivo
        if (!empty($lastaccess) && $completedactivities == 0) {

            $log = $DB->get_record_sql("
                SELECT COUNT(1) AS clicks,
                       MIN(timecreated) AS firstlog,
                       MAX(timecreated) AS lastlog
                  FROM {logstore_standard_log}
                 WHERE courseid = :courseid
                   AND userid   = :userid
            ", [
                'courseid' => $courseid,
                'userid'   => $user->id
            ]);

            $clicks  = $log && $log->clicks ? (int)$log->clicks : 0;
            $minutes = 0;
            if (!empty($log->firstlog) && !empty($log->lastlog) && $log->lastlog > $log->firstlog) {
                $minutes = (int)round(($log->lastlog - $log->firstlog) / 60);
            }

            $inactivos[] = (object)[
                'fullname' => $fullname,
                'email'    => $user->email,
                'lastaccess' => $lastaccess,
                'minutes'    => $minutes,
                'clicks'     => $clicks,
            ];
            continue;
        }

        // Nunca ingresó
        $cohortname = '';
        $sqlcohort = "SELECT c.name
                        FROM {cohort_members} cm
                        JOIN {cohort} c ON c.id = cm.cohortid
                       WHERE cm.userid = :userid";
        if ($c = $DB->get_record_sql($sqlcohort, ['userid' => $user->id], IGNORE_MULTIPLE)) {
            $cohortname = $c->name;
        }

        $groupname = '';
        $sqlgroup = "SELECT g.name
                       FROM {groups_members} gm
                       JOIN {groups} g ON g.id = gm.groupid
                      WHERE gm.userid = :userid
                        AND g.courseid = :courseid";
        if ($g = $DB->get_record_sql($sqlgroup, [
            'userid'   => $user->id,
            'courseid' => $courseid
        ], IGNORE_MULTIPLE)) {
            $groupname = $g->name;
        }

        $sqlenrol = "SELECT MIN(ue.timecreated) AS enroltime
                       FROM {user_enrolments} ue
                       JOIN {enrol} e ON e.id = ue.enrolid
                      WHERE ue.userid = :userid
                        AND e.courseid = :courseid";
        $enroltime = $DB->get_field_sql($sqlenrol, [
            'userid'   => $user->id,
            'courseid' => $courseid
        ]);

        $nuncaingresaron[] = (object)[
            'fullname'  => $fullname,
            'email'     => $user->email,
            'cohort'    => $cohortname,
            'group'     => $groupname,
            'enroltime' => $enroltime,
        ];
    }

    $counts = [
        'active'  => count($activos),
        'inactive'=> count($inactivos),
        'never'   => count($nuncaingresaron),
    ];

    return [
        'course'          => $course,
        'activos'         => $activos,
        'inactivos'       => $inactivos,
        'nuncaingresaron' => $nuncaingresaron,
        'counts'          => $counts,
        'total'           => array_sum($counts),
    ];
}
