<?php
/**
 * Plugin Name: Mall Settings
 * Description: Un plugin personnalisé pour afficher les horaires d'ouverture des boutiques.
 * Version: 1.3.0
 * Author: Placeloop
 */

if (!defined('ABSPATH')) {
    exit;
}

setlocale(LC_TIME, "fr_FR.UTF-8");

if (!function_exists('get_field')) {
    return;
}


function get_today_datetime(): DateTime
{
    return new DateTime('today', new DateTimeZone('Europe/Paris'));
}

function get_now_datetime(): DateTime
{
    return new DateTime('now', new DateTimeZone('Europe/Paris'));
}

function format_hour(string $hour): string
{
    if (empty($hour)) {
        return '';
    }
    return date('H:i', strtotime($hour));
}

/**
 * Fonction pour obtenir les horaires d'ouverture (mall ou boutique)
 */
function get_opening_hours(bool $is_mall, int $shop_id): array
{
    $schedules = $is_mall 
        ? get_field("schedules", "option") 
        : get_field("schedules", $shop_id);

    if (!$schedules || !is_array($schedules)) {
        return [];
    }
    return $schedules;
}

/**
 * Fonction pour récupérer le jour actuel
 */
function get_current_day(): string
{
    $day = get_now_datetime();
    return strtolower($day->format('l'));
}

/**
 * Fonction pour récupérer l'heure actuelle
 */
function get_current_time(): string
{
    return get_now_datetime()->format('H:i');
}

/**
 * Obtenir la liste des jours de la semaine (avec aujourd'hui en premier)
 */
function get_ordered_days(): array
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
 * Convertit "H:i" en DateTime d'aujourd'hui (Europe/Paris)
 * Gère le cas "00:00" => minuit du jour suivant
 */
function parseDateTime(string $hour): ?DateTime
{
    $today = get_today_datetime();
    if (!empty($hour)) {
        [$H, $i] = explode(':', date('H:i', strtotime($hour)));
        // Si "00:00" => on considère minuit comme étant le lendemain
        if ($H == 0 && $i == 0) {
            $today->setTime(0, 0);
            $today->modify('+1 day');
        } else {
            $today->setTime((int)$H, (int)$i);
        }
        return $today;
    }
    return null;
}

/**
 * Détermine le statut d'ouverture
 * Retourne [ 'status' => 'open|later|tomorrow|closed', 'hour' => 'xx:xx' ]
 */
function get_schedule_status(
    array $schedules,
    array $ordered_days,
    string $current_day,
    string $now
): array {
   if (empty($schedules)) {
       return ['status' => 'closed', 'hour' => ''];
   }

   $today_schedule = $schedules[$current_day] ?? null;
   if (!$today_schedule || !is_array($today_schedule)) {
       $next_opening = find_next_day_opening($schedules, $ordered_days, $current_day);
       return ['status' => 'tomorrow', 'hour' => $next_opening ?: ''];
   }

   $ms = parseDateTime($today_schedule['morning']['start']   ?? '');
   $me = parseDateTime($today_schedule['morning']['end']     ?? '');
   $as = parseDateTime($today_schedule['afternoon']['start'] ?? '');
   $ae = parseDateTime($today_schedule['afternoon']['end']   ?? '');

   $nowDT    = parseDateTime($now) ?? get_now_datetime();
   $midnight = get_today_datetime();

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

   if ($ae && $nowDT >= $ae) {
       $next_opening = find_next_day_opening($schedules, $ordered_days, $current_day);
       return [
           'status' => 'tomorrow',
           'hour'   => $next_opening
       ];
   }

   $no_pause = (empty($today_schedule['morning']['end']) && empty($today_schedule['afternoon']['start']));
   if ($no_pause && $ms && $ae) {
       if ($nowDT >= $ms && $nowDT < $ae) {
           return [
               'status' => 'open',
               'hour'   => format_hour($today_schedule['afternoon']['end'] ?? '')
           ];
       }
   }

   // matin
   if ($ms && $me && $nowDT >= $ms && $nowDT < $me) {
       return [
           'status' => 'open',
           'hour'   => format_hour($today_schedule['morning']['end'] ?? '')
       ];
   }
   // après-midi
   if ($as && $ae && $nowDT >= $as && $nowDT < $ae) {
       return [
           'status' => 'open',
           'hour'   => format_hour($today_schedule['afternoon']['end'] ?? '')
       ];
   }

   return [
       'status' => 'closed',
       'hour'   => ''
   ];
}

/**
 * Cherche la prochaine ouverture le jour suivant (ou plus) en parcourant $ordered_days
 */
function find_next_day_opening(array $schedules, array $ordered_days, string $current_day): string
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
function render_resume_message(array $scheduleInfo): string
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
function render_schedules(
    string $template, 
    string $resume_message, 
    array $ordered_days, 
    string $current_day, 
    array $schedules
): string {
    if (!in_array($template, ['mall', 'shop'], true)) {
        return '<p>Template inconnu</p>';
    }

    $details_open = ($template === 'mall') ? ' open' : '';

    $schedule_list  = "<div class='schedule-wrapper'>\n";
    $schedule_list .= "  <details class='schedules'{$details_open}>\n";
    $schedule_list .= "    <summary class='schedules__summary'>{$resume_message}</summary>\n";
    $schedule_list .= "    <div class='schedule-container'>\n";

    foreach ($ordered_days as $day_en => $day_fr) {
        $timestamp  = ($day_en === $current_day)
            ? time()
            : strtotime("next $day_en");

        $format_str   = ($day_en === $current_day) ? '%A %d %B' : '%A %d';
        $day_display  = strftime($format_str, $timestamp);
        $day_schedule = $schedules[$day_en] ?? [];

        $morning_start   = format_hour($day_schedule['morning']['start'] ?? '');
        $morning_end     = format_hour($day_schedule['morning']['end']   ?? '');
        $afternoon_start = format_hour($day_schedule['afternoon']['start'] ?? '');
        $afternoon_end   = format_hour($day_schedule['afternoon']['end']   ?? '');

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
 *
 * - mall       : horaires du centre commercial + détails
 * - shop       : horaires d’une boutique + détails
 * - mall_short : juste le message du centre commercial
 * - shop_short : juste le message de la boutique
 */
function display_schedules(array $atts): string
{
    $template = $atts['template'] ?? 'mall';
    $shop_id  = get_the_ID() ?: 0;

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
    if (!$schedules) {
        return "<p>Aucun horaire disponible</p>";
    }

    $current_day    = get_current_day();
    $now            = get_current_time();
    $ordered_days   = get_ordered_days();
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
function display_offer_shop(): string
{
    global $post;
    if (!$post) {
        return '';
    }
    $offer_fields = get_fields($post->ID);
    $shop_object  = $offer_fields['shop'] ?? null;

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
add_shortcode('offer_shop', 'display_offer_shop');

function schedules_styles(): void
{
    wp_enqueue_style('mall-schedules', plugins_url('css/schedules.css', __FILE__));
}
add_action('wp_enqueue_scripts', 'schedules_styles');

function mp_activation_plugin(): void
{
    // Code à l'activation si besoin
}
register_activation_hook(__FILE__, 'mp_activation_plugin');

function mp_desactivation_plugin(): void
{
    // Code à la désactivation si besoin
}
register_deactivation_hook(__FILE__, 'mp_desactivation_plugin');