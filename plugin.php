<?php

/**
 * Plugin Name: Mall Settings
 * Description: Un plugin personnalisé pour afficher les horaires d'ouverture des boutiques.
 * Version: 1.0.3
 * Author: Placeloop
 */

if (!defined('ABSPATH')) {
    exit; // Sécurité : empêcher l'accès direct
}

// Vérifier si ACF est actif avant d'utiliser ses fonctions
if (!function_exists('get_field')) {
    return;
}

// Fonction pour formater les heures au format 9h00 au lieu de 0:00:00
function format_hour($hour)
{
    if (empty($hour)) return '';
    $time = strtotime($hour);
    return date('G\hi', $time);
}

// Function pour afficher les horaires d'ouverture avec le jour actuel en premier 
function display_schedules($atts)
{
    $shop_id = get_the_ID();
    if (!$shop_id) {
        return "<p>Aucune boutique spécifiée</p>";
    }

    // Jours de la semaine
    $days = [
        'monday'    => 'Lundi',
        'tuesday'   => 'Mardi',
        'wednesday' => 'Mercredi',
        'thursday'  => 'Jeudi',
        'friday'    => 'Vendredi',
        'saturday'  => 'Samedi',
        'sunday'    => 'Dimanche'
    ];

    // Obtenir le jour actuel et la date
    setlocale(LC_TIME, "fr_FR.UTF-8");
    $current_day = strtolower(date('l')); // Jour (ex: "monday")
    $current_date = (new DateTime())->format('d'); // Numéro du jour du mois

    // Récupérer les horaires d'ouverture
    $schedules = get_field("schedules", $shop_id);
    if (!$schedules || !is_array($schedules)) {
        return "<p>Aucun horaire disponible</p>";
    }

    // echo "<p>Debug: ID boutique = $shop_id</p>";

    // Construire l'ordre des jours (aujourd'hui en premier)
    $ordered_days = [];
    $found = false;

    foreach ($days as $day_en => $day_fr) {
        if ($day_en == $current_day) {
            $found = true;
        }
        if ($found) {
            $ordered_days[$day_en] = $day_fr;
        }
    }

    foreach ($days as $day_en => $day_fr) {
        if (!isset($ordered_days[$day_en])) {
            $ordered_days[$day_en] = $day_fr;
        }
    }

    // Trouver l'heure de fermeture du jour actuel
    $today_schedule = $schedules[$current_day] ?? null;
    $now = date('H:i');
    $closing_hour = '';

    if ($today_schedule && is_array($today_schedule)) {
        $morning_end = format_hour($today_schedule['morning']['end'] ?? '');
        $afternoon_end = format_hour($today_schedule['afternoon']['end'] ?? '');
        $closing_hour = !empty($afternoon_end) ? $afternoon_end : $morning_end;
    }

    // Déterminer le message d'ouverture
    if ($closing_hour && $now < $closing_hour) {
        $opening_message = "<span class='schedules_status'>Ouvert</span> · Ferme à <span class='schedules_time'>$closing_hour</span>";
    } else {
        // Trouver le prochain jour ouvré
        $next_day_key = array_keys($days)[(array_search($current_day, array_keys($days)) + 1) % 7];
        $next_day_schedule = $schedules[$next_day_key] ?? null;

        if ($next_day_schedule && is_array($next_day_schedule)) {
            $next_opening = format_hour($next_day_schedule['morning']['start'] ?? '');
            if ($next_opening) {
                $opening_message = "<span class='schedules_status'>Ouvre</span> demain à <span class='schedules_time'>$next_opening</span>";
            } else {
                $opening_message = "Fermé actuellement";
            }
        } else {
            $opening_message = "Fermé actuellement";
        }
    }

    $template = $atts['template'] ?? '';
    if ($template == "full") {

        // Construire la liste des horaires
        $schedule_list = "<details class='schedules'><summary class='schedules__summary'>$opening_message</summary><div>";

        foreach ($ordered_days as $day_en => $day_fr) {
            $date_obj = new DateTime("this $day_en");
            $day_num = $date_obj->format('d'); // Jour du mois

            // Vérifier si le jour est aujourd'hui
            $is_today = ($day_en == $current_day) ? "<span class='schedule__day schedule__day--active'>$day_fr $day_num</span>" : "<span class='schedule__day'>$day_fr $day_num</span>";

            // Vérifier si le jour a des horaires ou non
            if (!isset($schedules[$day_en]) || !is_array($schedules[$day_en])) {
                $schedule_list .= "<div class='schedule__row'><span class='schedule__day'>$is_today</span><span>Fermé</span></div>";
                continue;
            }

            // Récupérer les horaires
            $day_schedule = $schedules[$day_en];
            $morning_start = format_hour($day_schedule['morning']['start'] ?? '');
            $morning_end = format_hour($day_schedule['morning']['end'] ?? '');
            $afternoon_start = format_hour($day_schedule['afternoon']['start'] ?? '');
            $afternoon_end = format_hour($day_schedule['afternoon']['end'] ?? '');

            $horaires = [];

            if (!empty($morning_start) && !empty($morning_end)) {
                $horaires[] = "$morning_start - $morning_end";
            } elseif (!empty($morning_start)) {
                $horaires[] = "$morning_start";
            }

            if (!empty($afternoon_start) && !empty($afternoon_end)) {
                $horaires[] = "$afternoon_start - $afternoon_end";
            } elseif (!empty($afternoon_end)) {
                $horaires[] = "$afternoon_end";
            }

            // Gérer le cas où un horaire est unique (ex: juste "9h00" ou "19h00")
            if (count($horaires) == 1) {
                $horaires_str = $horaires[0];
            } elseif (count($horaires) > 1) {
                $horaires_str = implode(' / ', $horaires);
            } else {
                $horaires_str = "Fermé";
            }

            // Mettre en gras les horaires du jour actuel
            $horaires_str = ($day_en == $current_day) ? "<div class='schedule__hours schedule__hours--active'>$horaires_str</div>" : "<div class='schedule__hours'>$horaires_str</div>";

            // Gérer les coupures
            if ($day_en === 'sunday' && strpos($horaires_str, ' / ') !== false) {
                $horaires_str = str_replace(' / ', ' - ', $horaires_str);
            }

            $schedule_list .= "<div class='schedule__row'>$is_today $horaires_str</div>";
        }

        $schedule_list .= "</div></details>";

        return $schedule_list;
    } elseif ($template == "short") {
        return $opening_message;
    }
}

