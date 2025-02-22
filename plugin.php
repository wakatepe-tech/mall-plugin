<?php
/**
 * Plugin Name: Mall Settings
 * Description: Un plugin personnalisé pour afficher les horaires d'ouverture et les offres promotionnels
 * Version: 1.4.0
 * Author: Placeloop
 * Author URI: https://placeloop.com/
 * Text Domain: placeloop
 * Requires at least: 6.7.2
 * Requires PHP: 8.2
 */

if (!defined('ABSPATH')) {
    exit;
}

setlocale(LC_TIME, "fr_FR.UTF-8");

if (!function_exists('get_field')) {
    return;
}

require_once __DIR__ . '/includes/schedules.php';
require_once __DIR__ . '/includes/offerShop.php';

new Schedules();
new OfferShop();