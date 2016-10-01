<?php
namespace Skybluesofa\OnThisDay\Data;

use Carbon\Carbon;

class Parser {

  protected $locale = 'en_US';

  private $carbonDate = null;
  private $year = null;
  private $month = null;
  private $day = null;

  private $localePath = null;

  private $classPaths = [
    'custom' => null,
    'standard' => '\\Skybluesofa\\OnThisDay\\Data\\Month',
  ];
  private $useStandardEvents = true;

  public function __construct($locale=null) {
    $this->setLocale($locale);
    return $this;
  }

  public function setLocale($locale = 'en_US') {
    $this->locale = $locale;
    $this->localePath = null;
    return $this;
  }

  public function setDate(Carbon $date) {
    $this->carbonDate = $date;
    $this->year = $this->carbonDate->year;
    $this->month = $this->carbonDate->month;
    $this->day = $this->carbonDate->day;
    return $this;
  }

  public function setCustomBaseClass($customBaseClass) {
    $this->classPaths['custom'] = $customBaseClass ? $customBaseClass : null;
    return $this;
  }

  public function setUseStandardEvents($useStandardEvents) {
    $this->useStandardEvents = $useStandardEvents ? true : false;
    return $this;
  }

  public function getEvents() {
    return array_merge(
      $this->getRecurringDateBasedEvents(),
      $this->getSpecificDateBasedEvents(),
      $this->getRecurringConfigurationBasedEvents(),
      $this->getRecurringAdvancedConfigurationBasedEvents()
    );
  }

  private function getMonthClass($classType='standard') {
    if (($classType=='standard' && !$this->useStandardEvents) || !array_key_exists($classType, $this->classPaths) || !$this->classPaths[$classType]) {
      return false;
    }

    $monthName = date("F", mktime(0, 0, 0, $this->carbonDate->month, 1));
    $classPath = $this->classPaths[$classType].$this->getLocalePath().$monthName;

    if (!class_exists($classPath)) {
      return false;
    }
    return $classPath;
  }

  private function getLocalePath() {
    if (is_null($this->localePath)) {
      $locale = explode('_', $this->locale);
      $this->localePath = '\\'.implode('\\', [ucfirst(strtolower($locale[0])), ucfirst(strtolower($locale[1]))] ).'\\';
    }
    return $this->localePath;
  }

  private function getRecurringDateBasedEvents() {
    $events = [];
    foreach (array_keys($this->classPaths) as $classType) {
      if ($monthClass = $this->getMonthClass($classType)) {
        if (property_exists($monthClass, 'recurringEvents') && isset($monthClass::$recurringEvents[$this->day])) {
          $events = array_merge($events, $monthClass::$recurringEvents[$this->day]);
        }
      }
    }
    return $events;
  }

  private function getSpecificDateBasedEvents() {
    $events = [];
    foreach (array_keys($this->classPaths) as $classType) {
      if ($monthClass = $this->getMonthClass($classType)) {
        if (property_exists($monthClass, 'recurringEvents') && isset($monthClass::$specificDateEvents[$this->year][$this->day])) {
          $events = array_merge($events, $monthClass::$specificDateEvents[$this->year][$this->day]);
        }
      }
    }
    return $events;
  }

  private function getRecurringConfigurationBasedEvents() {
    $events = [];
    foreach (array_keys($this->classPaths) as $classType) {
      if ($monthClass = $this->getMonthClass($classType)) {
        if (property_exists($monthClass, 'configurationEvents')) {
          $monthStartDate = Carbon::now();
          $monthStartDate->setDateTime($this->year, $this->month, 1, 0, 0, 0);
          foreach ($monthClass::$configurationEvents as $configuration => $eventList) {
            $configuration = str_replace(['%y','%Y'], $monthStartDate->year, $configuration);
            if ($this->carbonDate==Carbon::createFromTimestamp(strtotime($configuration))) {
              $events = array_merge($events, $eventList);
            }
          }
        }
      }
    }
    return $events;
  }

  private function getRecurringAdvancedConfigurationBasedEvents() {
    $events = [];
    foreach (array_keys($this->classPaths) as $classType) {
      if ($monthClass = $this->getMonthClass($classType)) {
        if (method_exists($monthClass, 'getRecurringAdvancedConfigurationBasedEvents')) {
          $monthStartDate = Carbon::now();
          $monthStartDate->setDateTime($this->carbonDate->year, $this->carbonDate->month, 1, 0, 0, 0);
          $events = array_merge($events, $monthClass::getRecurringAdvancedConfigurationBasedEvents($monthStartDate));
        }
      }
    }
    return $events;
  }
}