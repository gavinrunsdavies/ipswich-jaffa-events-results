<?php
/**
 * Database configuration for the IPSWICH JAFFA event results plugin.
 *
 * In production/test deployments, values should come from GitHub environment
 * secrets and be injected during the deployment step. Local development can
 * fallback to placeholder values.
 */

if (!defined('EVENTS_RESULTS_DB_NAME')) {
    define('EVENTS_RESULTS_DB_NAME', getenv('EVENTS_RESULTS_DB_NAME') ?: '***');
}

if (!defined('EVENTS_RESULTS_DB_USER')) {
    define('EVENTS_RESULTS_DB_USER', getenv('EVENTS_RESULTS_DB_USER') ?: '***');
}

if (!defined('EVENTS_RESULTS_DB_PASSWORD')) {
    define('EVENTS_RESULTS_DB_PASSWORD', getenv('EVENTS_RESULTS_DB_PASSWORD') ?: '***');
}

if (!defined('EVENTS_RESULTS_DB_HOST')) {
    define('EVENTS_RESULTS_DB_HOST', getenv('EVENTS_RESULTS_DB_HOST') ?: defined('DB_HOST') ? DB_HOST : 'localhost');
}

if (!defined('EVENTS_RESULTS_DB_PREFIX')) {
    define('EVENTS_RESULTS_DB_PREFIX', getenv('EVENTS_RESULTS_DB_PREFIX') ?: 'wp_');
}
