<?php

namespace Webmasterskaya\ProductionCalendar\Tests;

use DateTime;
use Exception;
use PHPUnit\Framework\TestCase;
use Webmasterskaya\ProductionCalendar\Calendar;

final class CalendarTest extends TestCase
{
	/**
	 * Просто проверим корректность работы синглтона
	 *
	 * @throws Exception
	 * @testdox Корректное создание и вызов синглтона
	 * @covers  \Webmasterskaya\ProductionCalendar\Calendar::getInstance
	 */
	public function testCreatesAValidSingletonInstance()
	{
		$firstCall = Calendar::getInstance();
		$secondCall = Calendar::getInstance();

		$this->assertInstanceOf(Calendar::class, $firstCall);
		$this->assertSame($firstCall, $secondCall);
	}

	/**
	 * Проверяем корректность обработки входных дат в разных форматах
	 *
	 * @throws Exception
	 * @testdox Корректное создание и вызов сингл тона с указанием дат в разных форматах
	 * @covers  \Webmasterskaya\ProductionCalendar\Calendar::find
	 */
	public function testCreatesAValidSingletonInstanceWithDate()
	{
		$now = (new DateTime('now'))->format('Y-m-d');
		$matrix = [
			[
				'input' => null,
				'output' => $now
			],
			[
				'input' => '',
				'output' => $now
			],
			[
				'input' => 'now',
				'output' => $now
			],
			[
				'input' => '11.12.2013',
				'output' => '2013-12-11'
			],
			[
				'input' => '2013-11-12',
				'output' => '2013-11-12'
			],
			[
				'input' => new DateTime('11.12.2013'),
				'output' => '2013-12-11'
			],
			[
				'input' => 1582146000,
				'output' => '2020-02-19'
			]
		];

		foreach ($matrix as $case) {
			$instance = Calendar::find($case['input']);
			$this->assertInstanceOf(Calendar::class, $instance);
			$this->assertEquals($case['output'], (string)$instance);
		}

		$this->expectException(Exception::class);
		Calendar::find('foo bar');
	}

	/**
	 * Проверяем корректность определения предпраздничного дня
	 *
	 * @throws Exception
	 * @testdox Корректное определение предпраздничного дня
	 * @covers  \Webmasterskaya\ProductionCalendar\Calendar::isPreHoliday
	 */
	public function testCorrectDefinitionOfThePreHoliday()
	{
		$this->assertTrue(Calendar::isPreHoliday('22.02.2013'));
		$this->assertTrue(Calendar::isPreHoliday('07.03.2014'));
		$this->assertTrue(Calendar::isPreHoliday('08.05.2015'));
		$this->assertTrue(Calendar::isPreHoliday('20.02.2016'));
		$this->assertTrue(Calendar::isPreHoliday('22.02.2017'));
		$this->assertTrue(Calendar::isPreHoliday('07.03.2018'));
		$this->assertTrue(Calendar::isPreHoliday('22.02.2019'));
		$this->assertTrue(Calendar::isPreHoliday('11.06.2020'));
		$this->assertTrue(Calendar::isPreHoliday('20.02.2021'));
		$this->assertTrue(Calendar::isPreHoliday('22.02.2022'));
		$this->assertTrue(Calendar::isPreHoliday('22.02.2023'));
		$this->assertTrue(Calendar::isPreHoliday('22.02.2024'));
		$this->assertTrue(Calendar::isPreHoliday('07.03.2025'));
	}

	/**
	 * Проверяем корректность определения праздничного дня
	 *
	 * @throws Exception
	 * @testdox Корректное определение праздничного дня
	 * @covers  \Webmasterskaya\ProductionCalendar\Calendar::isHoliday
	 */
	public function testCorrectDefinitionOfTheHoliday()
	{
		// Новогодние каникулы - это святое
		$this->assertTrue(Calendar::isHoliday('03.01.2013'));
		$this->assertTrue(Calendar::isHoliday('03.01.2014'));
		$this->assertTrue(Calendar::isHoliday('02.01.2015'));
		$this->assertTrue(Calendar::isHoliday('04.01.2016'));
		$this->assertTrue(Calendar::isHoliday('03.01.2017'));
		$this->assertTrue(Calendar::isHoliday('03.01.2018'));
		$this->assertTrue(Calendar::isHoliday('03.01.2019'));
		$this->assertTrue(Calendar::isHoliday('03.01.2020'));
		$this->assertTrue(Calendar::isHoliday('04.01.2021'));
		$this->assertTrue(Calendar::isHoliday('03.01.2022'));
		$this->assertTrue(Calendar::isHoliday('03.01.2023'));
		$this->assertTrue(Calendar::isHoliday('03.01.2024'));
		$this->assertTrue(Calendar::isHoliday('03.01.2025'));
	}

