<?php

namespace Iza\Moderare;

use Iza\Moderare\Resource\Collection;
use Iza\Moderare\Resource\Item;
use Iza\Moderare\Resource\ResourceAbstract;

abstract class ValidatorAbstract
{
    /**
     * Resources that can be included if requested.
     *
     * @var array
     */
    protected $availableIncludes = array();

    /**
     * Include resources without needing it to be requested.
     *
     * @var array
     */
    protected $defaultIncludes = array();

    /**
     * The transformer should know about the current scope, so we can fetch relevant params.
     *
     * @var Scope
     */
    protected $currentScope;

    /**
     * Getter for availableIncludes.
     *
     * @return array
     */
    public function getAvailableIncludes()
    {
        return $this->availableIncludes;
    }

    /**
     * Getter for defaultIncludes.
     *
     * @return array
     */
    public function getDefaultIncludes()
    {
        return $this->defaultIncludes;
    }

    /**
     * Getter for currentScope.
     *
     * @return \League\Fractal\Scope
     */
    public function getCurrentScope()
    {
        return $this->currentScope;
    }

    /**
     * Figure out which includes we need.
     *
     * @internal
     *
     * @param Scope $scope
     *
     * @return array
     */
    private function figureOutWhichIncludes(Scope $scope, $data)
    {
        $includes = $this->getDefaultIncludes();
        foreach ($this->getAvailableIncludes() as $include) {
            //if the given resource key is added to the data fire the include
            if (array_key_exists($include, $data)) {
                $includes[] = $include;
            }
        }

        return $includes;
    }

    /**
     * This method is fired to loop through available includes, see if any of
     * them are requested and permitted for this scope.
     *
     * @internal
     *
     * @param Scope $scope
     * @param mixed $data
     *
     * @return array
     */
    public function processIncludedResources(Scope $scope, $data)
    {
        $includedData = array();

        $includes = $this->figureOutWhichIncludes($scope, $data);

        foreach ($includes as $include) {
            $includedData = $this->includeResourceIfAvailable(
                $scope,
                $data,
                $includedData,
                $include
            );
        }

        return $includedData === array() ? false : $includedData;
    }

    /**
     * Include a resource only if it is available on the method.
     *
     * @internal
     *
     * @param Scope $scope
     * @param mixed $data
     * @param array $includedData
     * @param string $include
     *
     * @return array
     */
    private function includeResourceIfAvailable(
        Scope $scope,
        $data,
        $includedData,
        $include
    ) {
        if ($resource = $this->callIncludeMethod($scope, $include, $data)) {
            $childScope = $scope->embedChildScope($include, $resource);

            $includedData[$include] = $childScope->validate();
        }

        return $includedData;
    }

    /**
     * Call Include Method.
     *
     * @internal
     *
     * @param Scope $scope
     * @param string $includeName
     * @param mixed $data
     *
     * @throws \Exception
     *
     * @return \Iza\Moderare\Resource\ResourceInterface
     */
    protected function callIncludeMethod(Scope $scope, $includeName, $data)
    {
        $scopeIdentifier = $scope->getIdentifier($includeName);
        $params = $scope->getManager()->getIncludeParams($scopeIdentifier);

        $dataScope = $data[$includeName];

        // Check if the method name actually exists
        $methodName = 'include' . str_replace(' ', '', ucwords(str_replace('_', ' ', $includeName)));

        $resource = call_user_func(array($this, $methodName), $dataScope, $params);

        if ($resource === null) {
            return false;
        }

        if (!$resource instanceof ResourceAbstract) {
            throw new \Exception(sprintf(
                'Invalid return value from %s::%s(). Expected %s, received %s.',
                __CLASS__,
                $methodName,
                'Iza\Moderare\Resource\ResourceAbstract',
                gettype($resource)
            ));
        }

        return $resource;
    }

    /**
     * Setter for availableIncludes.
     *
     * @param array $availableIncludes
     *
     * @return $this
     */
    public function setAvailableIncludes($availableIncludes)
    {
        $this->availableIncludes = $availableIncludes;

        return $this;
    }

    /**
     * Setter for defaultIncludes.
     *
     * @param array $defaultIncludes
     *
     * @return $this
     */
    public function setDefaultIncludes($defaultIncludes)
    {
        $this->defaultIncludes = $defaultIncludes;

        return $this;
    }

    /**
     * Setter for currentScope.
     *
     * @param Scope $currentScope
     *
     * @return $this
     */
    public function setCurrentScope($currentScope)
    {
        $this->currentScope = $currentScope;

        return $this;
    }

    /**
     * Create a new item resource object.
     *
     * @param mixed $data
     * @param ValidatorAbstract|callable $transformer
     * @param string $resourceKey
     *
     * @return Item
     */
    protected function item($data, $transformer, $resourceKey = null)
    {
        return new Item($data, $transformer, $resourceKey);
    }

    /**
     * Create a new collection resource object.
     *
     * @param mixed $data
     * @param ValidatorAbstract|callable $transformer
     * @param string $resourceKey
     *
     * @return Collection
     */
    protected function collection($data, $transformer, $resourceKey = null)
    {
        return new Collection($data, $transformer, $resourceKey);
    }

    /**
     * @param $data
     * @return mixed
     */
    abstract public function validate($data);
}
