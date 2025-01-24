<?php

namespace Webmasterskaya\ProductionCalendar;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Exception;

use function dirname;
use function is_string;

class Updater
{
	protected const OUTPUT_PATH = 'data/holidays.json';
	protected const BACKUP_PATH = 'data/backup.holidays.json';

	/**
	 * @throws Exception
	 */
	public static function execute($arg = null)
	{
		/** @noinspection PhpUndefinedNamespaceInspection */
		/** @noinspection PhpUndefinedClassInspection */
		if ($arg instanceof \Composer\Script\Event) {
			$args = $arg->getArguments();
			$arg = $args[0] ?? null;
		}

		if (is_string($arg)) {
			$arg = trim($arg);

			if (strtolower($arg) === 'all') {
				static::updateAll();
				return;
			}
		}

		static::update($arg);
	}

	/**
	 * @throws Exception
	 */
	public function __invoke($arg = null)
	{
		static::execute($arg);
	}

	/**
	 * Обновляет справочник дат за весь доступный период (2013 - текущий год и следующий, если опубликован)
	 *
	 * @return void
	 * @throws Exception
	 */
	public static function updateAll()
	{
		$output = dirname(__FILE__) . '/' . ltrim(static::OUTPUT_PATH, '/');
		$backup = dirname(__FILE__) . '/' . ltrim(static::BACKUP_PATH, '/');

		if (file_exists($output)) {
			copy($output, $backup);
			unlink($output);
		}

		$year = 2013;
		$cur_year = date('Y');

		try {
			while ($year <= $cur_year) {
				static::update($year++);
			}

			try {
				static::update($year);
			} catch (Exception $e) {
				//do nothing
			}
		} catch (Exception $e) {
			if (file_exists($backup)) {
				copy($backup, $output);
			}

			throw $e;
		} finally {
			if (file_exists($backup)) {
				unlink($backup);
			}
		}
	}

	/**
	 * Обновляет справочник дат за указанный год
	 *
	 * @param int|string $year Год, за который нужно получить справочник. null - екущий год
	 *
	 * @throws Exception
	 */
	public static function update($year = null)
	{
		if (empty($year)) {
			$year = date('Y');
		}

		if ($year == 2020) {
			$year = '2020b';
		}

		$uri = "https://www.consultant.ru/law/ref/calendar/proizvodstvennye/$year/";

		$year = (int)$year;

		$output = dirname(__FILE__) . '/' . ltrim(static::OUTPUT_PATH, '/');

		$ch = curl_init($uri);
		curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: text/html; charset=utf-8']);
		curl_setopt($ch, CURLOPT_FAILONERROR, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		if (($result = curl_exec($ch)) === false) {
			throw new Exception(curl_error($ch), curl_errno($ch));
		}

		$document = new DOMDocument();

		// Отключает ошибки парсинга HTML5 элементов
		libxml_use_internal_errors(true);
		$document->loadHTML($result);
		libxml_use_internal_errors(false);

		$xpath = new DOMXPath($document);

		$tables_nodes = $xpath->query("//*/table[contains(concat(' ', normalize-space(@class), ' '), cal)]");

		if (file_exists($output)) {
			$dates = json_decode(file_get_contents($output), true, 128, JSON_OBJECT_AS_ARRAY);
			if (json_last_error() !== JSON_ERROR_NONE) {
				throw new Exception(json_last_error_msg(), 1);
			}
		} else {
			$dates = [];
		}

		$dates[$year] = [
			'holidays' => [],
			'works' => [],
			'preholidays' => [],
			'nowork' => [],
		];

		$m = 0;

		/** @var DOMElement $table_node */
		foreach ($tables_nodes as $table_node) {
			// Дополнительная проверка на косяки, при выборке таблиц по классу
			if (strpos($table_node->getAttribute('class'), 'cal') === false) {
				continue;
			}

			$m++;
			$tds_nodes = $table_node->getElementsByTagName('td');
			$month = str_pad($m, 2, '0', STR_PAD_LEFT);

			/** @var DOMElement $td_node */
			foreach ($tds_nodes as $td_node) {
				$day = str_pad(
					preg_replace('/\D/', '', $td_node->textContent),
					2,
					'0',
					STR_PAD_LEFT
				);

				$td_classname = $td_node->getAttribute('class');

				if (strpos($td_classname, 'inactively') !== false) {
					continue;
				}

				$date = $year . '-' . $month . '-' . $day;
				$idx = '';

				if (strpos($td_classname, 'holiday') !== false) {
					$idx = 'holidays';
				}

				if (strpos($td_classname, 'nowork') !== false) {
					$idx = 'nowork';
				}

				if (strpos($td_classname, 'preholiday') !== false) {
					$idx = 'preholidays';
				}

				if (strpos($td_classname, 'work') !== false && $idx !== 'nowork') {
					$idx = 'works';
				}

				if (empty($idx)) {
					continue;
				}

				$dates[$year][$idx][] = $date;
			}
		}

		if (
			!empty($dates[$year]['holidays'])
			&& !empty($dates[$year]['preholidays'])
		) {
			$dates_json = json_encode($dates);

			if (json_last_error() !== JSON_ERROR_NONE) {
				throw new Exception(json_last_error_msg(), 1);
			}

			file_put_contents($output, $dates_json);
		}
	}
}