	/**
	 * Проверяем корректность определения выходного дня
	 *
	 * @throws Exception
	 * @testdox Корректное определение выходного дня
	 * @covers  \Webmasterskaya\ProductionCalendar\Calendar::isWeekend
	 */
	public function testCorrectDefinitionOfTheWeekend()
	{
		$this->assertTrue(Calendar::isWeekend('12.01.2013'));
		$this->assertTrue(Calendar::isWeekend('11.01.2014'));
		$this->assertTrue(Calendar::isWeekend('11.01.2015'));
		$this->assertTrue(Calendar::isWeekend('09.01.2016'));
		$this->assertTrue(Calendar::isWeekend('14.01.2017'));
		$this->assertTrue(Calendar::isWeekend('13.01.2018'));
		$this->assertTrue(Calendar::isWeekend('12.01.2019'));
		$this->assertTrue(Calendar::isWeekend('11.01.2020'));
		$this->assertTrue(Calendar::isWeekend('09.01.2021'));
		$this->assertTrue(Calendar::isWeekend('08.01.2022'));
		$this->assertTrue(Calendar::isWeekend('14.01.2023'));
		$this->assertTrue(Calendar::isWeekend('13.01.2024'));
		$this->assertTrue(Calendar::isWeekend('12.01.2025'));

		// Проверка рабочих суббот
		$this->assertFalse(Calendar::isWeekend('20.02.2016'));
		$this->assertFalse(Calendar::isWeekend('09.06.2018'));
		$this->assertFalse(Calendar::isWeekend('09.06.2021'));
		$this->assertFalse(Calendar::isWeekend('05.03.2022'));
		$this->assertFalse(Calendar::isWeekend('27.04.2024'));
		$this->assertFalse(Calendar::isWeekend('01.11.2024'));
	}

	/**
	 * Проверяем корректность определения нерабочего дня
	 *
	 * @throws Exception
	 * @testdox Корректное определение нерабочего дня
	 * @covers  \Webmasterskaya\ProductionCalendar\Calendar::isNoWorking
	 */
	public function testCorrectDefinitionOfTheNoWorking()
	{
		$this->assertFalse(Calendar::isNoWorking('23.02.2020'));
		$this->assertFalse(Calendar::isNoWorking('24.02.2020'));
		$this->assertFalse(Calendar::isNoWorking('05.05.2020'));
		$this->assertTrue(Calendar::isNoWorking('06.05.2020'));
		$this->assertTrue(Calendar::isNoWorking('24.06.2020'));
		$this->assertTrue(Calendar::isNoWorking('01.07.2020'));
	}

	/**
	 * Проверяем корректность определения рабочего дня
	 *
	 * @testdox Корректное определение рабочего дня
	 * @covers  \Webmasterskaya\ProductionCalendar\Calendar::isWorking
	 */
	public function testCorrectDefinitionOfTheWorking()
	{
		$this->assertTrue(Calendar::isWorking('15.01.2013'));
		$this->assertTrue(Calendar::isWorking('15.01.2014'));
		$this->assertTrue(Calendar::isWorking('15.01.2015'));
		$this->assertTrue(Calendar::isWorking('15.01.2016'));
		$this->assertTrue(Calendar::isWorking('13.01.2017'));
		$this->assertTrue(Calendar::isWorking('12.01.2018'));
		$this->assertTrue(Calendar::isWorking('15.01.2019'));
		$this->assertTrue(Calendar::isWorking('15.01.2020'));
		$this->assertTrue(Calendar::isWorking('15.01.2021'));
		$this->assertTrue(Calendar::isWorking('14.01.2022'));
		$this->assertTrue(Calendar::isWorking('13.01.2023'));
		$this->assertTrue(Calendar::isWorking('12.01.2024'));
		$this->assertTrue(Calendar::isWorking('15.01.2025'));

		// Проверка рабочих суббот
		$this->assertTrue(Calendar::isWorking('20.02.2016'));
		$this->assertTrue(Calendar::isWorking('09.06.2018'));
		$this->assertTrue(Calendar::isWorking('09.06.2021'));
		$this->assertTrue(Calendar::isWorking('05.03.2022'));
		$this->assertTrue(Calendar::isWorking('27.04.2024'));
		$this->assertTrue(Calendar::isWorking('01.11.2024'));

		// Проверка выходных среди недели
		$this->assertFalse(Calendar::isWorking('08.03.2013'));
		$this->assertFalse(Calendar::isWorking('10.03.2014'));
		$this->assertFalse(Calendar::isWorking('09.03.2015'));
		$this->assertFalse(Calendar::isWorking('08.03.2016'));
		$this->assertFalse(Calendar::isWorking('08.03.2017'));
		$this->assertFalse(Calendar::isWorking('08.03.2018'));
		$this->assertFalse(Calendar::isWorking('08.03.2019'));
		$this->assertFalse(Calendar::isWorking('09.03.2020'));
		$this->assertFalse(Calendar::isWorking('08.03.2021'));
		$this->assertFalse(Calendar::isWorking('08.03.2022'));
		$this->assertFalse(Calendar::isWorking('08.03.2023'));
		$this->assertFalse(Calendar::isWorking('08.03.2024'));
		$this->assertFalse(Calendar::isWorking('08.05.2025'));
	}

