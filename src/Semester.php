<?php


namespace Kos;

abstract class SemesterPart
{
    const WINTER = 1;
    const SUMMER = 2;
}

abstract class StudyPlan
{
    const MAGISTER = "M";
    const BACHELOR = "B";
}

class Semester
{
    private $year;
    private $part;
    private $plan;

    public function __construct(string $plan = null, int $year = null, int $part = null)
    {
        $this->year = $year;
        $this->part = $part;
        $this->plan = $plan;
    }

    /**
     * @return int
     */
    public function getYear(): int
    {
        return $this->year;
    }

    /**
     * @param int $year
     * @return Semester
     */
    public function setYear(int $year): Semester
    {
        $this->year = $year;
        return $this;
    }

    /**
     * @return string
     */
    public function getPart(): string
    {
        return $this->part;
    }

    /**
     * @param string $part
     * @return Semester
     */
    public function setPart(string $part): Semester
    {
        $this->part = $part;
        return $this;
    }

    /**
     * @return string
     */
    public function getPlan(): string
    {
        return $this->plan;
    }

    /**
     * @param string $plan
     * @return Semester
     */
    public function setPlan(string $plan): Semester
    {
        $this->plan = $plan;
        return $this;
    }

    public function __toString()
    {
        return $this->plan . $this->year . $this->part;
    }

}
