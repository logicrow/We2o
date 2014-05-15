<?php
namespace We2o\Component\Semaphore\Tests;

use We2o\Component\Semaphore\Calculator;

class CalculatorTest extends \PHPUnit_Framework_TestCase
{
    public function testAddNumber(){
        $calc = new Calculator();
        $this->assertEquals(4, $calc->add(2, 2));
    }
}
