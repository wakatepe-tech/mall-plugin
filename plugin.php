<?php

/**
 * Plugin Name: Mall Settings
 * Description: Un plugin personnalisé pour afficher les horaires d'ouverture des boutiques.
 * Version: 1.2.0
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
    return date('H:i', strtotime($hour));
}

/**
 * Fonction pour obtenir les horaires d'ouverture des boutiques et du centre commercial
 */
function get_opening_hours($is_mall, $shop_id)
{
    // Si is_mall = true => récupère depuis get_field("schedules","option")
    // Sinon, pour une boutique, get_field("schedules", $shop_id)
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
    $day = new DateTime('now', new DateTimeZone('Europe/Paris'));
    return strtolower($day->format('l'));
}

/**
 * Fonction pour récupérer l'heure actuelle (format "H:i")
 */
function get_current_time()
{
    $date = new DateTime('now', new DateTimeZone('Europe/Paris'));
    return $date->format('H:i');
}

/**
 * Obtenir la liste des jours de la semaine (avec aujourd'hui en premier)
 */
function get_ordered_days()
{
    $days = [
        'monday'    => 'Lundi',
        'tuesday'   => 'Mardi',
        'wednesday' => 'Mercredi',
        'thursday'  => 'Jeudi',
        'friday'    => 'Vendredi',
        'saturday'  => 'Samedi',
        'sunday'    => 'Dimanche'
    ];

    $current_day = strtolower(date('l'));
    $keys        = array_keys($days);
    $current_pos = array_search($current_day, $keys);

    // Réordonne le tableau en commençant par le jour actuel
    return array_slice($days, $current_pos, null, true)
         + array_slice($days, 0, $current_pos, true);
}

/**
 * Obtenir l'heure de fermeture du jour actuel
 */
function get_closing_hour($today_schedule)
{
    $morning_end   = format_hour($today_schedule['morning']['end'] ?? '');
    $afternoon_end = format_hour($today_schedule['afternoon']['end'] ?? '');
    return !empty($afternoon_end) ? $afternoon_end : $morning_end;
}

/**
 * Obtenir d'ouv de fermeture du jour actuel
 */
function get_opening_hour($today_schedule)
{
    $morning_start   = format_hour($today_schedule['morning']['start'] ?? '');
    $afternoon_start = format_hour($today_schedule['afternoon']['start'] ?? '');
    return !empty($afternoon_start) ? $afternoon_start : $morning_start;
}
/**
 * Parse une heure "H:i" en objet DateTime
 */
function parseDateTime($hour)
{
    $today = new DateTime('today', new DateTimeZone('Europe/Paris'));
    if (!empty($hour)) {
        [$H,$i] = explode(':', date('H:i', strtotime($hour)));
        $today->setTime($H, $i);
        return $today;
    }
    return null;
}

/**
 * Renvoie le statut d'ouverture (ouvert / ouvre demain / fermé) et l'heure associée
 *
 * @param array  $schedules     Horaires (tel que renvoyé par get_opening_hours())
 * @param array  $ordered_days  Jours ordonnés (ex. ['monday'=>'Lundi', ...])
 * @param string $current_day   Jour actuel en anglais
 * @param string $now           Heure courante "H:i"
 * @return array
 *     [
 *       'status' => 'open' | 'later' | 'closed',
 *       'hour'   => heure de fermeture (si open) OU prochaine ouverture (si later)
 *     ]
 */
function get_schedule_status($schedules, $ordered_days, $current_day, $now)
{
    if (empty($schedules) || !is_array($schedules)) {
        return ['status' => 'closed', 'hour' => ''];
    }

    $today_schedule = $schedules[$current_day] ?? null;
    $closing_hour   = get_closing_hour($today_schedule);
    $opening_hour   = get_opening_hour($today_schedule);

    $nowDT   = parseDateTime($now);
    $closeDT = parseDateTime($closing_hour);
    $openDT = parseDateTime($opening_hour);

    // 1) S'il est encore ouvert
    if ($nowDT > $openDT && $nowDT < $closeDT) {
        return [
            'status' => 'open',
            'hour'   => $closing_hour
        ];
    }

    // 2) Sinon, chercher la prochaine ouverture (demain matin)
    foreach ($ordered_days as $day_en => $day_fr) {
        if ($day_en === $current_day) {
            continue;
        }
        if (!empty($schedules[$day_en]) && is_array($schedules[$day_en])) {
            $next_opening = format_hour($schedules[$day_en]['morning']['start'] ?? '');
            if ($next_opening) {
                return [
                    'status' => 'later',
                    'hour'   => $next_opening
                ];
            }
        }
    }

    // 3) Fermé sinon
    return [
        'status' => 'closed',
        'hour'   => ''
    ];
}

/**
 * Génère un message selon le statut ('open', 'later', 'closed')
 * @param array $scheduleInfo  Ex : ['status'=>'open','hour'=>'19:00']
 * 
 * @return string HTML
 */
function render_resume_message($scheduleInfo)
{
    $status = $scheduleInfo['status'];
    $hour   = $scheduleInfo['hour'];

    switch ($status) {
        case 'open':
            return "
                <span class='schedules__status'>Ouvert</span>
                <span class='schedules__label'> · Jusqu'à </span>
                <span class='schedules__time'>{$hour}</span>
            ";

        case 'later':
            return "
                <span class='schedules__status'>Ouvre demain</span>
                <span class='schedules__label'> à </span>
                <span class='schedules__time'>{$hour}</span>
            ";

        default:
        case 'closed':
            return "
                <span class='schedules__status'>Fermé actuellement</span>
            ";
    }
}

