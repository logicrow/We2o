<?php

use App\Calculator;
class CalculatorTest extends \PHPUnit_Framework_TestCase
{
    public function testAddNumber(){
        $calc = new Calculator();
        $this->assertEquals(4, $calc->add(2, 2));
    }
}
