<?php
namespace Eris;

use OutOfBoundsException;
use DateInterval;
use InvalidArgumentException;

trait TestTrait
{
    // TODO: make this private as much as possible
    // TODO: it's time, extract an object?
    private $quantifiers = [];
    private $iterations = 100;
    private $listeners = [];
    private $terminationConditions = [];
    private $randFunction = 'rand';
    private $seedFunction = 'srand';
    private $shrinkerFactoryMethod = 'multiple';
    protected $seed;
    protected $shrinkingTimeLimit;

    /**
     * @beforeClass
     */
    public static function erisSetupBeforeClass()
    {
        foreach (['Generator', 'Antecedent', 'Listener', 'Random'] as $namespace) {
            foreach (glob(__DIR__ . '/' . $namespace . '/*.php') as $filename) {
                require_once($filename);
            }
        }
    }

    /**
     * @before
     */
    public function erisSetup()
    {
        $this->seed = getenv('ERIS_SEED') ?: (int) (microtime(true)*1000000);
        $this->listeners = array_filter(
            $this->listeners,
            function ($listener) {
                return !($listener instanceof MinimumEvaluations);
            }
        );
        $tags = $this->getAnnotations();//from TestCase of PHPunit
        $this->withRand($this->getAnnotationValue($tags, 'eris-method', 'rand', 'strval'));
        $this->iterations = $this->getAnnotationValue($tags, 'eris-repeat', 100, 'intval');
        $this->shrinkingTimeLimit = $this->getAnnotationValue($tags, 'eris-shrink', null, 'intval');
        $this->listeners[] = Listener\MinimumEvaluations::ratio($this->getAnnotationValue($tags, 'eris-ratio', 50, 'floatval')/100);
        $duration = $this->getAnnotationValue($tags, 'eris-duration', false, 'strval');
        if ($duration) {
            $terminationCondition = new Quantifier\TimeBasedTerminationCondition('time', new DateInterval($duration));
            $this->listeners[] = $terminationCondition;
            $this->terminationConditions[] = $terminationCondition;
        }
    }

    /**
     * @param array $annotations
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    private function getAnnotationValue(array $annotations, $key, $default, $cast)
    {
        $annotation = $this->getAnnotation($annotations, $key);
        return isset($annotation[0])?$cast($annotation[0]):$default;
    }

    /**
     * @param array $annotations
     * @param string $key
     * @return array
     */
    private function getAnnotation(array $annotations, $key)
    {
        if (isset($annotations['method'][$key])) {
            return $annotations['method'][$key];
        }
        return isset($annotations['class'][$key])?$annotations['class'][$key]:[];
    }

    /**
     * @after
     */
    public function erisTeardown()
    {
        $this->dumpSeedForReproducing();
    }

    /**
     * Maybe: we could add --filter options to the command here,
     * since now the original command is printed.
     */
    private function dumpSeedForReproducing()
    {
        if (!$this->hasFailed()) {
            return;
        }
        $command = PHPUnitCommand::fromSeedAndName($this->seed, $this->toString());
        echo PHP_EOL."Reproduce with:".PHP_EOL.$command.PHP_EOL;
    }

    /**
     * @return self
     */
    protected function withRand($randFunction)
    {
        // TODO: invert and wrap rand, srand into objects?
        if ($randFunction instanceof \Eris\Random\RandomRange) {
            $this->randFunction = function ($lower = null, $upper = null) use ($randFunction) {
                return $randFunction->rand($lower, $upper);
            };
            $this->seedFunction = function ($seed) use ($randFunction) {
                return $randFunction->seed($seed);
            };
            return $this;
        }
        if (is_callable($randFunction)) {
            switch ($randFunction) {
                case 'rand':
                    $seedFunction = 'srand';
                    break;
                case 'mt_rand':
                    $seedFunction = 'mt_srand';
                    break;
                default:
                    throw new BadMethodCallException("When specifying random generators different from the standard ones, you must also pass a \$seedFunction callable that will be called to seed it.");
            }
            $this->randFunction = $randFunction;
            $this->seedFunction = $seedFunction;
        }
        return $this;
    }

    /**
     * forAll($generator1, $generator2, ...)
     * @return Quantifier\ForAll
     */
    public function forAll()
    {
        call_user_func($this->seedFunction, $this->seed);
        $generators = func_get_args();
        $quantifier = new Quantifier\ForAll(
            $generators,
            $this->iterations,
            new Shrinker\ShrinkerFactory([
                'timeLimit' => $this->shrinkingTimeLimit,
            ]),
            $this->shrinkerFactoryMethod,
            $this->randFunction
        );
        foreach ($this->listeners as $listener) {
            $quantifier->hook($listener);
        }
        foreach ($this->terminationConditions as $terminationCondition) {
            $quantifier->stopOn($terminationCondition);
        }
        $this->quantifiers[] = $quantifier;
        return $quantifier;
    }

    /**
     * @return Sample
     */
    public function sample(Generator $generator, $times = 10)
    {
        return Sample::of($generator, $this->randFunction)->repeat($times);
    }

    /**
     * @return Sample
     */
    public function sampleShrink(Generator $generator, $fromValue = null)
    {
        return Sample::of($generator, $this->randFunction)->shrink($fromValue);
    }
}
