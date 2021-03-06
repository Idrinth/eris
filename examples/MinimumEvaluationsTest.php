<?php
use Eris\Generator;

class MinimumEvaluationsTest extends PHPUnit_Framework_TestCase
{
    use Eris\TestTrait;

    public function testFailsBecauseOfTheLowEvaluationRatio()
    {
        $this
            ->forAll(
                Generator\choose(0, 100)
            )
            ->when(function ($n) {
                return $n > 90;
            })
            ->then(function ($number) {
                $this->assertTrue($number * 2 > 90 * 2);
            });
    }

    /**
     * @eris-ratio 1
     */
    public function testPassesBecauseOfTheArtificiallyLowMinimumEvaluationRatio()
    {
        $this
            ->forAll(
                Generator\choose(0, 100)
            )
            ->when(function ($n) {
                return $n > 90;
            })
            ->then(function ($number) {
                $this->assertTrue($number * 2 > 90 * 2);
            });
    }
}
