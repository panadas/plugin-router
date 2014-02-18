<?php
namespace Panadas\RouterPlugin;

use Panadas\Framework\DataStructure\ActionArgs;
use Panadas\RouterPlugin\DataStructure\PatternParamValues;
use Panadas\RouterPlugin\DataStructure\PatternParamRegexps;

class Route
{

    private $pattern;
    private $patternRegexp;
    private $patternParamValues;
    private $patternParamRegexps;
    private $actionClass;
    private $actionArgs;

    const PATTERN_PARAM_REGEXP = "[a-z0-9_]+";
    const PATTERN_PARAM_REGEXP_DEFAULT = "[^/]+";

    public function __construct(
        $pattern,
        $actionClass,
        ActionArgs $actionArgs = null,
        PatternParamValues $patternParamValues = null,
        PatternParamRegexps $patternParamRegexps = null
    ) {
        if (null === $actionArgs) {
            $actionArgs = new ActionArgs();
        }

        if (null === $patternParamValues) {
            $patternParamValues = new PatternParamValues();
        }

        if (null === $patternParamRegexps) {
            $patternParamRegexps = new PatternParamRegexps();
        }

        $this
            ->setPatternParamValues($patternParamValues)
            ->setPatternParamRegexps($patternParamRegexps)
            ->setPattern($pattern)
            ->setActionClass($actionClass)
            ->setActionArgs($actionArgs);
    }

    public function getPattern()
    {
        return $this->pattern;
    }

    protected function setPattern($pattern)
    {
        $this->pattern = $pattern;

        return $this->updatePatternRegexp();
    }

    public function getPatternRegexp()
    {
        return $this->patternRegexp;
    }

    protected function setPatternRegexp($patternRegexp)
    {
        $this->patternRegexp = $patternRegexp;

        return $this;
    }

    public function getPatternParamValues()
    {
        return $this->patternParamValues;
    }

    protected function setPatternParamValues(PatternParamValues $patternParamValues)
    {
        $this->patternParamValues = $patternParamValues;

        return $this;
    }

    public function getPatternParamRegexps()
    {
        return $this->patternParamRegexps;
    }

    protected function setPatternParamRegexps(PatternParamRegexps $patternParamRegexps)
    {
        $this->patternParamRegexps = $patternParamRegexps;

        return $this;
    }

    public function getActionClass()
    {
        return $this->actionClass;
    }

    protected function setActionClass($actionClass)
    {
        $this->actionClass = $actionClass;

        return $this;
    }

    public function getActionArgs()
    {
        return $this->actionArgs;
    }

    protected function setActionArgs(ActionArgs $actionArgs)
    {
        $this->actionArgs = $actionArgs;

        return $this;
    }

    public function getPatternParamNames()
    {
        preg_match_all("/:(" . static::PATTERN_PARAM_REGEXP . ")/i", $this->getPattern(), $matches);

        return $matches[1];
    }

    protected function updatePatternRegexp()
    {
        $replacements = [];

        $patternParamRegexps = $this->getPatternParamRegexps();

        foreach ($this->getPatternParamNames() as $name) {

            if ($patternParamRegexps->has($name)) {
                $regexp = $patternParamRegexps->get($name);
            } else {
                $regexp = static::PATTERN_PARAM_REGEXP_DEFAULT;
            }

            $replacements[":{$name}"] = "(?<" . preg_quote($name, "/") . ">{$regexp})";

        }

        $pattern = $this->getPattern();
        $isFolder = (substr_count($pattern, ".") === 0);

        if ($isFolder) {
            $pattern = rtrim($pattern, "/");
        }

        $regexp = strtr($pattern, $replacements);

        if ($isFolder) {
            $regexp .= "/?";
        }

        return $this->setPatternRegexp($regexp);
    }

    public function getUri(array $params = [])
    {
        $replacements = [];

        $patternParamValues = $this->getPatternParamValues();

        foreach ($this->getPatternParamNames() as $name) {

            $value = null;

            if (array_key_exists($name, $params)) {
                $value = $params[$name];
                unset($params[$name]);
            } elseif ($patternParamValues->has($name)) {
                $value = $patternParamValues->get($name);
            }

            if (null === $value) {
                throw new \InvalidArgumentException(
                    "A value for \"{$name}\" must be provided to generate a URI for route: {$this->getName()}"
                );
            }

            $replacements[":{$name}"] = $value;

        }

        $uri = strtr($this->getPattern(), $replacements);

        if ($params) {
            $uri .= "?" . http_build_query($params);
        }

        return $uri;
    }
}
