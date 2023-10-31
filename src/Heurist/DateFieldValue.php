<?php

namespace UtilityCli\Heurist;

class DateFieldValue extends GenericFieldValue
{
    /**
     * @var string $year
     *   The Year of the date.
     */
    protected string $year;

    /**
     * @var string $month
     *   The month of the date.
     */
    protected string $month;

    /**
     * @var string $day
     *   The day of the date.
     */
    protected string $day;

    /**
     * @var bool $isRange
     *   Whether the date value is a range.
     */
    protected bool $isRange = false;

    /**
     * @var DateFieldValue $tpq
     *   The Terminus Post Quem (start) of the range.
     */
    protected DateFieldValue $tpq;

    /**
     * @var DateFieldValue $taq
     *   The Terminus Ante Quem (end) of the range.
     */
    protected DateFieldValue $taq;

    /**
     * @var DateFieldValue $pdb
     *   The Probable begin of the range.
     */
    protected DateFieldValue $pdb;

    /**
     * @var DateFieldValue $pde
     *   The probable end of the range.
     */
    protected DateFieldValue $pde;

    /**
     * @return string
     */
    public function getYear(): string
    {
        return $this->year;
    }

    /**
     * @param string $year
     */
    public function setYear(string $year): void
    {
        $this->year = $year;
    }

    /**
     * @return string
     */
    public function getMonth(): string
    {
        return $this->month;
    }

    /**
     * @param string $month
     */
    public function setMonth(string $month): void
    {
        $this->month = $month;
    }

    /**
     * @return string
     */
    public function getDay(): string
    {
        return $this->day;
    }

    /**
     * @param string $day
     */
    public function setDay(string $day): void
    {
        $this->day = $day;
    }

    /**
     * @return bool
     */
    public function isRange(): bool
    {
        return $this->isRange;
    }

    /**
     * @param bool $isRange
     */
    public function setIsRange(bool $isRange): void
    {
        $this->isRange = $isRange;
    }

    /**
     * @return DateFieldValue
     */
    public function getTpq(): DateFieldValue
    {
        return $this->tpq;
    }

    /**
     * @param DateFieldValue $tpq
     */
    public function setTpq(DateFieldValue $tpq): void
    {
        $this->tpq = $tpq;
    }

    /**
     * @return DateFieldValue
     */
    public function getTaq(): DateFieldValue
    {
        return $this->taq;
    }

    /**
     * @param DateFieldValue $taq
     */
    public function setTaq(DateFieldValue $taq): void
    {
        $this->taq = $taq;
    }

    /**
     * @return DateFieldValue
     */
    public function getPdb(): DateFieldValue
    {
        return $this->pdb;
    }

    /**
     * @param DateFieldValue $pdb
     */
    public function setPdb(DateFieldValue $pdb): void
    {
        $this->pdb = $pdb;
    }

    /**
     * @return DateFieldValue
     */
    public function getPde(): DateFieldValue
    {
        return $this->pde;
    }

    /**
     * @param DateFieldValue $pde
     */
    public function setPde(DateFieldValue $pde): void
    {
        $this->pde = $pde;
    }

    /**
     * Get the date in ISO 8601 format.
     *
     * @return string
     */
    public function getISODate(): string
    {
        $date = '';
        if ($this->isRange()) {
            if (isset($this->tpq)) {
                $date .= $this->tpq->getISODate();
            }
            if (isset($this->taq)) {
                $date .= '/' . $this->taq->getISODate();
            }
        } else {
            if (!empty($this->year)) {
                $date .= $this->year;
            }
            if (!empty($this->month)) {
                if (strlen($this->month) === 1) {
                    $date .= '-0' . $this->month;
                } else {
                    $date .= '-' . $this->month;
                }
            }
            if (!empty($this->day)) {
                if (strlen($this->day) === 1) {
                    $date .= '-0' . $this->day;
                } else {
                    $date .= '-' . $this->day;
                }
            }
        }
        // Fallback to the raw value if the date is empty.
        if (empty($date)) {
            $date = $this->getValue();
        }
        return $date;
    }

}