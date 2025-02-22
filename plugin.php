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
function format_hour(string $hour)
{
    if (empty($hour)) return '';
    return date('H:i', strtotime($hour));
}

/**
 * Fonction pour obtenir les horaires d'ouverture des boutiques et du centre commercial
 */
function get_opening_hours(bool $is_mall, int $shop_id)
{
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

    $current_day = get_current_day(); 
    $keys        = array_keys($days);
    $current_pos = array_search($current_day, $keys);

    $ordered = array_slice($days, $current_pos, null, true)
             + array_slice($days, 0, $current_pos, true);

    return array_slice($ordered, 0, 7, true);
}

/**
 * Obtenir l'heure d'ouverture du jour actuel
 */
function get_opening_hour(array $today_schedule)
{
    $morning_start   = format_hour($today_schedule['morning']['start'] ?? '');
    $afternoon_start = format_hour($today_schedule['afternoon']['start'] ?? '');
    return !empty($afternoon_start) ? $afternoon_start : $morning_start;
}

/**
 * Convertit "H:i" en DateTime(aujourd'hui)
 */
function parseDateTime(string $hour)
{
    $today = new DateTime('today', new DateTimeZone('Europe/Paris'));
    if (!empty($hour)) {
        [$H, $i] = explode(':', date('H:i', strtotime($hour)));
        if ($H == 0 && $i == 0) {
            $today->setTime(0, 0);
            $today->modify('+1 day');
        } else {
            $today->setTime($H, $i);
        }
        return $today;
    }
    return null;
}

/**
* Détermine le statut d'ouverture
* en retournant un tableau [ 'status' => 'open|later|tomorrow|closed', 'hour' => 'xx:xx' ]
*/
function get_schedule_status(array $schedules, array $ordered_days, string $current_day, string $now)
{

   if (empty($schedules) || !is_array($schedules)) {
       return ['status' => 'closed', 'hour' => ''];
   }

   $today_schedule = $schedules[$current_day] ?? null;
   if (!$today_schedule || !is_array($today_schedule)) {
       $next_opening = find_next_day_opening($schedules, $ordered_days, $current_day);
       return ['status' => 'tomorrow', 'hour' => $next_opening ?: ''];
   }

   $ms = parseDateTime($today_schedule['morning']['start']   ?? null); 
   $me = parseDateTime($today_schedule['morning']['end']     ?? null);
   $as = parseDateTime($today_schedule['afternoon']['start'] ?? null);
   $ae = parseDateTime($today_schedule['afternoon']['end']   ?? null);

   $nowDT = parseDateTime($now);
   $midnight = (new DateTime('today', new DateTimeZone('Europe/Paris')))->setTime(0, 0);

   // (00:00 → morning_start) OU (morning_end → afternoon_start)
   if ($ms && $nowDT >= $midnight && $nowDT < $ms) {
       // plus tard => ouvre au matin
       return [
           'status' => 'later',
           'hour'   => format_hour($today_schedule['morning']['start'] ?? '')
       ];
   }
   if ($me && $as && $nowDT >= $me && $nowDT < $as) {
       // plus tard => ouvre à l'après-midi
       return [
           'status' => 'later',
           'hour'   => format_hour($today_schedule['afternoon']['start'] ?? '')
       ];
   }

   // (après afternoon_end)
   if ($ae && $nowDT >= $ae) {
       $next_opening = find_next_day_opening($schedules, $ordered_days, $current_day);
       return [
           'status' => 'tomorrow',
           'hour'   => $next_opening
       ];
   }

   // si pas de pause => [morning_start, afternoon_end]
   // sinon [morning_start, morning_end] ou [afternoon_start, afternoon_end]
   $no_pause = (empty($today_schedule['morning']['end']) && empty($today_schedule['afternoon']['start']));
   if ($no_pause && $ms && $ae) {
       if ($nowDT >= $ms && $nowDT < $ae) {
           return [
               'status' => 'open',
               'hour'   => format_hour($today_schedule['afternoon']['end'] ?? '')
           ];
       }
   }

   // sinon on check matin
   if ($ms && $me && $nowDT >= $ms && $nowDT < $me) {
       return [
           'status' => 'open',
           'hour'   => format_hour($today_schedule['morning']['end'] ?? '')
       ];
   }
   // check après-midi
   if ($as && $ae && $nowDT >= $as && $nowDT < $ae) {
       return [
           'status' => 'open',
           'hour'   => format_hour($today_schedule['afternoon']['end'] ?? '')
       ];
   }

   // 6) Sinon => closed
   return [
       'status' => 'closed',
       'hour'   => ''
   ];
}

/**
 * Cherche la prochaine ouverture le jour suivant (ou +), en parcourant $ordered_days
 */
function find_next_day_opening(array $schedules, array $ordered_days, string $current_day)
{
    $found_current = false;

    foreach ($ordered_days as $day_en => $day_fr) {
        if (!$found_current) {
            if ($day_en === $current_day) {
                $found_current = true;
            }
            continue;
        }
        if (!empty($schedules[$day_en]['morning']['start'])) {
            return format_hour($schedules[$day_en]['morning']['start']);
        }
    }
    return '';
}


/**
 * Génère un message personnalisé selon le statut
 */
function render_resume_message(array $scheduleInfo)
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
                <span class='schedules__status'>Ouvre à</span>
                <span class='schedules__time'>{$hour}</span>
            ";

        case 'tomorrow':
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
 * Génère la liste des horaires + le résumé au-dessus
 */
function render_schedules(string $template, string $resume_message, array $ordered_days, string $current_day, array $schedules)
{
    setlocale(LC_TIME, 'fr_FR.UTF-8');

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
 * - mall_short : horaires du centre commercial (juste le message)
 * - shop_short : horaires d’une boutique (juste le message)
 */
function display_schedules(array $atts)
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

    $current_day  = get_current_day();
    $now          = get_current_time();
    $ordered_days = get_ordered_days();

    $statusInfo     = get_schedule_status($schedules, $ordered_days, $current_day, $now);
    $resume_message = render_resume_message($statusInfo);

    if ($template === 'mall_short' || $template === 'shop_short') {
        return "<span>{$resume_message}</span>";
    }

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