<?php

/**
 * Plugin Name: Mall Settings
 * Description: Un plugin personnalisé pour afficher les horaires d'ouverture des boutiques.
 * Version: 1.1.0
 * Author: Placeloop
 */

if (!defined('ABSPATH')) {
    exit; // Empêche l'accès direct
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

// Fonction pour afficher les horaires d'ouverture avec le jour actuel en premier 
function display_schedules($atts)
{
    $shop_id = get_the_ID();
    $is_mall = !$shop_id;
    $template = $atts['template'] ?? 'mall';

    if ($template === 'mall') {
        $is_mall = true;
    }

    // Récupérer les horaires d'ouverture
    $schedules = $is_mall ? get_field("schedules", "option") : get_field("schedules", $shop_id);

    if (!$schedules || !is_array($schedules)) {
        return "<p>Aucun horaire disponible</p>";
    }

    // Jours de la semaine en français
    $days = [
        'monday'    => 'Lundi',
        'tuesday'   => 'Mardi',
        'wednesday' => 'Mercredi',
        'thursday'  => 'Jeudi',
        'friday'    => 'Vendredi',
        'saturday'  => 'Samedi',
        'sunday'    => 'Dimanche'
    ];

    // Définir le jour actuel
    setlocale(LC_TIME, "fr_FR.UTF-8");
    $current_day = strtolower(date('l'));
    $now = date('H:i');

    // Construire l'ordre des jours (aujourd'hui en premier)
    $ordered_days = array_slice($days, array_search($current_day, array_keys($days)), null, true) +
        array_slice($days, 0, array_search($current_day, array_keys($days)), true);

    // Trouver l'heure de fermeture du jour actuel
    $today_schedule = $schedules[$current_day] ?? null;
    $closing_hour = '';

    if ($today_schedule && is_array($today_schedule)) {
        $morning_end = format_hour($today_schedule['morning']['end'] ?? '');
        $afternoon_end = format_hour($today_schedule['afternoon']['end'] ?? '');
        $closing_hour = !empty($afternoon_end) ? $afternoon_end : $morning_end;
    }

    // Déterminer le message d'ouverture
    if ($closing_hour && $now < $closing_hour) {
        // Si l'heure actuelle est avant l'heure de fermeture
        $opening_message = "<span class='schedules_status'>Ouvert</span> · Ferme à <span class='schedules_time'>$closing_hour</span>";
    } else {
        // Trouver le prochain jour ouvré
        $found_next_day = false;
        foreach ($ordered_days as $next_day_key => $next_day_fr) {
            if ($next_day_key == $current_day) {
                continue; // Sauter le jour actuel
            }
            if (isset($schedules[$next_day_key]) && is_array($schedules[$next_day_key])) {
                $next_opening = format_hour($schedules[$next_day_key]['morning']['start'] ?? '');
                if ($next_opening) {
                    //                     echo "Le jour récupéré est : $next_day_fr"; // Ajout de l'echo pour vérifier le jour
                    $opening_message = "<span class='schedules_status'>Ouvre demain</span> à <span class='schedules_time'>$next_opening</span>";
                    $found_next_day = true;
                    break;
                }
            }
        }

        if (!$found_next_day) {
            $opening_message = "Fermé actuellement";
        }
    }

    // Générer l'affichage selon le template
    if ($template === "mall") {
        $schedule_list = "<div class='schedule-wrapper'>
                          <details class='schedules' open>
                              <summary class='schedules__summary'>$opening_message</summary>
                              <div class='schedule-container'>";
    } elseif ($template === "full") {
        $schedule_list = "<div class='schedule-wrapper'>
                          <details class='schedules'>
                              <summary class='schedules__summary'>$opening_message</summary>
                              <div class='schedule-container'>";
    } elseif ($template === "short") {
        return $opening_message;
    } else {
        return "<p>Template inconnu</p>"; // Sécurité en cas de template inconnu
    }

    // Liste des horaires par jour
    foreach ($ordered_days as $day_en => $day_fr) {
        $is_today = ($day_en == $current_day) ? "schedule__day schedule__day--active" : "schedule__day";

        if (!isset($schedules[$day_en]) || !is_array($schedules[$day_en])) {
            $schedule_list .= "<div class='schedule__row'><span class='$is_today'>$day_fr</span><span>Fermé</span></div>";
            continue;
        }

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

        $horaires_str = !empty($horaires) ? implode(' / ', $horaires) : "Fermé";
        $horaires_str = ($day_en == $current_day) ? "<div class='schedule__hours schedule__hours--active'>$horaires_str</div>" : "<div class='schedule__hours'>$horaires_str</div>";

        $schedule_list .= "<div class='schedule__row'>
                           <span class='$is_today'>$day_fr</span>
                           $horaires_str
                           </div>";
    }

    // Fermeture des balises <details>
    $schedule_list .= "</div></details></div>";
    return $schedule_list;
}

add_shortcode('schedules_current', 'display_schedules');


add_shortcode('schedules_current', 'display_schedules');

/**
 * Affiche le statut du centre commercial (ouvert/fermé) et l'heure de fermeture.
 * Shortcode: [mall_message]
 */
// Fonction pour afficher le statut du centre commercial
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
        return "Ouvert · Jusqu'à $closing_hour";
    } else {
        return "Fermé actuellement";
    }
}

add_shortcode('mall_message', 'display_mall_message');

// Fonction pour récupérer les données des shops
function get_shop_offers() {
    $args = array(
        'post_type' => 'offers',
        'posts_per_page' => -1,
    );
    
    $query = new WP_Query($args);
    $shop_offers = array();

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $shop_post = get_field('shop');
            
            if ($shop_post) {
                $shop_name = get_the_title($shop_post->ID); 
                $shop_logo = get_field('logo', $shop_post->ID); 
 
				// Si le champ 'logo' est de type 'Image', nous devons récupérer l'ID de l'image
                if ($shop_logo) {
                    $shop_logo_id = $shop_logo['ID'];
                } else {
                    $shop_logo_id = '';
                }
				
                // Vérifiez que les champs ne sont pas vides
                if ($shop_name && $shop_logo_id) {
                    $shop_offers[] = array(
                        'name' => $shop_name,
                        'logo' => $shop_logo_id
                    );
                }
            }
        }
    }
    wp_reset_postdata();

    return $shop_offers;
}

// Fonction pour afficher les données des shops avec le shortcode :[shop_offers]
function display_shop_offers() {
    $shops = get_shop_offers();
    $output = '<div class="shop-offers">';

    foreach ($shops as $shop) {
        if ($shop['logo']) {
            $output .= '<div class="shop-offer">';
            $output .=  wp_get_attachment_image($shop['logo'], 'medium');
            $output .= '<p>' . esc_html($shop['name']) . '</p>';
            $output .= '</div>';
        }
    }

    $output .= '</div>';
    return $output;
}
add_shortcode('shop_offers',  'display_shop_offers');

function schedules_styles()
{
    wp_enqueue_style('mall-schedules', plugins_url('css/schedules.css', __FILE__));
}
add_action('wp_enqueue_scripts', 'schedules_styles');

function mp_activation_plugin()
{
    // Code éventuel à l'activation
}
register_activation_hook(__FILE__, 'mp_activation_plugin');

function mp_desactivation_plugin()
{
    // Code éventuel à la désactivation
}
register_deactivation_hook(__FILE__, 'mp_desactivation_plugin');
