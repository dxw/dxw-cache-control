<?php
/**
 * Cache Control
 *
 * @package     CacheControl
 * @author      dxw
 * @copyright   2020
 * @license     MIT
 *
 * @wordpress-plugin
 * Plugin Name: DXW Cache Control
 * Plugin URI: https://github.com/dxw/dxw-cache-control
 * Description: Set the Cache control headers by content type, taxonomy, template etc.
 * Author: dxw
 * Version: 0.1.2
 * Network: True
 */

$registrar = require __DIR__.'/src/load.php';
$registrar->register();
