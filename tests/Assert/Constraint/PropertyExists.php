<?php
namespace Affinity4\Magic\Tests\Assert\Constraint;

/**
 * Property Exists Constraint
 * 
 * @author Luke Watts <luke@affinity4.ie>
 * 
 * @since 0.0.3
 */
class PropertyExists extends \PHPUnit\Framework\Constraint\Constraint
{
    /**
     * @var string
     */
    private $propertyName;

    /**
     * Constructor
     * 
     * @author Luke Watts <luke@affinity4.ie>
     * 
     * @since 0.0.3
     *
     * @param string $propertyName
     */
    public function __construct(string $propertyName)
    {
        $this->propertyName = $propertyName;
    }

    /**
     * Matches
     * 
     * Evaluates the constraint for parameter $other. Returns true if the
     * constraint is met, false otherwise.
     * 
     * @author Luke Watts <luke@affinity4.ie>
     * 
     * @since 0.0.3
     *
     * @param mixed $other value or object to evaluate
     * 
     * @return bool
     */
    protected function matches($other): bool
    {
        try {
            return (new \ReflectionClass($other))->hasProperty($this->propertyName);
        } catch (\ReflectionException $e) {
            throw new \Exception($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Returns the description of the failure
     *
     * The beginning of failure messages is "Failed asserting that" in most
     * cases. This method should return the second part of that sentence.
     * 
     * @author Luke Watts <luke@affinity4.ie>
     * 
     * @since 0.0.3
     *
     * @param mixed $other evaluated value or object
     */
    protected function failureDescription($other): string
    {
        return \sprintf(
            '%sclass "%s" %s',
            \is_object($other) ? 'object of ' : '',
            \is_object($other) ? \get_class($other) : $other,
            $this->toString()
        );
    }

    /**
     * To String
     * 
     * Returns a string representation of the constraint.
     * 
     * @author Luke Watts <luke@affinity4.ie>
     * 
     * @since 0.0.3
     */
    public function toString(): string
    {
        return \sprintf('has property "%s"', $this->propertyName);
    }
}