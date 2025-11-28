<?php
// local/mai/estructura/lib.php

defined('MOODLE_INTERNAL') || die();

/**
 * Cálculo de estadísticas por estructura académica.
 *
 * @param int $programid  Categoría padre (programa académico).
 * @param int $termid     Subcategoría (cuatrimestre).
 * @param int $teacherid  Usuario docente (editar / no editar, filtro para cuatrimestre).
 * @param int $groupid    Grupo de Moodle (filtro por grupo dentro del cuatrimestre).
 * @return array          Datos agregados para usar en AJAX/export.
 */
function local_mai_estructura_get_stats(int $programid = 0, int $termid = 0, int $teacherid = 0, int $groupid = 0): array {
    global $DB, $CFG;

    require_once($CFG->dirroot . '/local/mai/participacion/lib.php');

    // Todas las categorías para navegar jerarquía.
    $categories = $DB->get_records('course_categories', null, 'sortorder',
        'id, name, parent');

    // Programas = categorías padre.
    $programcats = $DB->get_records('course_categories', ['parent' => 0], 'sortorder',
        'id, name, parent');

    // Cuatrimestres (subcategorías del programa seleccionado).
    $termcats = [];
    if ($programid && isset($categories[$programid])) {
        $termcats = $DB->get_records('course_categories', ['parent' => $programid], 'sortorder',
            'id, name, parent');
    }

    // Helper: categoría top-level (programa).
    $get_top_program = function (int $catid) use ($categories): ?int {
        if (empty($categories[$catid])) {
            return null;
        }
        while (!empty($categories[$catid]->parent)) {
            $catid = $categories[$catid]->parent;
            if (empty($categories[$catid])) {
                return null;
            }
        }
        return $catid;
    };

    // Cursos (excepto portada).
    $courses = $DB->get_records_sql(
        "SELECT id, fullname, shortname, category
           FROM {course}
          WHERE id <> :siteid",
        ['siteid' => SITEID]
    );

    $courseStats = [];
    $global = [
        'courses'  => 0,
        'active'   => 0,
        'inactive' => 0,
        'never'    => 0,
    ];

    foreach ($courses as $course) {
        // Pasamos el groupid como opción para que la función de participación filtre por grupo.
        $options = [];
        if ($groupid) {
            $options['groupid'] = $groupid;
        }

        $pdata = local_mai_get_participation_data($course->id, $options);

        $activos         = $pdata['activos'] ?? [];
        $inactivos       = $pdata['inactivos'] ?? [];
        $nuncaingresaron = $pdata['nuncaingresaron'] ?? [];

        $ca = count($activos);
        $ci = count($inactivos);
        $cn = count($nuncaingresaron);
        $total = $ca + $ci + $cn;

        // Si hay filtro de grupo y este curso no tiene nadie de ese grupo, lo omitimos.
        if ($groupid && $total === 0) {
            continue;
        }

        $courseStats[$course->id] = [
            'course'   => $course,
            'category' => $course->category,
            'active'   => $ca,
            'inactive' => $ci,
            'never'    => $cn,
            'total'    => $total,
        ];

        $global['courses']++;
        $global['active']   += $ca;
        $global['inactive'] += $ci;
        $global['never']    += $cn;
    }

    // Agregación por programa.
    $programStats = [];

    foreach ($courseStats as $cs) {
        $catid        = $cs['category'];
        $topprogramid = $get_top_program($catid);
        if (!$topprogramid || !isset($programcats[$topprogramid])) {
            continue;
        }

        if (!isset($programStats[$topprogramid])) {
            $programStats[$topprogramid] = [
                'program'  => $programcats[$topprogramid],
                'courses'  => 0,
                'active'   => 0,
                'inactive' => 0,
                'never'    => 0,
                'total'    => 0,
            ];
        }

        $programStats[$topprogramid]['courses']++;
        $programStats[$topprogramid]['active']   += $cs['active'];
        $programStats[$topprogramid]['inactive'] += $cs['inactive'];
        $programStats[$topprogramid]['never']    += $cs['never'];
        $programStats[$topprogramid]['total']    += $cs['total'];
    }

    // Cursos dentro del cuatrimestre seleccionado.
    $termcourseids = [];
    if ($termid && isset($categories[$termid])) {
        foreach ($courseStats as $cid => $cs) {
            if ((int)$cs['category'] === $termid) {
                $termcourseids[] = $cid;
            }
        }
    }

    // Docentes del cuatrimestre (editingteacher Y teacher).
    $teachers = [];

    $editingrole    = $DB->get_record('role', ['shortname' => 'editingteacher'], 'id', IGNORE_MISSING);
    $noneditingrole = $DB->get_record('role', ['shortname' => 'teacher'], 'id', IGNORE_MISSING);

    $teacherroleids = [];
    if (!empty($editingrole) && !empty($editingrole->id)) {
        $teacherroleids[] = (int)$editingrole->id;
    }
    if (!empty($noneditingrole) && !empty($noneditingrole->id)) {
        $teacherroleids[] = (int)$noneditingrole->id;
    }

    if ($termid && !empty($termcourseids) && !empty($teacherroleids)) {
        list($sqlin, $params) = $DB->get_in_or_equal($termcourseids, SQL_PARAMS_NAMED);
        list($sqlroles, $roleparams) = $DB->get_in_or_equal($teacherroleids, SQL_PARAMS_NAMED, 'roleid');
        $params = array_merge($params, $roleparams);
        $params['ctxcourse'] = CONTEXT_COURSE;

        $teachers = $DB->get_records_sql("
            SELECT DISTINCT u.id,
                   u.firstname, u.lastname,
                   u.firstnamephonetic, u.lastnamephonetic,
                   u.middlename, u.alternatename
              FROM {user} u
              JOIN {role_assignments} ra ON ra.userid = u.id
              JOIN {context} ctx ON ctx.id = ra.contextid
             WHERE ra.roleid $sqlroles
               AND ctx.contextlevel = :ctxcourse
               AND ctx.instanceid $sqlin
             ORDER BY u.lastname, u.firstname
        ", $params);
    }

    // Restringimos cursos de cuatrimestre por docente si aplica.
    $scopeTermCourseIds = $termcourseids;
    if ($termid && $teacherid && !empty($termcourseids) && !empty($teacherroleids)) {
        list($sqlin, $params) = $DB->get_in_or_equal($termcourseids, SQL_PARAMS_NAMED);
        list($sqlroles, $roleparams) = $DB->get_in_or_equal($teacherroleids, SQL_PARAMS_NAMED, 'roleid');
        $params = array_merge($params, $roleparams);
        $params['teacherid'] = $teacherid;
        $params['ctxcourse'] = CONTEXT_COURSE;

        $teachercourses = $DB->get_fieldset_sql("
            SELECT DISTINCT ctx.instanceid AS courseid
              FROM {role_assignments} ra
              JOIN {context} ctx ON ctx.id = ra.contextid
             WHERE ra.roleid $sqlroles
               AND ra.userid = :teacherid
               AND ctx.contextlevel = :ctxcourse
               AND ctx.instanceid $sqlin
        ", $params);

        if (!empty($teachercourses)) {
            $scopeTermCourseIds = array_values(array_intersect($termcourseids, $teachercourses));
        } else {
            $scopeTermCourseIds = [];
        }
    }

    // Stats del cuatrimestre.
    $termStats = [
        'courses'  => 0,
        'active'   => 0,
        'inactive' => 0,
        'never'    => 0,
        'total'    => 0,
    ];

    if (!empty($scopeTermCourseIds)) {
        foreach ($scopeTermCourseIds as $cid) {
            if (empty($courseStats[$cid])) {
                continue;
            }
            $cs = $courseStats[$cid];

            // Si hay filtro de grupo y este curso no tiene datos, no lo sumamos.
            if ($groupid && $cs['total'] === 0) {
                continue;
            }

            $termStats['courses']++;
            $termStats['active']   += $cs['active'];
            $termStats['inactive'] += $cs['inactive'];
            $termStats['never']    += $cs['never'];
            $termStats['total']    += $cs['total'];
        }
    }

    // Determinar vista.
    $view = 'general';
    if ($termid) {
        $view = 'term';
    } else if ($programid) {
        $view = 'program';
    }

    // Global output.
    $globalTotal = $global['active'] + $global['inactive'] + $global['never'];
    $globalOut = [
        'courses'   => (int)$global['courses'],
        'active'    => (int)$global['active'],
        'inactive'  => (int)$global['inactive'],
        'never'     => (int)$global['never'],
        'total'     => (int)$globalTotal,
        'retention' => $globalTotal > 0
            ? (int)round((($global['active'] + $global['inactive']) * 100) / $globalTotal)
            : 0,
    ];

    // Program stats output.
    $programstatsOut = [];
    foreach ($programStats as $pid => $ps) {
        $total = $ps['total'];
        $ret = $total > 0 ? (int)round((($ps['active'] + $ps['inactive']) * 100) / $total) : 0;
        $programstatsOut[] = [
            'id'        => (int)$pid,
            'name'      => format_string($ps['program']->name),
            'courses'   => (int)$ps['courses'],
            'active'    => (int)$ps['active'],
            'inactive'  => (int)$ps['inactive'],
            'never'     => (int)$ps['never'],
            'total'     => (int)$ps['total'],
            'retention' => $ret,
        ];
    }

    // Term courses output.
    $termcoursesOut = [];
    if (!empty($scopeTermCourseIds)) {
        foreach ($scopeTermCourseIds as $cid) {
            if (empty($courseStats[$cid])) {
                continue;
            }
            $cs = $courseStats[$cid];
            $total = $cs['total'];

            // Si hay filtro de grupo y este curso quedó en 0, no lo mostramos.
            if ($groupid && $total === 0) {
                continue;
            }

            $ret = $total > 0 ? (int)round((($cs['active'] + $cs['inactive']) * 100) / $total) : 0;

            $termcoursesOut[] = [
                'id'        => (int)$cid,
                'fullname'  => format_string($cs['course']->fullname),
                'active'    => (int)$cs['active'],
                'inactive'  => (int)$cs['inactive'],
                'never'     => (int)$cs['never'],
                'total'     => (int)$total,
                'retention' => $ret,
            ];
        }
    }

    // Teachers output.
    $teachersOut = [];
    foreach ($teachers as $t) {
        $teachersOut[] = [
            'id'       => (int)$t->id,
            'fullname' => fullname($t),
        ];
    }

    // Groups dinámicos (según cursos del cuatrimestre y docente).
    $groups = [];
    if ($termid && !empty($scopeTermCourseIds)) {
        list($sqlin, $params) = $DB->get_in_or_equal($scopeTermCourseIds, SQL_PARAMS_NAMED);
        $groups = $DB->get_records_sql("
            SELECT g.id, g.name, g.courseid
              FROM {groups} g
             WHERE g.courseid $sqlin
             ORDER BY g.name
        ", $params);
    }

    $groupsOut = [];
    foreach ($groups as $g) {
        $groupsOut[] = [
            'id'   => (int)$g->id,
            'name' => format_string($g->name),
        ];
    }

    // Terms output (cuatrimestres del programa seleccionado).
    $termsOut = [];
    foreach ($termcats as $tc) {
        $termsOut[] = [
            'id'   => (int)$tc->id,
            'name' => format_string($tc->name),
        ];
    }

    // Nombres de contexto.
    $programname = ($programid && isset($programcats[$programid]))
        ? format_string($programcats[$programid]->name)
        : '';
    $termname = ($termid && isset($termcats[$termid]))
        ? format_string($termcats[$termid]->name)
        : '';

    $teachername = '';
    if ($teacherid && !empty($teachers[$teacherid])) {
        $teachername = fullname($teachers[$teacherid]);
    }

    $groupname = '';
    if ($groupid && !empty($groups[$groupid])) {
        $groupname = format_string($groups[$groupid]->name);
    }

    // Term stats output.
    $termStatsOut = [
        'courses'   => (int)$termStats['courses'],
        'active'    => (int)$termStats['active'],
        'inactive'  => (int)$termStats['inactive'],
        'never'     => (int)$termStats['never'],
        'total'     => (int)$termStats['total'],
        'retention' => $termStats['total'] > 0
            ? (int)round((($termStats['active'] + $termStats['inactive']) * 100) / $termStats['total'])
            : 0,
    ];

    // Filtros dinámicos que se devuelven al frontend.
    $filtersOut = [
        'programid'  => $programid,
        'termid'     => $termid,
        'teacherid'  => $teacherid,
        'groupid'    => $groupid,
        'teachers'   => $teachersOut,
        'terms'      => $termsOut,
        'groups'     => $groupsOut,
    ];

    return [
        'view'         => $view,
        'global'       => $globalOut,
        'programstats' => $programstatsOut,
        'termstats'    => $termStatsOut,
        'termcourses'  => $termcoursesOut,
        'context'      => [
            'programname' => $programname,
            'termname'    => $termname,
            'teachername' => $teachername,
            'groupname'   => $groupname,
        ],
        'filters'      => $filtersOut,
    ];
}

/**
 * Versión ligera: devuelve solo la estructura de filtros
 * (principalmente cuatrimestres del programa), sin recorrer todos los cursos
 * ni calcular participación.
 *
 * Pensado para cuando el usuario solo cambia el programa en la pestaña
 * "Vista por cuatrimestre" y aún no ha seleccionado cuatrimestre.
 *
 * @param int $programid
 * @param int $termid
 * @param int $teacherid
 * @param int $groupid
 * @return array
 */
function local_mai_estructura_get_filters(int $programid = 0, int $termid = 0,
        int $teacherid = 0, int $groupid = 0): array {

    global $DB;

    $termsOut = [];

    if ($programid) {
        $termcats = $DB->get_records('course_categories', ['parent' => $programid], 'sortorder',
            'id, name, parent');
        foreach ($termcats as $tc) {
            $termsOut[] = [
                'id'   => (int)$tc->id,
                'name' => format_string($tc->name),
            ];
        }
    }

    // Para este modo ligero no calculamos docentes ni grupos hasta que se
    // seleccione un cuatrimestre y se llame a local_mai_estructura_get_stats().
    return [
        'filters' => [
            'programid'  => $programid,
            'termid'     => $termid,
            'teacherid'  => $teacherid,
            'groupid'    => $groupid,
            'teachers'   => [],
            'terms'      => $termsOut,
            'groups'     => [],
        ],
    ];
}
