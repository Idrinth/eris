<?php
namespace Eris;

class Facade
{
    use TestTrait;

    public function __construct()
    {
        $this->erisSetupBeforeClass();
        $this->erisSetup();
    }

    /**
     * sadly this facade has no option to retrieve annotations of testcases
     * @return array
     */
    protected function getAnnotations()
    {
        return array();
    }
}
