<?php

namespace Webmasterskaya\ProductionCalendar;

use DateInterval;
use DateTime;
use Exception;

use function dirname;
use function in_array;
use function is_int;
use function is_null;

class Calendar
{
	/**
	 * Формат даты, для печати
	 *
	 * @var string
	 */
	public string $format = 'Y-m-d';

	/**
	 * Экземпляр класса
	 *
	 * @var static
	 */
	private static self $_instance;

	/**
	 * Массив со справочником дат, загруженным из json
	 *
	 * @var array
	 */
	protected static array $holidays;

	/**
	 * Дата, на которую установлен курсор
	 *
	 * @var DateTime
	 */
	private static DateTime $date;

	/**
	 * Возвращает дату, отформатированную согласно установленному формату
	 *
	 * @return string
	 */
	public function __toString(): string
	{
		return $this->date()->format($this->format);
	}

	/**
	 * Возвращает дату, в виде объекта \DateTime
	 *
	 * @return DateTime
	 */
	public static function date(): DateTime
	{
		return static::$date;
	}

	/**
	 * Возвращает временную метку Unix
	 *
	 * @return int
	 */
	public function timestamp(): int
	{
		return static::date()->getTimestamp();
	}

	/**
	 * Проверяет, является ли дата рабочим днём
	 *
	 * @param null|DateTime|string $date Дата, которую нужно проверить
	 *
	 * @param array $weekend Массив с номера дней, которые принято считать выходными. 0 - воскресенье, 6 - суббота
	 *
	 * @return bool
	 * @throws Exception
	 */
	public static function isWorking($date = null, array $weekend = [6, 0]): bool
	{
		return static::findDateInArray($date, static::getWorksByYear($date))
			|| static::isPreHoliday($date) || (!static::isHoliday($date) && !static::isWeekend($date, $weekend));
	}

	/**
	 * Проверяет, входит ли указанная дата в массив
	 *
	 * @param null|DateTime|string $date Дата, которую нужно найти
	 * @param array $array Массив дат, среди которых производится поиск
	 *
	 * @return bool
	 * @throws Exception
	 */
	protected static function findDateInArray($date, array $array): bool
	{
		$date = static::prepareDate($date);

		return in_array($date->format('Y-m-d'), $array);
	}

	/**
	 * Проверяет, является ли дата предпраздничным днём
	 *
	 * @param null|DateTime|string $date Дата, которую нужно проверить
	 *
	 * @return bool
	 * @throws Exception
	 */
	public static function isPreHoliday($date = null): bool
	{
		return static::findDateInArray($date, static::getPreHolidaysByYear($date));
	}

	/**
	 * Проверяет, является ли дата праздничным днём
	 *
	 * @param null|DateTime|string $date Дата, которую нужно проверить
	 *
	 * @return bool
	 * @throws Exception
	 */
	public static function isHoliday($date = null): bool
	{
		return static::findDateInArray($date, static::getHolidaysByYear($date));
	}

	/**
	 * Проверяет, является ли дата выходным днём
	 *
	 * @param null|DateTime|string $date Дата, которую нужно проверить
	 *
	 * @param array $weekend Массив с номера дней, которые принято считать выходными. 0 - воскресенье, 6 - суббота
	 *
	 * @return bool
	 * @throws Exception
	 */
	public static function isWeekend($date = null, array $weekend = [6, 0]): bool
	{
		$date = static::prepareDate($date);

		return static::isHoliday($date)
			|| (in_array($date->format('w'), $weekend)
				&& !static::isPreHoliday($date)
				&& !static::findDateInArray($date, static::getWorksByYear($date)));
	}

	/**
	 * Проверяет, является ли дата нерабочим днём
	 *
	 * @param null|DateTime|string $date Дата, которую нужно проверить
	 *
	 * @return bool
	 * @throws Exception
	 */
	public static function isNoWorking($date = null): bool
	{
		return static::findDateInArray($date, static::getNoWorkingByYear($date));
	}

	/**
	 * Возвращает массив праздничны дней в году
	 *
	 * @param integer|string|DateTime $year Год, для которого нужно получить список праздничных дней
	 *
	 * @return array
	 * @throws Exception
	 */
	protected static function getHolidaysByYear($year): array
	{
		if (!is_numeric($year)) {
			$year = static::prepareDate($year)->format('Y');
		}
		$holidays = static::getHolidays();

		return $holidays[$year]['holidays'] ?? [];
	}

	/**
	 * Возвращает массив рабочих дней в году
	 *
	 * @param integer|string|DateTime $year Год, для которого нужно получить список рабочих дней
	 *
	 * @return array
	 * @throws Exception
	 */
	protected static function getWorksByYear($year): array
	{
		if (!is_numeric($year)) {
			$year = static::prepareDate($year)->format('Y');
		}
		$holidays = static::getHolidays();

		return $holidays[$year]['works'] ?? [];
	}