	/**
	 * Проверяем корректность определения рабочего дня, признанного нерабочим
	 *
	 * @throws Exception
	 * @testdox Корректное определение рабочего дня, признанного нерабочим
	 * @covers  \Webmasterskaya\ProductionCalendar\Calendar::isWorking
	 * @covers  \Webmasterskaya\ProductionCalendar\Calendar::isNoWorking
	 */
	public function testCorrectSimultaneousDefinitionOfTheWorkingAndNoWorking()
	{
		$this->assertTrue(Calendar::isWorking('24.06.2020'));
		$this->assertTrue(Calendar::isNoWorking('24.06.2020'));
		$this->assertFalse(Calendar::isWeekend('24.06.2020'));
	}

	/**
	 * Проверяем корректность определения праздничного дня выпадающего на выходной
	 *
	 * @throws Exception
	 * @testdox Корректное определение праздничного дня выпадающего на выходной
	 * @covers  \Webmasterskaya\ProductionCalendar\Calendar::isHoliday
	 * @covers  \Webmasterskaya\ProductionCalendar\Calendar::isWeekend
	 */
	public function testCorrectSimultaneousDefinitionOfTheHolidayAndWeekend()
	{
		$this->assertTrue(Calendar::isHoliday('23.02.2023'));
		$this->assertTrue(Calendar::isWeekend('23.02.2023'));
	}

	/**
	 * Проверяем корректность возвращаемой даты, под курсором
	 *
	 * @throws Exception
	 * @testdox Корректное возврат даты, под курсором
	 * @covers  \Webmasterskaya\ProductionCalendar\Calendar::find
	 * @covers  \Webmasterskaya\ProductionCalendar\Calendar::date
	 */
	public function testDate()
	{
		$this->assertEquals(new DateTime('20.08.2020'), Calendar::find('2020-08-20')->date());
	}

	/**
	 * Проверяем корректность сдвига курсора на один день вперёд
	 *
	 * @throws Exception
	 * @testdox Корректный сдвиг курсора на один день вперёд
	 * @covers  \Webmasterskaya\ProductionCalendar\Calendar::find
	 * @covers  \Webmasterskaya\ProductionCalendar\Calendar::next
	 */
	public function testNext()
	{
		$this->assertEquals(new DateTime('21.08.2020'), Calendar::find('2020-08-20')->next()->date());
		$this->assertEquals(new DateTime('01.01.2021'), Calendar::find('2020-12-31')->next()->date());
	}

	/**
	 * Проверяем корректность сдвига курсора на один день назад
	 *
	 * @throws Exception
	 * @testdox Корректный сдвиг курсора на один день назад
	 * @covers  \Webmasterskaya\ProductionCalendar\Calendar::find
	 * @covers  \Webmasterskaya\ProductionCalendar\Calendar::prev
	 */
	public function testPrev()
	{
		$this->assertEquals(new DateTime('19.08.2020'), Calendar::find('2020-08-20')->prev()->date());
		$this->assertEquals(new DateTime('31.12.2019'), Calendar::find('2020-01-01')->prev()->date());
	}

	/**
	 * Проверяем корректность форматирования даты
	 *
	 * @throws Exception
	 * @testdox Корректное форматирование даты
	 * @covers  \Webmasterskaya\ProductionCalendar\Calendar::find
	 * @covers  \Webmasterskaya\ProductionCalendar\Calendar::format
	 */
	public function testFormat()
	{
		$this->assertEquals('2020-10-01', Calendar::find('01.10.2020')->format());
		$this->assertEquals('01.10.2020', Calendar::find('2020-10-01')->format('d.m.Y'));
	}

	/**
	 * Проверяем корректность форматирования даты в UNIX-timestamp
	 *
	 * @throws Exception
	 * @testdox Корректное форматирование даты в UNIX-timestamp
	 * @covers  \Webmasterskaya\ProductionCalendar\Calendar::find
	 * @covers  \Webmasterskaya\ProductionCalendar\Calendar::timestamp
	 */
	public function testTimestamp()
	{
		$this->assertEquals(1601510400, Calendar::find('01.10.2020')->timestamp());
	}

	/**
	 * Проверяем корректность преобразования в строку
	 *
	 * @throws Exception
	 * @testdox Корректное преобразование в строку
	 * @covers  \Webmasterskaya\ProductionCalendar\Calendar::find
	 * @covers  \Webmasterskaya\ProductionCalendar\Calendar::__toString
	 */
	public function testToString()
	{
		$this->assertEquals('2020-10-01', (string)Calendar::find('01.10.2020'));
		$this->assertEquals('2020-10-01', Calendar::find('01.10.2020')->__toString());
	}
}
