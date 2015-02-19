<?php

/**
 * @package framework
 * @subpackage tests
 */
class NumericFieldTest extends SapphireTest {

	protected $usesDatabase = false;

	/**
	 * In some cases and locales, validation expects non-breaking spaces.
	 *
	 * Duplicates non-public NumericField::clean method
	 *
	 * @param string $input
	 * @return string The input value, with all spaces replaced with non-breaking spaces
	 */
	protected function clean($input) {
		$nbsp = html_entity_decode('&nbsp;', null, 'UTF-8');
		return str_replace(' ', $nbsp, trim($input));
	}

	protected function checkInputValidation($locale, $tests) {
		i18n::set_locale($locale);
		$field = new NumericField('Number');
		$validator = new RequiredFields('Number');

		foreach($tests as $input => $output) {
			// Both decimal and thousands B
			$field->setValue($input);

			if($output === false) {
				$this->assertFalse(
					$field->validate($validator),
					"Expect validation to fail for input $input in locale $locale"
				);
				$this->assertEquals(
					0,
					$field->dataValue(),
					"Expect invalid value to be rewritten to 0 in locale $locale"
				);

				// Even invalid values shouldn't be rewritten
				$this->assertEquals(
					$this->clean($input),
					$field->Value(),
					"Expected input $input to be saved in the field in locale $locale"
				);
			} else {
				$this->assertTrue(
					$field->validate($validator),
					"Expect validation to succeed for $input in locale $locale"
				);
				$this->assertEquals(
					$output,
					$field->dataValue(),
					"Expect value $input to be mapped to $output in locale $locale"
				);
			}
		}
	}

	/**
	 * Test that data loaded in via Form::loadDataFrom(DataObject) will populate the field correctly,
	 * and can format the database value appropriately for the frontend
	 *
	 * @param string $locale
	 * @param array $tests
	 */
	public function checkDataFormatting($locale, $tests) {
		i18n::set_locale($locale);
		$field = new NumericField('Number');
		$form = new Form(new Controller(), 'Form', new FieldList($field), new FieldList());
		$dataObject = new NumericFieldTest_Object();

		foreach($tests as $input => $output) {
			// Given a dataobject as a context, the field should assume the field value is not localised
			$dataObject->Number = (string)$input;
			$form->loadDataFrom($dataObject, Form::MERGE_CLEAR_MISSING);

			// Test value
			$this->assertEquals(
				$input,
				$field->dataValue(),
				"Expected $input loaded via dataobject to be left intact in locale $locale"
			);
			
			// Test expected formatted value (Substitute nbsp for spaces)
			$this->assertEquals(
				$this->clean($output),
				$field->Value(),
				"Expected $input to be formatted as $output in locale $locale"
			);
		}
	}

	/**
	 * German locale values (same as dutch)
	 */
	public function testGermanLocales() {
		$this->checkDataFormatting('de_DE', $formatting = array(
			'13000' => "13.000",
			'15' => '15',
			'12.0' => '12,0',
			'12.1' => '12,1',
			'14000.5' => "14.000,5",
		));

		$this->checkDataFormatting('nl_NL', $formatting);

		$this->checkInputValidation('de_DE', $validation = array(
			'13000' => 13000,
			'12,00' => 12.00,
			'12.00' => false,
			'11 000' => false,
			'11.000' => 11000,
			'11,000' => 11.0,
			'15 000,5' => false,
			'15 000.5' => false,
			'15.000,5' => 15000.5,
			'15,000.5' => false,
		));

		$this->checkInputValidation('nl_NL', $validation);
	}

	/**
	 * French locale values
	 */
	public function testFrenchLocales() {
		$this->checkDataFormatting('fr_FR', array(
			'13000' => "13 000",
			'15' => '15',
			'12.0' => '12,0',
			'12.1' => '12,1',
			'14000.5' => "14 000,5",
		));
		
		$this->checkInputValidation('fr_FR', array(
			'13000' => 13000,
			'12,00' => 12.00,
			'12.00' => false,
			'11 000' => 11000,
			'11.000' => false,
			'11,000' => 11.000,
			'15 000,5' => 15000.5,
			'15 000.5' => false,
			'15.000,5' => false,
			'15,000.5' => false,
		));
	}

	/**
	 * US locale values
	 */
	public function testUSLocales() {
		$this->checkDataFormatting('en_US', array(
			'13000' => "13,000",
			'15' => '15',
			'12.0' => '12.0',
			'12.1' => '12.1',
			'14000.5' => "14,000.5",
		));

		$this->checkInputValidation('en_US', array(
			'13000' => 13000,
			'12,00' => false,
			'12.00' => 12.00,
			'11 000' => false,
			'11.000' => 11.0,
			'11,000' => 11000,
			'15 000,5' => false,
			'15 000.5' => false,
			'15.000,5' => false,
			'15,000.5' => 15000.5,
		));
	}

	/**
	 * Test empty values
	 */
	public function testEmptyValidator() {
		i18n::set_locale('en_US');
		$field = new NumericField('Number');

		// Treats '0' as given for the sake of required fields
		$field->setValue('0');
		$this->assertTrue($field->validate($validator));
		$this->assertEquals(0, $field->dataValue());

		// Treat literal 0
		$field->setValue(0);
		$this->assertTrue($field->validate($validator));
		$this->assertEquals(0, $field->dataValue());

		// Should fail the 'required but not given' test
		$field->setValue('');
		$this->assertFalse($field->validate($validator));

		$field->setValue(false);
	}

	public function testReadonly() {
		i18n::set_locale('en_US');
		$field = new NumericField('Number');
		$this->assertRegExp("#<span[^>]+>\s*0\s*<\/span>#", "".$field->performReadonlyTransformation()->Field()."");
	}

	public function testNumberTypeOnInputHtml() {
		$field = new NumericField('Number');

		$html = $field->Field();
		$this->assertContains('type="number"', $html, 'number type set');
		$this->assertContains('step="any"', $html, 'step value set to any');
	}

}

class NumericFieldTest_Object extends DataObject implements TestOnly {

	private static $db = array(
		'Number' => 'Float'
	);
}