	/**
	 * Возвращает массив предпраздничных дней в году
	 *
	 * @param integer|string|DateTime $year Год, для которого нужно получить список предпраздничных дней
	 *
	 * @return array
	 * @throws Exception
	 */
	protected static function getPreHolidaysByYear($year): array
	{
		if (!is_numeric($year)) {
			$year = static::prepareDate($year)->format('Y');
		}
		$holidays = static::getHolidays();

		return $holidays[$year]['preholidays'] ?? [];
	}

	/**
	 * Возвращает массив нерабочих дней в году
	 *
	 * @param integer|string|DateTime $year Год, для которого нужно получить список нерабочих дней
	 *
	 * @return array
	 * @throws Exception
	 */
	protected static function getNoWorkingByYear($year): array
	{
		if (!is_numeric($year)) {
			$year = static::prepareDate($year)->format('Y');
		}
		$holidays = static::getHolidays();

		return $holidays[$year]['nowork'] ?? [];
	}

	/**
	 * Находит и возвращает все выходные дни в указанном промежутке дат в заданном формате
	 *
	 * @param integer|string|DateTime $date_from Начальная дата поиска
	 * @param integer|string|DateTime $date_to Конечная дата поиска
	 * @param string|null $format Формат возвращаемых дат. см. https://www.php.net/manual/ru/datetime.format.php
	 *
	 * @return array
	 * @throws Exception
	 */
	public static function getHolidaysListByInterval($date_from, $date_to, string $format = null): array
	{
		static::prepareDateInterval($date_from, $date_to);

		$holidaysList = [];

		$lastHoliday = Calendar::find($date_from)->holiday();
		$holidaysList[] = $lastHoliday->format($format);
		while ($lastHoliday->date() <= $date_to) {
			$lastHoliday = $lastHoliday->next()->holiday();
			$holidaysList[] = $lastHoliday->format($format);
		}

		return $holidaysList;
	}

	/**
	 * Находит и возвращает все рабочие дни в указанном промежутке дат в заданном формате
	 *
	 * @param integer|string|DateTime $date_from Начальная дата поиска
	 * @param integer|string|DateTime $date_to Конечная дата поиска
	 * @param string|null $format Формат возвращаемых дат. см. https://www.php.net/manual/ru/datetime.format.php
	 *
	 * @return array
	 * @throws Exception
	 */
	public static function getWorkingListByInterval($date_from, $date_to, string $format = null): array
	{
		static::prepareDateInterval($date_from, $date_to);

		$workingList = [];

		$lastWorking = Calendar::find($date_from)->working();
		$workingList[] = $lastWorking->format($format);
		while ($lastWorking->date() <= $date_to) {
			$lastWorking = $lastWorking->next()->working();
			$workingList[] = $lastWorking->format($format);
		}

		return $workingList;
	}

	/**
	 * Находит и возвращает все нерабочие дни в указанном промежутке дат в заданном формате
	 *
	 * @param integer|string|DateTime $date_from Начальная дата поиска
	 * @param integer|string|DateTime $date_to Конечная дата поиска
	 * @param string|null $format Формат возвращаемых дат. см. https://www.php.net/manual/ru/datetime.format.php
	 *
	 * @return array
	 * @throws Exception
	 */
	public static function getNoWorkingListByInterval($date_from, $date_to, string $format = null): array
	{
		static::prepareDateInterval($date_from, $date_to);

		$noWorkingList = [];

		$lastNoWorking = Calendar::find($date_from)->noWorking();
		$noWorkingList[] = $lastNoWorking->format($format);
		while ($lastNoWorking->date() <= $date_to) {
			$lastNoWorking = $lastNoWorking->next()->noWorking();
			$noWorkingList[] = $lastNoWorking->format($format);
		}

		return $noWorkingList;
	}

	/**
	 * Находит и возвращает все предпраздничные дни в указанном промежутке дат в указанном формате
	 *
	 * @param integer|string|DateTime $date_from Начальная дата поиска
	 * @param integer|string|DateTime $date_to Конечная дата поиска
	 * @param string|null $format Формат возвращаемых дат. см. https://www.php.net/manual/ru/datetime.format.php
	 *
	 * @return string[]
	 * @throws Exception
	 */
	public static function getPreHolidayListByInterval($date_from, $date_to, string $format = null): array
	{
		static::prepareDateInterval($date_from, $date_to);

		$preHolidayList = [];

		$lastPreHoliday = Calendar::find($date_from)->preHoliday();
		$preHolidayList[] = $lastPreHoliday->format($format);
		while ($lastPreHoliday->date() <= $date_to) {
			$lastPreHoliday = $lastPreHoliday->next()->preHoliday();
			$preHolidayList[] = $lastPreHoliday->format($format);
		}

		return $preHolidayList;
	}

