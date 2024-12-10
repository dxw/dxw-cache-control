<?php

/**
 * Cache Control
 *
 * @package     CacheControl
 * @author      dxw
 * @copyright   2022
 * @license     MIT
 *
 * @wordpress-plugin
 * Plugin Name: DXW Cache Control
 * Plugin URI: https://github.com/dxw/dxw-cache-control
 * Description: Set the Cache control headers by content type, taxonomy, template etc.
 * Author: dxw
 * Version: 1.0.0
 * Requires at least: 6.1
 * Network: True
 */

$registrar = require __DIR__.'/src/load.php';
$registrar->register();