/**
 * Génère la liste des horaires + le résumé (ouvert / fermé...) au-dessus
 *
 * @param string $template       "mall", "shop" ou "message"
 * @param string $resume_message Le texte affiché comme résumé (HTML)
 * @param array  $ordered_days   Jours ordonnés
 * @param string $current_day    Jour actuel en anglais
 * @param array  $schedules      Tableau associatif des horaires
 *
 * @return string HTML
 */
function render_schedules($template, $resume_message, $ordered_days, $current_day, $schedules)
{
    setlocale(LC_TIME, 'fr_FR.UTF-8');

    if ($template === 'mall_short' || $template === 'shop_short') {
        return "<span class='message'>{$resume_message}</span>";
    }

    if (!in_array($template, ['mall', 'shop'], true)) {
        return '<p>Template inconnu</p>';
    }
    $details_open = ($template === 'mall') ? ' open' : '';

    $schedule_list  = "<div class='schedule-wrapper'>\n";
    $schedule_list .= "  <details class='schedules'{$details_open}>\n";
    $schedule_list .= "    <summary class='schedules__summary'>{$resume_message}</summary>\n";
    $schedule_list .= "    <div class='schedule-container'>\n";

    foreach ($ordered_days as $day_en => $day_fr) {
        $timestamp  = ($day_en === $current_day) ? time() : strtotime("next $day_en");
        $format_str = ($day_en === $current_day) ? '%A %d %B' : '%A %d';

        $day_display  = strftime($format_str, $timestamp);
        $day_schedule = $schedules[$day_en] ?? [];

        $morning_start   = format_hour($day_schedule['morning']['start'] ?? '');
        $morning_end     = format_hour($day_schedule['morning']['end'] ?? '');
        $afternoon_start = format_hour($day_schedule['afternoon']['start'] ?? '');
        $afternoon_end   = format_hour($day_schedule['afternoon']['end'] ?? '');

        $horaires = [];
        if ($morning_start && $morning_end) {
            $horaires[] = "$morning_start - $morning_end";
        } elseif ($morning_start) {
            $horaires[] = $morning_start;
        }

        if ($afternoon_start && $afternoon_end) {
            $horaires[] = "$afternoon_start - $afternoon_end";
        } elseif ($afternoon_end) {
            $horaires[] = $afternoon_end;
        }

        $horaires_str = $horaires ? implode(' / ', $horaires) : 'Fermé';

        $horaires_class = ($day_en === $current_day)
            ? 'schedule__hours schedule__hours--active'
            : 'schedule__hours';

        $is_today_class = ($day_en === $current_day)
            ? 'schedule__day schedule__day--active'
            : 'schedule__day';

        $schedule_list .= "      <div class='schedule__row'>\n";
        $schedule_list .= "        <div class='$is_today_class'>$day_display</div>\n";
        $schedule_list .= "        <div class='$horaires_class'>$horaires_str</div>\n";
        $schedule_list .= "      </div>\n";
    }

    $schedule_list .= "    </div>\n";
    $schedule_list .= "  </details>\n";
    $schedule_list .= "</div>\n";

    return $schedule_list;
}

/**
 * Shortcode principal : [schedules template="mall|shop|mall_short|shop_short"]
 * - mall       : horaires du centre commercial + détails
 * - shop       : horaires d’une boutique + détails
 * - mall_short : horaires du centre commercial
 * _ shop_short : horaires d’une boutique + détails
 */
function display_schedules($atts)
{
    $template = $atts['template'] ?? 'mall';
    $shop_id  = get_the_ID();

    switch ($template) {
        case 'mall':
        case 'mall_short':
            $is_mall = true;
            break;

        case 'shop':
        case 'shop_short':
        default:
            $is_mall = false;
            break;
    }

    $schedules = get_opening_hours($is_mall, $shop_id);
    if (!$schedules || !is_array($schedules)) {
        return "<p>Aucun horaire disponible</p>";
    }

    $current_day   = get_current_day();
    $now           = get_current_time();
    $ordered_days  = get_ordered_days();

    $statusInfo     = get_schedule_status($schedules, $ordered_days, $current_day, $now);
    $resume_message = render_resume_message($statusInfo);

    return render_schedules($template, $resume_message, $ordered_days, $current_day, $schedules);
}

add_shortcode('schedules', 'display_schedules');

/**
 * Shortcode PostObject : [offer_shop]
 * Affiche le shop associé à l'offre
 */
function display_offer_shop()
{
    global $post;
    $offer_fields = get_fields($post->ID);
    $shop_object  = $offer_fields['shop']; 

    if (is_object($shop_object)) {
        $shop_id     = $shop_object->ID;
        $shop_name   = get_the_title($shop_id);
        $shop_logo   = get_field('logo', $shop_id);
        $offer_title = get_the_title($post->ID);

        $offerShop  = "<div class='offerShop'>";
        $offerShop .= "<div class='offerShop__image'>";
        $offerShop .= $shop_logo ? wp_get_attachment_image($shop_logo['ID'], 'full') : '';
        $offerShop .= "</div>";
        $offerShop .= "<div class='offerShop__content'>";
        $offerShop .= "<div class='offerShop__name'><p>" . esc_html($shop_name) . "</p></div>";
        $offerShop .= "<div class='offerShop__offer'><p>" . esc_html($offer_title) . "</p></div>";
        $offerShop .= "</div>";
        $offerShop .= "</div>";

        return $offerShop;
    }

    return ''; 
}
add_shortcode('offer_shop',  'display_offer_shop');

function schedules_styles() {
    wp_enqueue_style('mall-schedules', plugins_url('css/schedules.css', __FILE__));
}
add_action('wp_enqueue_scripts', 'schedules_styles');

function mp_activation_plugin() {
}
register_activation_hook(__FILE__, 'mp_activation_plugin');

function mp_desactivation_plugin() {
}
register_deactivation_hook(__FILE__, 'mp_desactivation_plugin');
