<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) { // Solo para administradores del sitio.

    // ─────────────────────────────────────────────────────────────
    // 1. Categoría principal dentro de "Reportes".
    // ─────────────────────────────────────────────────────────────
    $ADMIN->add('reports', new admin_category(
        'mai_category',
        get_string('pluginname', 'local_mai')
    ));

    // ─────────────────────────────────────────────────────────────
    // 2. Configuraciones registradas.
    // ─────────────────────────────────────────────────────────────
    $ADMIN->add('mai_category', new admin_externalpage(
        'adminmai',
        get_string('dashboard', 'local_mai'),
        new moodle_url('/local/mai/index.php'),
        'local/mai:manage'
    ));


    // Añadir la página de ajustes a la categoría del plugin.
    $ADMIN->add('mai_category', $page);
}