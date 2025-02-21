<?php

/**
 * Plugin Name: Mall Settings
 * Description: Un plugin personnalisé pour afficher les horaires d'ouverture des boutiques.
 * Version: 1.1.0
 * Author: Placeloop
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Vérifier si ACF est actif avant d'utiliser ses fonctions
 */
if (!function_exists('get_field')) {
    return;
}

/**
 * Formater une heure au format 9h00 au lieu de 0:00:00
 */
function format_hour($hour)
{
    if (empty($hour)) return '';
    return date('G\hi', strtotime($hour));
}

/**
 * Fonction pour obtenir les horaires d'ouverture des boutiques et du centre commercial
 */
function get_opening_hours($is_mall, $shop_id) {
    $schedules = $is_mall ? get_field("schedules", "option") : get_field("schedules", $shop_id);
    if (!$schedules || !is_array($schedules)) {
        return [];
    }
    return $schedules;
}

/**
 * Fonction pour récupérer le jour actuel en français
 */
function get_current_day()
{
    setlocale(LC_TIME, "fr_FR.UTF-8");
    return strtolower(date('l'));
}

/**
 * Fonction pour récupérer l'heure actuelle
 */
function get_current_time()
{
    return date('H:i');
}

function get_current_date()
{
    setlocale(LC_TIME, "fr_FR.UTF-8");
    $date = new DateTime();
    return $date->format('l d F'); 
}

/**
 * Obtenir la liste des jours de la semaine avec aujourd'hui en premier
 */
function get_ordered_days()
{
    $days = [
        'monday' => 'Lundi',
        'tuesday' => 'Mardi',
        'wednesday' => 'Mercredi',
        'thursday' => 'Jeudi',
        'friday' => 'Vendredi',
        'saturday' => 'Samedi',
        'sunday' => 'Dimanche'
    ];
    $current_day = strtolower(date('l'));
    return array_slice($days, array_search($current_day, array_keys($days)), null, true) +
        array_slice($days, 0, array_search($current_day, array_keys($days)), true);
}

/**
 * Obtenir l'heure de fermeture du jour actuel
 */
function get_closing_hour($today_schedule)
{
    $morning_end = format_hour($today_schedule['morning']['end'] ?? '');
    $afternoon_end = format_hour($today_schedule['afternoon']['end'] ?? '');
    return !empty($afternoon_end) ? $afternoon_end : $morning_end;
}


/**
 * Fonction pour générer le message d'ouverture
 */
function generate_opening_message($closing_hour, $now, $ordered_days, $schedules, $current_day)
{
    $current_date = get_current_date(); // Récupérer la date du jour

    if ($closing_hour && $now < $closing_hour) {
        return "<span class='schedules_status'>Ouvert</span> · Ferme à <span class='schedules_time'>$closing_hour</span><br><span class='schedules_date'></span>";
    }

    foreach ($ordered_days as $next_day_key => $next_day_fr) {
        if ($next_day_key == $current_day) {
            continue;
        }
        if (isset($schedules[$next_day_key]) && is_array($schedules[$next_day_key])) {
            $next_opening = format_hour($schedules[$next_day_key]['morning']['start'] ?? '');
            if ($next_opening) {
                return "<span class='schedules_status'>Ouvre demain</span> à <span class='schedules_time'>$next_opening</span><br><span class='schedules_date'></span>";
            }
        }
    }

    return "Fermé actuellement<br><span class='schedules_date'></span>";
}

/**
 * Fonction pour générer la liste des horaires du centre commercial et des boutiques
 */
function generate_schedule_list($template, $opening_message, $ordered_days, $current_day, $schedules)
{
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
        return "<p>Template inconnu</p>";
    }

    $date = new DateTime();
    foreach ($ordered_days as $day_en => $day_fr) {
        $is_today = ($day_en == $current_day) ? "schedule__day schedule__day--active" : "schedule__day";
       
        if ($day_en == $current_day) {
            $day_display = $date->format('l j F'); // Affiche le mois uniquement pour aujourd'hui
        } else {
            $day_display = $date->format('l j'); // Affiche seulement le jour et le numéro pour les autres jours
        }

        if (!isset($schedules[$day_en]) || !is_array($schedules[$day_en])) {
            $schedule_list .= "<div class='schedule__row'>
            <span class='$is_today'>$day_display</span>
            <span>Fermé</span></div>";
            $date->modify('+1 day');
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

        $schedule_list .= "<div class='schedule__row'><span class='$is_today'>$day_display</span>$horaires_str</div>";
        $date->modify('+1 day');
    }

    $schedule_list .= "</div></details></div>";
    return $schedule_list;
}

