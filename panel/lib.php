<?php
// local/mai/panel/lib.php

defined('MOODLE_INTERNAL') || die();

/**
 * Cálculo de estadísticas para el panel MAI (gráficos).
 *
 * @param int $programid  Programa académico (categoría padre).
 * @param int $termid     Cuatrimestre (subcategoría).
 * @param int $teacherid  (Reservado para futuro).
 * @param int $groupid    (Reservado para futuro / filtro por grupo).
 * @return array          Datos agregados para el panel.
 */
function local_mai_panel_get_stats(int $programid = 0, int $termid = 0,
        int $teacherid = 0, int $groupid = 0): array {

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
        $catid = (int)$course->category;

        // ¿Este curso entra en el alcance de los filtros?
        $inscope = true;

        if ($programid) {
            $topprogramid = $get_top_program($catid);
            if ($topprogramid !== $programid) {
                $inscope = false;
            }
        }

        if ($termid && $catid !== $termid) {
            $inscope = false;
        }

        if (!$inscope) {
            continue;
        }

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

        // Si hay groupid y no hay nadie de ese grupo en este curso, lo omitimos.
        if ($groupid && $total === 0) {
            continue;
        }

        $courseStats[$course->id] = [
            'course'   => $course,
            'category' => $catid,
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

    // ==========================
    // Global output
    // ==========================
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

    // ==========================
    // Stats por programa
    // ==========================
    $programStats = [];

    foreach ($courseStats as $cs) {
        $catid = $cs['category'];
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

    // ==========================
    // Stats por cuatrimestre (comparación de periodos)
    // ==========================
    $termsStatsOut = [];
    if ($programid && !empty($termcats)) {
        foreach ($termcats as $tc) {
            $tstats = [
                'courses'  => 0,
                'active'   => 0,
                'inactive' => 0,
                'never'    => 0,
                'total'    => 0,
            ];

            foreach ($courseStats as $cid => $cs) {
                if ((int)$cs['category'] !== (int)$tc->id) {
                    continue;
                }
                $tstats['courses']++;
                $tstats['active']   += $cs['active'];
                $tstats['inactive'] += $cs['inactive'];
                $tstats['never']    += $cs['never'];
                $tstats['total']    += $cs['total'];
            }

            if ($tstats['courses'] === 0 && $tstats['total'] === 0) {
                continue;
            }

            $ret = $tstats['total'] > 0
                ? (int)round((($tstats['active'] + $tstats['inactive']) * 100) / $tstats['total'])
                : 0;

            $termsStatsOut[] = [
                'id'        => (int)$tc->id,
                'name'      => format_string($tc->name),
                'courses'   => (int)$tstats['courses'],
                'active'    => (int)$tstats['active'],
                'inactive'  => (int)$tstats['inactive'],
                'never'     => (int)$tstats['never'],
                'total'     => (int)$tstats['total'],
                'retention' => $ret,
            ];
        }
    }

    // ==========================
    // Filtros dinámicos (cuatrimestres del programa)
    // ==========================
    $termsOut = [];
    foreach ($termcats as $tc) {
        $termsOut[] = [
            'id'   => (int)$tc->id,
            'name' => format_string($tc->name),
        ];
    }

    $filtersOut = [
        'programid' => $programid,
        'termid'    => $termid,
        'teacherid' => $teacherid,
        'groupid'   => $groupid,
        'terms'     => $termsOut,
    ];

    return [
        'global'       => $globalOut,
        'programstats' => $programstatsOut,
        'termsstats'   => $termsStatsOut,
        'filters'      => $filtersOut,
    ];
}