// Ajouter un shortcode pour afficher les horaires d'une boutique spécifique avec le jour actuel en premier
add_shortcode('schedules_current', 'display_schedules');

// Function pour afficher le statut du centre commercial
function display_mall_message()
{
    $schedules = get_field("schedules", "option");
    if (!$schedules || !is_array($schedules)) {
        return "<p>Aucun horaire disponible</p>";
    }

    $days = [
        'monday'    => 'Lundi',
        'tuesday'   => 'Mardi',
        'wednesday' => 'Mercredi',
        'thursday'  => 'Jeudi',
        'friday'    => 'Vendredi',
        'saturday'  => 'Samedi',
        'sunday'    => 'Dimanche'
    ];

    setlocale(LC_TIME, "fr_FR.UTF-8");
    $current_day = strtolower(date('l')); // Jour (ex: "monday")
    $now = date('H:i');
    $closing_hour = '';

    $today_schedule = $schedules[$current_day] ?? null;
    if ($today_schedule && is_array($today_schedule)) {
        $morning_end = format_hour($today_schedule['morning']['end'] ?? '');
        $afternoon_end = format_hour($today_schedule['afternoon']['end'] ?? '');
        $closing_hour = !empty($afternoon_end) ? $afternoon_end : $morning_end;
    }

    // Déterminer le message d'ouverture
    if ($closing_hour && $now < $closing_hour) {
        return '<span class="message">
		<span class="message__text--accent">Ouvert </span>
		<span class="message__text">· Jusqu\'à</span> 
		<span class="message__text message__text--accent">$closing_hour</span>
		</span>';
    } else {
        return '<span class="message">
					<span class="message__text message__text--accent">Fermé actuellement </span>
               </span>';
    }
}

// Ajouter un shortcode pour afficher le message du centre commercial
add_shortcode('mall_message', 'display_mall_message');

// Fonction pour enqueuer les styles css
function schedules_styles()
{
    wp_enqueue_style('mall-schedules', plugins_url('css/schedules.css', __FILE__));
}
add_action('wp_enqueue_scripts', 'schedules_styles');


// Activation du plugin
function mp_activation_plugin() {}
register_activation_hook(__FILE__, 'mp_activation_plugin');

// Désactivation du plugin
function mp_desactivation_plugin() {}
register_deactivation_hook(__FILE__, 'mp_desactivation_plugin');