/**
 * Fonction principale pour afficher les horaires d'ouverture avec le jour actuel en premier
 */
function display_schedules($atts)
{
    $shop_id = get_the_ID();
    $is_mall = !$shop_id;
    $template = $atts['template'] ?? 'mall';

    if ($template === 'mall') {
        $is_mall = true;
    }

    $schedules = get_opening_hours($is_mall, $shop_id);

    if (!$schedules || !is_array($schedules)) {
        return "<p>Aucun horaire disponible</p>";
    }

    $current_day = get_current_day();
    $now = get_current_time();
    $ordered_days = get_ordered_days();

    $today_schedule = $schedules[$current_day] ?? null;
    $closing_hour = get_closing_hour($today_schedule);

    $opening_message = generate_opening_message($closing_hour, $now, $ordered_days, $schedules, $current_day);

    return generate_schedule_list($template, $opening_message, $ordered_days, $current_day, $schedules);
}

/**
 * Shortcode pour afficher les horaires du centre commercial [schedules_current template="mall"]
 * Shortcode pour les horaires des boutiques [schedules_current template="full"]
 * Shortcode pour afficher le message 'ouvert ou ouver demain [schedules_current template="short"]
 */
add_shortcode('schedules_current', 'display_schedules');

/**
 * Afficher le message de statut du centre commercial sur le bandeau de l'accueil
 */
function display_mall_message()
{
    $days = get_ordered_days();

    $schedules = get_field("schedules", "option");
    if (!$schedules || !is_array($schedules)) {
        return "<p>Aucun horaire disponible</p>";
    }

    $current_day = get_current_day();
    $now = get_current_time();
    $closing_hour = get_closing_hour($schedules[$current_day] ?? []);

    if ($closing_hour && $now < $closing_hour) {
        return "<span class='message'>
        <span class='message__text--accent'>Ouvert </span>
        <span class='message__text'>· Jusqu'à</span> 
        <span class='message__text message__text--accent'>$closing_hour</span>
        </span>";
    }

    foreach ($days as $next_day_key => $next_day_fr) {
        if (isset($schedules[$next_day_key]) && is_array($schedules[$next_day_key])) {
            $next_opening = format_hour($schedules[$next_day_key]['morning']['start'] ?? '');
            if ($next_opening) {
                return "<span class='message'>
                <span class='message__text'> Ouvre demain à </span>
                <span class='message__text message__text--accent'>$next_opening</span>
                </span>";
            }
        }
    }
}

/**
 * Shortcode pour afficher le statut du centre commercial [mall_message]
 */
add_shortcode('mall_message', 'display_mall_message');

/**
 * Afficher les offres des magasins
 */
function display_shop_offers() {

    $offer_fields = get_fields($post->ID); // Récupère tous les champs de l’Offer
    $shop_object  = $offer_fields['shop']; // Il s’agit d’un WP_Post (Post Object ACF)
     
    if ( is_object($shop_object) ) {
        // 1. Obtenir l'ID du shop
        $shop_id = $shop_object->ID;
		$shop_name = get_the_title($shop_id);
        $shop_logo = get_field('logo', $shop_id);
		$offer_title = get_the_title($post->ID);
       		
		$output = "<div class='shop'>";
        $output .= "<div class='shop__image'>";
        $output .= $shop_logo ? wp_get_attachment_image($shop_logo['ID'], 'full') : '';
        $output .= "</div>";
        $output .= "<div class='shop__content'>";
        $output .= "<div class='shop__name'><p>" . esc_html($shop_name) . "</p></div>";
        $output .= "<div class='shop__offer'><p>" . esc_html($offer_title) . "</p></div>";
        $output .= "</div>";
        $output .= "</div>";

        return $output;
    }

    echo "L'objet du shop n'est pas valide.<br>";
    return ''; // Retourne une chaîne vide si aucun shop n'est trouvé
	
}       // 2. Afficher un champ natif du shop : son titre, par exemple
//         echo 'Titre du Shop : ' . get_the_title($shop_id) . '<br/>';
//     echo 'Image: ' . get_field("logo", $shop_id) . '<br/>';
//     }

add_shortcode('shop_offers',  'display_shop_offers');


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