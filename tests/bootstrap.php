<?php
// declare(strict_types = 1);
require __DIR__ . '/../vendor/autoload.php';

// Now call the bootstrap method of WP Mock
WP_Mock::bootstrap();

// Ensure library/ is on include_path
set_include_path(implode(PATH_SEPARATOR, array(
    __DIR__,
    get_include_path()
)));

$_SERVER['REQUEST_URI'] = '/';

/**
 * Now we include any plugin files that we need to be able to run the tests. This
 * should be files that define the functions and classes you're going to test.
 */
// require_once __DIR__ . '/../waau-mendeley-plugin.php';
require_once __DIR__ . '/../includes/MendeleyApi.php';
require_once __DIR__ . '/../public/WaauMendeleyPlugin.php';
require_once __DIR__ . '/../admin/WaauMendeleyPluginAdmin.php';



