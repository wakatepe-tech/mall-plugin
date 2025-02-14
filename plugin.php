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

/**
 * Retourne la liste des jours en français,
 * indexés par leur nom anglais en minuscule (WordPress/PHP).
 */
function get_mall_days() {
    return [
        'monday'    => 'Lundi',
        'tuesday'   => 'Mardi',
        'wednesday' => 'Mercredi',
        'thursday'  => 'Jeudi',
        'friday'    => 'Vendredi',
        'saturday'  => 'Samedi',
        'sunday'    => 'Dimanche'
    ];
}

/**
 * Formatage d'une heure stockée en base (type string HH:MM:SS)
 * vers un format "9h00" ou "" si vide.
 */
function format_hour($hour) {
    if (empty($hour)) {
        return '';
    }
    $time = strtotime($hour);
    return date('G\hi', $time);
}

/**
 * Construit un tableau ordonné avec le jour actuel d'abord,
 * puis les jours suivants, et enfin les jours précédents.
 */
function get_ordered_days($current_day) {
    $days = get_mall_days();
    $ordered = [];
    $found   = false;

    foreach ($days as $day_en => $day_fr) {
        if ($day_en === $current_day) {
            $found = true;
        }
        if ($found) {
            $ordered[$day_en] = $day_fr;
        }
    }

    // Ajouter les jours avant le jour actuel
    foreach ($days as $day_en => $day_fr) {
        if (!isset($ordered[$day_en])) {
            $ordered[$day_en] = $day_fr;
        }
    }

    return $ordered;
}

/**
 * Génère la chaîne de caractères décrivant les horaires
 * d'un seul jour, en combinant matin et après-midi.
 * Exemple: "9h00 - 12h00 / 14h00 - 19h00" ou "Fermé"
 */
function get_day_hours($day_schedule) {
    if (!is_array($day_schedule)) {
        return 'Fermé';
    }

    $morning_start   = format_hour($day_schedule['morning']['start'] ?? '');
    $morning_end     = format_hour($day_schedule['morning']['end']   ?? '');
    $afternoon_start = format_hour($day_schedule['afternoon']['start'] ?? '');
    $afternoon_end   = format_hour($day_schedule['afternoon']['end']   ?? '');

    // On construit des petits segments "9h00 - 12h00" selon ce qui est défini
    $horaires = [];
    if ($morning_start && $morning_end) {
        $horaires[] = "$morning_start - $morning_end";
    } elseif ($morning_start || $morning_end) {
        // Cas où il manque une info, on affiche ce qui existe
        $horaires[] = $morning_start ?: $morning_end;
    }

    if ($afternoon_start && $afternoon_end) {
        $horaires[] = "$afternoon_start - $afternoon_end";
    } elseif ($afternoon_start || $afternoon_end) {
        $horaires[] = $afternoon_start ?: $afternoon_end;
    }

    // Si rien n'est défini, c'est "Fermé"
    if (empty($horaires)) {
        return 'Fermé';
    }
    // On assemble avec " / " (matin / après-midi)
    return implode(' / ', $horaires);
}

/**
 * Affiche les horaires d’ouverture avec le jour actuel en premier.
 * Shortcode: [schedules_current template="full" ou "short" ou "mall"]
 */
