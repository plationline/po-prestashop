<?php
/**
 * @link              http://plationline.eu
 * @since             6.0.0
 * @package           PlatiOnlinePO6
 *
 */

spl_autoload_register(function ($class_name) {
    // If the specified $class_name does not include our namespace, duck out.
    if (false === strpos($class_name, 'PlatiOnlinePO6')) {
        return;
    }

    // Split the class name into an array to read the namespace and class.
    $file_parts = explode('\\', $class_name);

    // Do a reverse loop through $file_parts to build the path to the file.
    $namespace = '';
    for ($i = count($file_parts) - 1; $i > 0; $i--) {

        // Read the current component of the file part.
        $current = strtolower($file_parts[ $i ]);
        $current = str_ireplace('_', '-', $current);

        // If we're at the first entry, then we're at the filename.
        if (count($file_parts) - 1 === $i) {
            /**
             * If 'interface' is contained in the parts of the file name, then
             * define the $file_name differently so that it's properly loaded.
             * Otherwise, just set the $file_name equal to that of the class
             * filename structure.
             */
            if (strpos(strtolower($file_parts[ count($file_parts) - 1 ]), 'interface')) {

                // Grab the name of the interface from its qualified name.
                $interface_name = explode('_', $file_parts[ count($file_parts) - 1 ]);
                $interface_name = $interface_name[0];

                $file_name = "interface-$interface_name.php";
            } else {
                $file_name = "class-$current.php";
            }
        } else {
            $namespace = '/' . $current . $namespace;
        }
    }

    // Now build a path to the file using mapping to the file location.
    $filepath  = trailingslashit(untrailingslashit(_PS_ROOT_DIR_.DIRECTORY_SEPARATOR.'modules'.DIRECTORY_SEPARATOR.'plationline'.DIRECTORY_SEPARATOR) . $namespace);
    $filepath .= $file_name;

    // If the file exists in the specified path, then include it.
    if (file_exists($filepath)) {
        include_once($filepath);
    } else {
        die(
           'The file attempting to be loaded at ' . $filepath . ' does not exist.'
        );
    }
});

function untrailingslashit($string)
{
    return rtrim($string, '/\\');
}

function trailingslashit($string)
{
    return untrailingslashit($string) . '/';
}
