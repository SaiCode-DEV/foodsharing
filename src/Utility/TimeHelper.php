<?php

namespace Foodsharing\Utility;

use Carbon\Carbon;
use DateTime;
use Exception;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

final class TimeHelper
{
    private readonly TranslatorInterface $translator;

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    // given a unix time it provides a human readable full date format.
    // parameter $extendWithAbsoluteDate == true adds the date between "today/tomorrow" and the time while false leaves it empty.
    public function niceDate(?int $unixTimeStamp, bool $extendWithAbsoluteDate = false): string
    {
        if ($unixTimeStamp === null) {
            return '- -';
        }

        $date = Carbon::createFromTimestamp($unixTimeStamp);

        if ($date->isToday()) {
            $dateString = $this->translator->trans('date.today');
        } elseif ($date->isTomorrow()) {
            $dateString = $this->translator->trans('date.tomorrow');
        } elseif ($date->isYesterday()) {
            $dateString = $this->translator->trans('date.yesterday');
        } else {
            $dateString = '';
            $extendWithAbsoluteDate = true;
        }

        if ($extendWithAbsoluteDate) {
            $dateString .= $this->getDow(intval(date('w', $unixTimeStamp))) . ', '
                . (int)date('d', $unixTimeStamp) . '. '
                . $this->translator->trans('month.short.' . date('n', $unixTimeStamp));

            $year = date('Y', $unixTimeStamp);
            if ($year != date('Y')) {
                $dateString .= ' ' . $year;
            }
        }

        return $dateString . ', ' . $this->translator->trans('date.time', [
            '{time}' => date('H:i', $unixTimeStamp),
        ]);
    }

    public function niceDateShort(int $ts): string
    {
        if (date('Y-m-d', $ts) === date('Y-m-d')) {
            return $this->translator->trans('date.Today') . ' ' . date('H:i', $ts);
        }

        return date('j.m.Y. H:i', $ts);
    }

    public function month(int $ts): string
    {
        return $this->translator->trans('month.' . intval(date('m', $ts)));
    }

    public function getDow(int $day): string
    {
        return [
            1 => $this->translator->trans('date.monday'),
            2 => $this->translator->trans('date.tuesday'),
            3 => $this->translator->trans('date.wednesday'),
            4 => $this->translator->trans('date.thursday'),
            5 => $this->translator->trans('date.friday'),
            6 => $this->translator->trans('date.saturday'),
            0 => $this->translator->trans('date.sunday'),
        ][$day];
    }

    public static function parsePickupDate(string $pickupDate): Carbon
    {
        try {
            $date = new Carbon($pickupDate);
            $date->setTimezone('Europe/Berlin');

            return $date;
        } catch (Exception) {
            throw new BadRequestHttpException('Invalid date format');
        }
    }

    /**
     * Returns the number of days by which the date is in the future, or 0 if the date is in the past.
     */
    public function daysInFuture(DateTime $date): int
    {
        if (Carbon::instance($date)->isPast()) {
            return 0;
        }

        return Carbon::today()->diffInDays($date);
    }

    /**
     * Parses a date time string and adds the specified interval. Returns the end date of the interval.
     *
     * @param string $dateString a date time string of the form 'Y-m-d H:i:s'
     * @param string $addInterval any parseable interval string
     * @return Carbon the end date
     * @throws BadRequestHttpException if either the date string or the interval could not be parsed
     */
    public function calculateEndDate(string $dateString, string $addInterval): Carbon
    {
        if (empty($dateString)) {
            throw new BadRequestHttpException('missing date');
        }
        $parsedDate = Carbon::createFromFormat('Y-m-d H:i:s', $dateString);

        if ($parsedDate === false) {
            throw new BadRequestHttpException('Invalid date format. Expected Y-m-d');
        }

        $value = $parsedDate->modify($addInterval);
        if (!$value) {
            throw new BadRequestHttpException('Invalid interval string');
        }

        return $value;
    }
}
