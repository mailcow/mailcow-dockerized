<?php

class Html2TextTest extends PHPUnit_Framework_TestCase {

	function doTest($test, $ignoreXmlError = false) {
		$this->assertTrue(file_exists(__DIR__ . "/$test.html"), "File '$test.html' did not exist");
		$this->assertTrue(file_exists(__DIR__ . "/$test.txt"), "File '$test.txt' did not exist");
		$input = file_get_contents(__DIR__ . "/$test.html");
		$expected = Html2Text\Html2Text::fixNewlines(file_get_contents(__DIR__ . "/$test.txt"));

		$output = Html2Text\Html2Text::convert($input, $ignoreXmlError);

		if ($output != $expected) {
			file_put_contents(__DIR__ . "/$test.output", $output);
		}
		$this->assertEquals($output, $expected);
	}

	function testBasic() {
		$this->doTest("basic");
	}

	function testAnchors() {
		$this->doTest("anchors");
	}

	function testMoreAnchors() {
		$this->doTest("more-anchors");
	}

	function test3() {
		$this->doTest("test3");
	}

	function test4() {
		$this->doTest("test4");
	}

	function testTable() {
		$this->doTest("table");
	}

	function testNbsp() {
		$this->doTest("nbsp");
	}

	function testLists() {
		$this->doTest("lists");
	}

	function testPre() {
		$this->doTest("pre");
	}

	function testFullEmail() {
		$this->doTest("full_email");
	}

	function testImages() {
		$this->doTest("images");
	}

	function testNonBreakingSpaces() {
		$this->doTest("non-breaking-spaces");
	}

	function testUtf8Example() {
		$this->doTest("utf8-example");
	}

	function testWindows1252Example() {
		$this->doTest("windows-1252-example");
	}

	function testMsoffice() {
		$this->doTest("msoffice");
	}

	function testDOMProcessing() {
		$this->doTest("dom-processing");
	}

	function testEmpty() {
		$this->doTest("empty");
	}

	function testHugeMsoffice() {
		$this->doTest("huge-msoffice");
	}

	/**
     * @expectedException PHPUnit_Framework_Error_Warning
     */
	function testInvalidXML() {
		$this->doTest("invalid", false);
	}

	function testInvalidXMLIgnore() {
		$this->doTest("invalid", true);
	}
}
