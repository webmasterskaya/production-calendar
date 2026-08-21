<?php

namespace Webmasterskaya\ProductionCalendar\Tests;

use PHPUnit\Framework\TestCase;
use UnexpectedValueException;
use Webmasterskaya\ProductionCalendar\Updater;

final class UpdaterTest extends TestCase
{
	private string $directory;
	private string $output;

	protected function setUp(): void
	{
		$this->directory = sys_get_temp_dir() . '/production-calendar-' . uniqid('', true);
		mkdir($this->directory, 0777, true);
		$this->output = $this->directory . '/calendar.json';
		TestableUpdater::setOutput($this->output);
	}

	protected function tearDown(): void
	{
		if (file_exists($this->output)) {
			unlink($this->output);
		}
		foreach (glob($this->directory . '/holidays.*') as $temporary) {
			unlink($temporary);
		}
		rmdir($this->directory);
	}

	/**
	 * Проверяем извлечение и распределение дат по категориям из корректной HTML-разметки календаря
	 *
	 * @testdox Парсер корректно извлекает и классифицирует даты производственного календаря
	 * @covers  \Webmasterskaya\ProductionCalendar\Updater::parseYear
	 * @covers  \Webmasterskaya\ProductionCalendar\Updater::getCategory
	 * @covers  \Webmasterskaya\ProductionCalendar\Updater::hasClass
	 */
	public function testParserExtractsAndClassifiesCalendarDates()
	{
		$html = '<html><body><table class="vocal"><tr><td class="holiday">31</td></tr></table>';

		for ($month = 1; $month <= 12; $month++) {
			$cells = '<td>15</td>';
			if ($month === 1) {
				$cells .= '<td class="holiday">1</td><td class="holiday inactively">31</td>';
			}
			if ($month === 2) {
				$cells .= '<td class="preholiday work">22</td>';
			}
			if ($month === 3) {
				$cells .= '<td class="work">2</td>';
			}
			if ($month === 4) {
				$cells .= '<td class="nowork work">3</td>';
			}

			$html .= '<table class="calendar cal"><tr>' . $cells . '</tr></table>';
		}

		$html .= '</body></html>';
		$dates = TestableUpdater::parse($html, 2025);

		$this->assertSame(['2025-01-01'], $dates['holidays']);
		$this->assertSame(['2025-03-02'], $dates['works']);
		$this->assertSame(['2025-02-22'], $dates['preholidays']);
		$this->assertSame(['2025-04-03'], $dates['nowork']);
	}

	/**
	 * Проверяем отказ от обработки страницы с неожиданным количеством таблиц календаря
	 *
	 * @testdox Парсер отклоняет страницу с изменившейся структурой календаря
	 * @covers  \Webmasterskaya\ProductionCalendar\Updater::parseYear
	 */
	public function testParserRejectsAnUnexpectedPageLayout()
	{
		$this->expectException(UnexpectedValueException::class);
		$this->expectExceptionMessage('Expected 12 calendar tables');

		TestableUpdater::parse('<html><body><table class="cal"></table></body></html>', 2025);
	}

	/**
	 * Проверяем валидацию некорректного значения года перед обновлением календаря
	 *
	 * @testdox Обновлятор отклоняет некорректное значение года
	 * @covers  \Webmasterskaya\ProductionCalendar\Updater::normalizeYear
	 */
	public function testUpdaterRejectsInvalidYear()
	{
		$this->expectException(UnexpectedValueException::class);

		TestableUpdater::year('not-a-year');
	}

	/**
	 * Проверяем атомарную запись и последующее чтение справочника без потери данных и временных файлов
	 *
	 * @testdox Данные календаря записываются и загружаются без потерь
	 * @covers  \Webmasterskaya\ProductionCalendar\Updater::writeDates
	 * @covers  \Webmasterskaya\ProductionCalendar\Updater::loadDates
	 * @covers  \Webmasterskaya\ProductionCalendar\Updater::getOutputPath
	 */
	public function testCalendarDataIsWrittenAndLoadedWithoutLoss(): void
	{
		$dates = [
			2025 => [
				'holidays' => ['2025-01-01'],
				'works' => [],
				'preholidays' => ['2025-02-22'],
				'nowork' => [],
			],
		];

		TestableUpdater::write($dates);

		$this->assertFileExists($this->output);
		$this->assertSame($dates, TestableUpdater::load());
		$this->assertSame([], glob($this->directory . '/holidays.*'));
	}
}

final class TestableUpdater extends Updater
{
	private static string $output;

	public static function setOutput(string $output): void
	{
		self::$output = $output;
	}

	public static function parse(string $html, int $year): array
	{
		return parent::parseYear($html, $year);
	}

	public static function year($year): int
	{
		return parent::normalizeYear($year);
	}

	public static function write(array $dates): void
	{
		parent::writeDates($dates);
	}

	public static function load(): array
	{
		return parent::loadDates();
	}

	protected static function getOutputPath(): string
	{
		return self::$output;
	}
}
