<?php

namespace Webmasterskaya\ProductionCalendar;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Exception;
use RuntimeException;
use UnexpectedValueException;

use function dirname;
use function in_array;
use function is_array;
use function is_string;
use function sprintf;
use function strlen;

class Updater
{
	protected const OUTPUT_PATH = 'data/holidays.json';

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

		if (is_string($arg) && strtolower(trim($arg)) === 'all') {
			static::updateAll();

			return;
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
	 * Обновляет справочник дат за весь доступный период.
	 *
	 * @throws Exception
	 */
	public static function updateAll()
	{
		$dates = [];
		$currentYear = (int)date('Y');

		for ($year = 2013; $year <= $currentYear; $year++) {
			$dates[$year] = static::downloadYear($year);
		}

		try {
			$dates[$currentYear + 1] = static::downloadYear($currentYear + 1);
		} catch (Exception $exception) {
			// Календарь следующего года может быть ещё не опубликован.
		}

		static::writeDates($dates);
	}

	/**
	 * Обновляет справочник дат за указанный год.
	 *
	 * @param int|string|null $year
	 *
	 * @throws Exception
	 */
	public static function update($year = null)
	{
		$year = static::normalizeYear($year);
		$dates = static::loadDates();
		$dates[$year] = static::downloadYear($year);
		ksort($dates, SORT_NUMERIC);

		static::writeDates($dates);
	}

	/**
	 * @param int|string|null $year
	 */
	protected static function normalizeYear($year): int
	{
		if ($year === null || $year === '') {
			return (int)date('Y');
		}

		$year = trim((string)$year);
		if (!preg_match('/^\d{4}$/', $year) || (int)$year < 2013) {
			throw new UnexpectedValueException("Invalid production calendar year: $year");
		}

		return (int)$year;
	}

	/**
	 * @throws Exception
	 */
	protected static function downloadYear(int $year): array
	{
		switch (true) {
			case $year === 2020:
				$sourceYear = '2020b';
				break;
			case $year === 2024:
				$sourceYear = '2024b';
				break;
			default:
				$sourceYear = (string)$year;
		}

		$uri = "https://www.consultant.ru/law/ref/calendar/proizvodstvennye/$sourceYear/";

		return static::parseYear(static::fetch($uri), $year);
	}

	/**
	 * @throws RuntimeException
	 */
	protected static function fetch(string $uri): string
	{
		$curl = curl_init($uri);
		if ($curl === false) {
			throw new RuntimeException("Unable to initialize cURL for $uri");
		}

		curl_setopt_array($curl, [
			CURLOPT_HTTPHEADER => ['Content-Type: text/html; charset=utf-8'],
			CURLOPT_FAILONERROR => true,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_RETURNTRANSFER => true,
		]);

		$result = curl_exec($curl);
		if ($result === false) {
			$message = curl_error($curl);
			$code = curl_errno($curl);
			curl_close($curl);

			throw new RuntimeException("Unable to download $uri: $message", $code);
		}

		curl_close($curl);

		return $result;
	}

	/**
	 * @throws UnexpectedValueException
	 */
	protected static function parseYear(string $html, int $year): array
	{
		$document = new DOMDocument();
		$previousErrorsSetting = libxml_use_internal_errors(true);

		try {
			$loaded = $document->loadHTML($html, LIBXML_NONET);
		} finally {
			libxml_clear_errors();
			libxml_use_internal_errors($previousErrorsSetting);
		}

		if (!$loaded) {
			throw new UnexpectedValueException("Unable to parse production calendar HTML for $year");
		}

		$xpath = new DOMXPath($document);
		$tables = $xpath->query("//table[contains(concat(' ', normalize-space(@class), ' '), ' cal ')]");
		if ($tables === false || $tables->length !== 12) {
			$count = $tables === false ? 0 : $tables->length;

			throw new UnexpectedValueException("Expected 12 calendar tables for $year, found $count");
		}

		$dates = [
			'holidays' => [],
			'works' => [],
			'preholidays' => [],
			'nowork' => [],
		];

		/** @var DOMElement $table */
		foreach ($tables as $index => $table) {
			$month = $index + 1;

			/** @var DOMElement $cell */
			foreach ($table->getElementsByTagName('td') as $cell) {
				if (static::hasClass($cell, 'inactively')) {
					continue;
				}

				$category = static::getCategory($cell);
				if ($category === null) {
					continue;
				}

				$day = (int)preg_replace('/\D+/', '', $cell->textContent);
				if (!checkdate($month, $day, $year)) {
					throw new UnexpectedValueException("Invalid date in production calendar HTML for $year");
				}

				$dates[$category][] = sprintf('%04d-%02d-%02d', $year, $month, $day);
			}
		}

		if (empty($dates['holidays']) || empty($dates['preholidays'])) {
			throw new UnexpectedValueException("Incomplete production calendar data for $year");
		}

		foreach ($dates as &$categoryDates) {
			$categoryDates = array_values(array_unique($categoryDates));
		}
		unset($categoryDates);

		return $dates;
	}

	protected static function getCategory(DOMElement $cell): ?string
	{
		if (static::hasClass($cell, 'nowork')) {
			return 'nowork';
		}

		if (static::hasClass($cell, 'preholiday')) {
			return 'preholidays';
		}

		if (static::hasClass($cell, 'holiday')) {
			return 'holidays';
		}

		if (static::hasClass($cell, 'work')) {
			return 'works';
		}

		return null;
	}

	protected static function hasClass(DOMElement $element, string $class): bool
	{
		$classes = preg_split('/\s+/', trim($element->getAttribute('class')), -1, PREG_SPLIT_NO_EMPTY);

		return in_array($class, $classes, true);
	}

	/**
	 * @throws Exception
	 */
	protected static function loadDates(): array
	{
		$output = static::getOutputPath();
		if (!file_exists($output)) {
			return [];
		}

		$json = file_get_contents($output);
		if ($json === false) {
			throw new RuntimeException("Unable to read production calendar data from $output");
		}

		$dates = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
		if (!is_array($dates)) {
			throw new UnexpectedValueException("Invalid production calendar data in $output");
		}

		return $dates;
	}

	/**
	 * @throws Exception
	 */
	protected static function writeDates(array $dates): void
	{
		$output = static::getOutputPath();
		$json = json_encode($dates, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
		$temporary = tempnam(dirname($output), 'holidays.');

		if ($temporary === false) {
			throw new RuntimeException("Unable to create a temporary file next to $output");
		}

		try {
			$written = file_put_contents($temporary, $json, LOCK_EX);
			if ($written !== strlen($json)) {
				throw new RuntimeException("Unable to write complete production calendar data to $temporary");
			}

			if (!rename($temporary, $output)) {
				throw new RuntimeException("Unable to replace production calendar data in $output");
			}
		} finally {
			if (file_exists($temporary)) {
				unlink($temporary);
			}
		}
	}

	protected static function getOutputPath(): string
	{
		return dirname(__FILE__) . '/' . ltrim(static::OUTPUT_PATH, '/');
	}
}