function display_schedules($atts) {
    // Récupération de l'ID du post (boutique)
    $shop_id = get_the_ID();
	
	$is_mall = !$shop_id;
	
	$template = $atts['template'] ?? 'mall';
	
	if ($template === 'mall'){
		$is_mall = true;
	}
		 $schedules = $is_mall ? get_field("schedules", "option") : get_field("schedules", $shop_id);

	// Récupération des horaires via ACF

    if (!$schedules || !is_array($schedules)) {
        return "<p>Aucun horaire disponible</p>";
    }

    // Jour actuel
    setlocale(LC_TIME, "fr_FR.UTF-8");
    $current_day = strtolower(date('l')); // ex: 'monday'
    $now         = date('H:i');

    // On détermine l'heure de fermeture du jour actuel
    $today_schedule = $schedules[$current_day] ?? null;
    $closing_hour   = '';
    if ($today_schedule && is_array($today_schedule)) {
        // S’il y a un après-midi défini, on prend l'heure de fin d'après-midi,
        // sinon l'heure de fin de matinée
        $morning_end     = format_hour($today_schedule['morning']['end'] ?? '');
        $afternoon_end   = format_hour($today_schedule['afternoon']['end'] ?? '');
        $closing_hour    = !empty($afternoon_end) ? $afternoon_end : $morning_end;
    }

    // Construire le message d'ouverture/fermeture
    if ($closing_hour && $now < $closing_hour) {
        // Toujours ouvert aujourd'hui
        $opening_message = "<span class='schedules_status'>Ouvert</span> · Ferme à <span class='schedules_time'>$closing_hour</span>";
    } else {
        // Fermé aujourd'hui, on cherche le prochain jour ouvrable
        $all_days = array_keys(get_mall_days());
        $current_index = array_search($current_day, $all_days);
        // Prochain jour (en tenant compte du modulo pour la fin de semaine)
        $next_day_key = $all_days[($current_index + 1) % 7];
        $next_day_schedule = $schedules[$next_day_key] ?? null;

        if ($next_day_schedule && is_array($next_day_schedule)) {
            $next_opening = format_hour($next_day_schedule['morning']['start'] ?? '');
            if ($next_opening) {
                $opening_message = "<span class='schedules_status'>Ouvre</span> demain à <span class='schedules_time'>$next_opening</span>";
            } else {
                // Pas d'heure du matin ? On pourrait éventuellement prendre l'après-midi
                $opening_message = "Fermé actuellement";
            }
        } else {
            $opening_message = "Fermé actuellement";
        }
    }

   // Gestion du template
$template = $atts['template'] ?? 'short';

if ($template === 'full' || $template === 'mall') {
    // On va construire le tableau/liste des horaires
    $ordered_days = get_ordered_days($current_day);
    if ($template === 'mall') {
        $schedule_list = "<div class='schedules'>";
    } else {
        $schedule_list  = "<details class='schedules' " . ($is_mall ? "open" : "") . ">";
    }
    $schedule_list .= "<summary class='schedules__summary'>$opening_message</summary>";
    $schedule_list .= "<div>";

    foreach ($ordered_days as $day_en => $day_fr) {
        // DateTime "this monday" ne fonctionne pas toujours correctement selon le jour actuel,
        // mais c’est une approche possible. Sinon, on peut juste afficher le nom du jour.
        $day_label = $day_fr;
        
        // Vérifie si c'est le jour actuel pour styling
        $is_today_class = ($day_en === $current_day) ? 'schedule__day schedule__day--active' : 'schedule__day';
        
        // Récupère la chaîne horaires
        $day_hours_str  = get_day_hours($schedules[$day_en] ?? null);
        $hours_class    = ($day_en === $current_day) ? 'schedule__hours schedule__hours--active' : 'schedule__hours';

        $schedule_list .= "<div class='schedule__row'>
                              <span class='$is_today_class'>$day_label</span>
                              <div class='$hours_class'>$day_hours_str</div>
                           </div>";
    }

    if ($template === 'mall'){
        $schedule_list .= "</div>";
    } else {
        $schedule_list .= "</div></details>";
    }

    return $schedule_list;
} else {
    // Par défaut, template "short" : juste le message d'ouverture
    return $opening_message;
}
}

add_shortcode('schedules_current', 'display_schedules');

/**
 * Affiche le statut du centre commercial (ouvert/fermé) et l'heure de fermeture.
 * Shortcode: [mall_message]
 */
function display_mall_message() {
    // On récupère les horaires du centre via l'option (ACF sur "option")
    $schedules = get_field("schedules", "option");
    if (!$schedules || !is_array($schedules)) {
        return "<p>Aucun horaire disponible</p>";
    }

    setlocale(LC_TIME, "fr_FR.UTF-8");
    $current_day = strtolower(date('l'));
    $now         = date('H:i');

    // On trouve l'heure de fermeture du jour actuel
    $closing_hour = '';
    $today_schedule = $schedules[$current_day] ?? null;
    if ($today_schedule && is_array($today_schedule)) {
        $morning_end   = format_hour($today_schedule['morning']['end'] ?? '');
        $afternoon_end = format_hour($today_schedule['afternoon']['end'] ?? '');
        $closing_hour  = !empty($afternoon_end) ? $afternoon_end : $morning_end;
    }

    if ($closing_hour && $now < $closing_hour) {
        return "Ouvert · Jusqu'à $closing_hour";
    }
    return "Fermé actuellement";
}
add_shortcode('mall_message', 'display_mall_message');

function schedules_styles() {
    wp_enqueue_style('mall-schedules', plugins_url('css/schedules.css', __FILE__));
}
add_action('wp_enqueue_scripts', 'schedules_styles');

function mp_activation_plugin() {
    // Code éventuel à l'activation
}
register_activation_hook(__FILE__, 'mp_activation_plugin');

function mp_desactivation_plugin() {
    // Code éventuel à la désactivation
}
register_deactivation_hook(__FILE__, 'mp_desactivation_plugin');