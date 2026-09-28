<?php

namespace App\Support\Mail;

use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * The hours an account sends in, in its own timezone. Every moment that leaves
 * here for the database goes back to the app timezone first: Eloquent stores a
 * date in the timezone it carries.
 */
class SendingWindow
{
    public function __construct(
        private string $timezone,
        private int $from,
        private int $until,
        private bool $weekdaysOnly,
    ) {}

    public static function for(User $user): self
    {
        return new self($user->send_timezone, $user->send_from, $user->send_until, $user->send_weekdays_only);
    }

    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone);
    }

    /**
     * Start and end of the window on the day of the given moment.
     *
     * @return array{start: CarbonImmutable, end: CarbonImmutable}
     */
    public function on(CarbonImmutable $day): array
    {
        $day = $day->setTimezone($this->timezone);

        return [
            'start' => $day->setTime($this->from, 0),
            'end' => $day->setTime($this->until, 0),
        ];
    }

    /**
     * Seconds between opening and closing.
     */
    public function length(): int
    {
        return max(0, ($this->until - $this->from) * 3600);
    }

    public function isOpen(): bool
    {
        $now = $this->now();
        $window = $this->on($now);

        return $this->isSendingDay($now) && $now->between($window['start'], $window['end']);
    }

    /**
     * The same moment when it falls inside a window, otherwise the start of the next
     * one, a few minutes of jitter added so a batch does not leave at once.
     */
    public function inside(CarbonImmutable $moment): CarbonImmutable
    {
        $moment = $moment->setTimezone($this->timezone);
        $window = $this->on($moment);

        if ($moment->lessThan($window['start']) && $this->isSendingDay($moment)) {
            return $window['start']->addSeconds(random_int(0, 300));
        }

        if ($moment->greaterThanOrEqualTo($window['end']) || ! $this->isSendingDay($moment)) {
            return $this->nextAfter($moment)->addSeconds(random_int(0, 300));
        }

        return $moment;
    }

    /**
     * The opening of the first window after the given day.
     */
    public function nextAfter(CarbonImmutable $moment): CarbonImmutable
    {
        $day = $moment->setTimezone($this->timezone)->addDay();

        while (! $this->isSendingDay($day)) {
            $day = $day->addDay();
        }

        return $this->on($day)['start'];
    }

    /**
     * In the app timezone, ready to be saved or compared in a query.
     */
    public function toUtc(CarbonImmutable $moment): CarbonImmutable
    {
        return $moment->setTimezone(config('app.timezone'));
    }

    public function isSendingDay(CarbonImmutable $day): bool
    {
        return ! $this->weekdaysOnly || $day->isWeekday();
    }
}