	/**
	 * Подготавливает корректные даты начала и конца интервала
	 *
	 * @param integer|string|DateTime $date_from Начальная дата интервала
	 * @param integer|string|DateTime $date_to Конечная дата интервала
	 *
	 * @throws Exception
	 */
	protected static function prepareDateInterval(&$date_from, &$date_to)
	{
		$date_from = static::prepareDate($date_from);
		$date_to = static::prepareDate($date_to);

		if ($date_from > $date_to) {
			$date_tmp = $date_to;
			$date_to = $date_from;
			$date_from = $date_tmp;
			unset($date_tmp);
		}
	}

	/**
	 * Возвращает дату, отформатированную согласно переданному формату
	 *
	 * @param string|null $format Шаблон результирующей строки с датой. см. https://www.php.net/manual/ru/datetime.format.php
	 *
	 * @return string
	 */
	public function format(string $format = null): string
	{
		return $this->date()->format($format ?: $this->format);
	}

	/**
	 * Возвращает текущую дату
	 *
	 * @return Calendar
	 */
	public function day(): Calendar
	{
		return $this;
	}

	/**
	 * Возвращает дату ближайшего рабочего дня
	 *
	 * @return Calendar
	 * @throws Exception
	 */
	public function working(): Calendar
	{
		while (!static::isWorking(static::$date)) {
			$this->next();
		}

		return $this;
	}

	/**
	 * Возвращает дату ближайшего праздничного дня
	 *
	 * @return Calendar
	 * @throws Exception
	 */
	public function holiday(): Calendar
	{
		while (!static::isHoliday(static::$date) && static::haveData()) {
			$this->next();
		}

		return $this;
	}

	/**
	 * Проверяет, содержится ли заданная дата в справочнике дат библиотеки
	 *
	 * @param DateTime|string $date Дата, которую нужно проверить
	 *
	 * @return bool
	 * @throws Exception
	 */
	protected static function haveData($date = null): bool
	{
		$date = $date ? static::prepareDate($date) : static::date();

		return isset(static::getHolidays()[$date->format('Y')]);
	}

	/**
	 * Возвращает дату ближайшего предпраздничного дня
	 *
	 * @return Calendar
	 * @throws Exception
	 */
	public function preHoliday(): Calendar
	{
		while (!static::isPreHoliday(static::$date) && static::haveData($this->date())) {
			$this->next();
		}

		return $this;
	}

	/**
	 * Возвращает дату ближайшего нерабочего дня
	 *
	 * @return Calendar
	 * @throws Exception
	 */
	public function noWorking(): Calendar
	{
		while (!static::isNoWorking(static::$date) && static::haveData($this->date())) {
			$this->next();
		}

		return $this;
	}

	/**
	 * Сдвигает текущую дату на один день вперёд
	 *
	 * @return Calendar
	 */
	public function next(): Calendar
	{
		static::$date->add(new DateInterval('P1D'));

		return $this;
	}

	/**
	 * Сдвигает текущую дату на один день назад
	 *
	 * @return Calendar
	 */
	public function prev(): Calendar
	{
		static::$date->sub(new DateInterval('P1D'));

		return $this;
	}

	/**
	 * Возвращает экземпляр класса
	 *
	 * @return Calendar
	 * @throws Exception
	 */
	public static function getInstance(): Calendar
	{
		return static::$_instance ?? static::find();
	}

	/**
	 * Инициализирует экземпляр класса с указанной датой
	 *
	 * @param null|string|DateTime $date Дата, с которой нужно инициализировать класс. null - сегодняшняя дата
	 *
	 * @return Calendar
	 * @throws Exception
	 */
	public static function find($date = null): Calendar
	{
		static::$date = static::prepareDate($date);

		if (!isset(static::$_instance)) {
			$json = file_get_contents(dirname(__FILE__) . '/data/holidays.json');
			static::$_instance = new self();
			static::$holidays = json_decode($json, true);
		}

		return static::$_instance;
	}

	/**
	 * Преобразует дату в объект \DateTime
	 *
	 * @param null|int|string|DateTime $date Объект или строка даты/времени. Объяснение корректных форматов см по ссылке https://www.php.net/manual/ru/datetime.formats.php
	 *
	 * @throws Exception
	 * @return DateTime
	 */
	protected static function prepareDate($date = null): DateTime
	{
		if (is_null($date) && isset(static::$date)) {
			$date = static::$date;
		} elseif (!$date instanceof DateTime) {
			if (is_int($date)) {
				$date = '@' . (string)$date;
			}
			$date = new DateTime((string)$date);
		}

		return $date;
	}

	/**
	 * Возвращает справочник дат
	 *
	 * @return array
	 * @throws Exception
	 */
	protected static function getHolidays(): array
	{
		if (!isset(static::$_instance)) {
			static::find();
		}

		return static::$holidays;
	}

	/**
	 * Это singleton класс
	 */
	private function __construct()
	{
	}

	/**
	 * Это singleton класс
	 */
	protected function __clone()
	{
	}
}
