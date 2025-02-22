<?php

class Schedules
{
    public function __construct()
    {
        add_shortcode('schedules', [$this, 'displaySchedules']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueStyles']);
    }

    public function formatHour(string $hour): string
    {
        if (empty($hour)) {
            return '';
        }
        return date('H:i', strtotime($hour));
    }

    public function getOpeningHours(bool $is_mall, int $shop_id): array
    {
        $schedules = $is_mall 
            ? get_field("schedules", "option")
            : get_field("schedules", $shop_id);

        return (is_array($schedules)) ? $schedules : [];
    }

    private function getTodayDateTime(): DateTime
    {
        return new DateTime('today', new DateTimeZone('Europe/Paris'));
    }

    private function getNowDateTime(): DateTime
    {
        return new DateTime('now', new DateTimeZone('Europe/Paris'));
    }

    public function parseDateTime(string $hour): ?DateTime
    {
        $today = $this->getTodayDateTime();
        if (!empty($hour)) {
            [$H, $i] = explode(':', date('H:i', strtotime($hour)));
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

    public function getOrderedDays(): array
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

        $current_day = strtolower($this->getNowDateTime()->format('l'));
        $keys        = array_keys($days);
        $current_pos = array_search($current_day, $keys);

        $ordered = array_slice($days, $current_pos, null, true)
                 + array_slice($days, 0, $current_pos, true);

        return array_slice($ordered, 0, 7, true);
    }

    public function getScheduleStatus(
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
            $next_opening = $this->findNextDayOpening($schedules, $ordered_days, $current_day);
            return ['status' => 'tomorrow', 'hour' => $next_opening ?: ''];
        }

        $ms = $this->parseDateTime($today_schedule['morning']['start'] ?? '');
        $me = $this->parseDateTime($today_schedule['morning']['end']   ?? '');
        $as = $this->parseDateTime($today_schedule['afternoon']['start'] ?? '');
        $ae = $this->parseDateTime($today_schedule['afternoon']['end']   ?? '');

        $nowDT    = $this->parseDateTime($now) ?: $this->getNowDateTime();
        $midnight = $this->getTodayDateTime();

        if ($ms && $nowDT >= $midnight && $nowDT < $ms) {
            return [
                'status' => 'later',
                'hour'   => $this->formatHour($today_schedule['morning']['start'] ?? '')
            ];
        }
        if ($me && $as && $nowDT >= $me && $nowDT < $as) {
            return [
                'status' => 'later',
                'hour'   => $this->formatHour($today_schedule['afternoon']['start'] ?? '')
            ];
        }

        if ($ae && $nowDT >= $ae) {
            $next_opening = $this->findNextDayOpening($schedules, $ordered_days, $current_day);
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
                    'hour'   => $this->formatHour($today_schedule['afternoon']['end'] ?? '')
                ];
            }
        }
        if ($ms && $me && $nowDT >= $ms && $nowDT < $me) {
            return [
                'status' => 'open',
                'hour'   => $this->formatHour($today_schedule['morning']['end'] ?? '')
            ];
        }
        if ($as && $ae && $nowDT >= $as && $nowDT < $ae) {
            return [
                'status' => 'open',
                'hour'   => $this->formatHour($today_schedule['afternoon']['end'] ?? '')
            ];
        }

        return [
            'status' => 'closed',
            'hour'   => ''
        ];
    }

    public function findNextDayOpening(
        array $schedules,
        array $ordered_days,
        string $current_day
    ): string {
        $found_current = false;

        foreach ($ordered_days as $day_en => $day_fr) {
            if (!$found_current) {
                if ($day_en === $current_day) {
                    $found_current = true;
                }
                continue;
            }
            if (!empty($schedules[$day_en]['morning']['start'])) {
                return $this->formatHour($schedules[$day_en]['morning']['start']);
            }
        }
        return '';
    }

    public function renderResumeMessage(array $scheduleInfo): string
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

    private function renderSchedules(
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

            $format_str  = ($day_en === $current_day) ? '%A %d %B' : '%A %d';
            $day_display = strftime($format_str, $timestamp);
            $day_schedule = $schedules[$day_en] ?? [];

            $morning_start   = $this->formatHour($day_schedule['morning']['start'] ?? '');
            $morning_end     = $this->formatHour($day_schedule['morning']['end']   ?? '');
            $afternoon_start = $this->formatHour($day_schedule['afternoon']['start'] ?? '');
            $afternoon_end   = $this->formatHour($day_schedule['afternoon']['end']   ?? '');

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

    public function displaySchedules(array $atts): string
    {
        $template = $atts['template'] ?? 'mall';
        $shop_id  = get_the_ID() ?: 0;

        $is_mall = in_array($template, ['mall', 'mall_short'], true);

        $schedules = $this->getOpeningHours($is_mall, $shop_id);
        if (!$schedules) {
            return "<p>Aucun horaire disponible</p>";
        }

        $current_day  = strtolower($this->getNowDateTime()->format('l'));
        $now          = $this->getNowDateTime()->format('H:i');
        $ordered_days = $this->getOrderedDays();

        $statusInfo     = $this->getScheduleStatus($schedules, $ordered_days, $current_day, $now);
        $resume_message = $this->renderResumeMessage($statusInfo);

        if ($template === 'mall_short' || $template === 'shop_short') {
            return "<span>{$resume_message}</span>";
        }

        return $this->renderSchedules($template, $resume_message, $ordered_days, $current_day, $schedules);
    }

    public function enqueueStyles(): void
    {
        wp_enqueue_style(
            'mall-settings',
            plugin_dir_url(dirname(__FILE__)) . 'css/schedules.css',
            []
        );
    }
}