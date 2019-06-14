<?php
namespace Affinity4\Magic;

/**
 * Magic Trait
 * 
 * 1. Throws Error on undefined property calls
 * 2. Adds event handling to class
 * 3. Set and get using raw properties
 * 4. Will suggest correct spelling for typos in property calls
 */
trait Magic
{
    // --------------------------------------------------
    // CUSTOM MAGIC METHODS
    // --------------------------------------------------

    /**
     * __isEventProperty
     * 
     * Check if the given propery is an event (e.g. onSave, onError)
     * 
     * @author Luke Watts <luke@affinity4.ie>
     * 
     * @since 1.0.0
     *
     * @param string $class
     * @param string $name
     * 
     * @return bool
     */
    public static function __isEventProperty(string $class, string $name): bool
    {
        static $cache;

        $prop = &$cache[$class][$name];

        if ($prop === null) {
            $prop = false;
            try {
				$rp = new \ReflectionProperty($class, $name);
				if ($rp->isPublic() && !$rp->isStatic()) {
                    $prop = (preg_match('/^on[A-Z]+\w*/', $name) === 1);
				}
			} catch (\ReflectionException $e) {
                // No prop...return false
            }
        }

        return ($prop);
    }

    /**
     * __getSpellingSuggestion
     * 
	 * Finds the best suggestion (for 8-bit encoding)
     * 
     * @author Luke Watts <luke@affinity4.ie>
     * 
     * @since 1.0.0
     * 
	 * @param  (\ReflectionFunctionAbstract|\ReflectionParameter|\ReflectionClass|\ReflectionProperty|string)[]  $possibilities
     * 
	 * @internal
	 */
	public static function __getSpellingSuggestion(array $possibilities, string $value): ?string
	{
		$norm = preg_replace($re = '#^(get|set|has|is|add)(?=[A-Z])#', '', $value);
		$best = null;
		$min  = (strlen($value) / 4 + 1) * 10 + .1;
		foreach (array_unique($possibilities, SORT_REGULAR) as $item) {
			$item = $item instanceof \Reflector ? $item->getName() : $item;
			if ($item !== $value && (
                ($len = levenshtein($item, $value, 10, 11, 10)) < $min
                || ($len = levenshtein(preg_replace($re, '', $item), $norm, 10, 11, 10) + 20) < $min
            )) {
				$min  = $len;
				$best = $item;
			}
        }
        
		return $best;
    }
    
    /**
     * __parseFullDoc
     * 
     * Parse full PHP file and get docblock, traits and parent class info
     * 
     * @author Luke Watts <luke@affinity4.ie>
     * 
     * @since 1.0.0
     *
     * @param \ReflectionClass $rc
     * @param string           $pattern
     * 
     * @return array
     */
    private static function __parseFullDoc(\ReflectionClass $rc, string $pattern): array
	{
		do {
			$doc[] = $rc->getDocComment();
			$traits = $rc->getTraits();
			while ($trait = array_pop($traits)) {
				$doc[] = $trait->getDocComment();
				$traits += $trait->getTraits();
			}
        } while ($rc = $rc->getParentClass());
        
		return preg_match_all($pattern, implode($doc), $m) ? $m[1] : [];
    }
    
    /**
     * __strictStaticCall
     * 
     * @author Luke Watts <luke@affinity4.ie>
     * 
     * @since 1.0.0
     * 
     * @param string $class
     * @param string $method
     * 
	 * @throws \Error
	 */
	public static function __strictStaticCall(string $class, string $method): void
	{
		$hint = self::getSuggestion(
			array_filter(
                (new \ReflectionClass($class))->getMethods(\ReflectionMethod::IS_PUBLIC),
                function ($m) {
                    return $m->isStatic();
                }
            ),
			$method
        );
        
		throw new \Error("Call to undefined static method $class::$method()" . ($hint ? ", did you mean $hint()?" : '.'));
	}
    
    /**
     * __strictCall
     * 
     * @author Luke Watts <luke@affinity4.ie>
     * 
     * @since 1.0.0
     * 
	 * @throws \Error
	 */
	public static function __strictCall(string $class, string $method, array $additionalMethods = []): void
	{
		$hint = self::__getSpellingSuggestion(array_merge(
			get_class_methods($class),
			self::__parseFullDoc(new \ReflectionClass($class), '~^[ \t*]*@method[ \t]+(?:\S+[ \t]+)??(\w+)\(~m'),
			$additionalMethods
		), $method);

		if (method_exists($class, $method)) { // called parent::$method()
			$class = 'parent';
        }
        
		throw new \Error("Call to undefined method $class::$method()" . ($hint ? ", did you mean $hint()?" : '.'));
    }
    
     /**
      * __getMagicProperties
      *
      * Returns array of magic properties defined by annotation @property

      @author Luke Watts <luke@affinity4.ie>
     * 
     * @since 1.0.0
      *
      * @param string $class

      * @return array of [name => bit mask]
      */
	public static function __getMagicProperties(string $class): array
	{
		static $cache;
		$props = &$cache[$class];
		if ($props !== null) {
			return $props;
		}

		$rc = new \ReflectionClass($class);
		preg_match_all(
			'~^  [ \t*]*  @property(|-read|-write)  [ \t]+  [^\s$]+  [ \t]+  \$  (\w+)  ()~mx',
			(string) $rc->getDocComment(), $matches, PREG_SET_ORDER
		);

		$props = [];
		foreach ($matches as [, $type, $name]) {
			$uname = ucfirst($name);
			$write = $type !== '-read'
				&& $rc->hasMethod($nm = 'set' . $uname)
				&& ($rm = $rc->getMethod($nm)) && $rm->getName() === $nm && !$rm->isPrivate() && !$rm->isStatic();
			$read = $type !== '-write'
				&& ($rc->hasMethod($nm = 'get' . $uname) || $rc->hasMethod($nm = 'is' . $uname))
				&& ($rm = $rc->getMethod($nm)) && $rm->getName() === $nm && !$rm->isPrivate() && !$rm->isStatic();

			if ($read || $write) {
				$props[$name] = $read << 0 | ($nm[0] === 'g') << 1 | $rm->returnsReference() << 2 | $write << 3;
			}
		}

		foreach ($rc->getTraits() as $trait) {
			$props += self::__getMagicProperties($trait->getName());
		}

		if ($parent = get_parent_class($class)) {
			$props += self::__getMagicProperties($parent);
        }
        
		return $props;
    }

    /**
     * __strictGet
     * 
     * @author Luke Watts <luke@affinity4.ie>
     * 
     * @since 1.0.0
     *
     * @param string $class
     * @param string $name
     * 
     * @throws \Error
     */
	public static function __strictGet(string $class, string $name): void
	{
		$rc = new \ReflectionClass($class);
		$hint = self::__getSpellingSuggestion(array_merge(
			array_filter(
                $rc->getProperties(\ReflectionProperty::IS_PUBLIC),
                function ($p) {
                    return !$p->isStatic();
                }
            ),
			self::__parseFullDoc($rc, '~^[ \t*]*@property(?:-read)?[ \t]+(?:\S+[ \t]+)??\$(\w+)~m')
        ), $name);
        
		throw new \Error("Cannot read an undeclared property $class::\$$name" . ($hint ? ", did you mean \$$hint?" : '.'));
    }

    /**
     * __strictSet
     *
     * @author Luke Watts <luke@affinity4.ie>
     * 
     * @since 1.0.0
     *
     * @param string $class
     * @param string $name
     * 
     * @throws \Error
     */
	public static function __strictSet(string $class, string $name): void
	{
		$rc = new \ReflectionClass($class);
		$hint = self::__getSpellingSuggestion(array_merge(
			array_filter(
                $rc->getProperties(\ReflectionProperty::IS_PUBLIC),
                function ($p) {
                    return !$p->isStatic();
                }
            ),
			self::__parseFullDoc($rc, '~^[ \t*]*@property(?:-write)?[ \t]+(?:\S+[ \t]+)??\$(\w+)~m')
        ), $name);
        
		throw new \Error("Cannot write to an undeclared property $class::\$$name" . ($hint ? ", did you mean \$$hint?" : '.'));
    }
    
    /**
	 * Checks if the public non-static property exists
     * 
     * @author Luke Watts <luke@affinity4.ie>
     * 
     * @since 1.0.0
     * 
	 * @return bool|string returns 'event' if the property exists and has event like name
     * 
	 * @internal
	 */
	public static function __hasProperty(string $class, string $name)
	{
		static $cache;
		$prop = &$cache[$class][$name];
		if ($prop === null) {
			$prop = false;
			try {
				$rp = new \ReflectionProperty($class, $name);
				if ($rp->isPublic() && !$rp->isStatic()) {
					$prop = true;
				}
			} catch (\ReflectionException $e) {
			}
		}
		return $prop;
	}
    
    // --------------------------------------------------
    // INTERNAL MAGIC METHODS
    // --------------------------------------------------

    /**
     * __call
     * 
     * @author Luke Watts <luke@affinity4.ie>
     * 
     * @since 1.0.0
     * 
     * @param string $name
     * @param array  $args
     * 
	 * @throws \Error
	 */
	public function __call(string $name, array $args)
	{
		$class = get_class($this);

        // calling event handlers
		if (self::__isEventProperty($class, $name)) {
			if (is_iterable($this->$name)) {
				foreach ($this->$name as $handler) {
					$handler(...$args);
				}
			} elseif ($this->$name !== null) {
				throw new \Error("Property $class::$$name must be iterable or null, " . gettype($this->$name) . ' given.');
			}
		} else {
            self::__strictCall($class, $name);
		}
    }

	/**
     * __callStatic
     * 
     * @author Luke Watts <luke@affinity4.ie>
     * 
     * @since 1.0.0
     * 
     * @param string $name
     * @param array  $args
     * 
	 * @throws \Error
	 */
	public static function __callStatic(string $name, array $args)
	{
        self::__strictStaticCall(static::class, $name);
    }

	/**
     * &__get
     * 
     * @author Luke Watts <luke@affinity4.ie>
     * 
     * @since 1.0.0
     * 
     * @param string $name
     * 
	 * @return mixed
     * 
	 * @throws \Error if the property is not defined.
	 */
	public function &__get(string $name)
	{
		$class = get_class($this);

        // property getter
		if ($prop = self::__getMagicProperties($class)[$name] ?? null) {
			if (!($prop & 0b0001)) {
				throw new \Error("Cannot read a write-only property $class::\$$name.");
            }
            
			$m = ($prop & 0b0010 ? 'get' : 'is') . $name;
			if ($prop & 0b0100) { // return by reference
				return $this->$m();
			} else {
                $val = $this->$m();
                
				return $val;
			}
		} else {
            self::__strictGet($class, $name);
		}
	}

	/**
     * __set
     * 
     * @author Luke Watts <luke@affinity4.ie>
     * 
     * @since 1.0.0
     * 
     * @param string $name
     * @param mixed  $value
     * 
	 * @throws \Error if the property is not defined or is read-only
	 */
	public function __set(string $name, $value)
	{
		$class = get_class($this);

		if (self::__hasProperty($class, $name)) {
			$this->$name = $value;
		} elseif ($prop = self::__getMagicProperties($class)[$name] ?? null) {
			if (!($prop & 0b1000)) {
				throw new \Error("Cannot write to a read-only property $class::\$$name.");
            }
            
			$this->{'set' . $name}($value);
		} else {
			self::__strictSet($class, $name);
		}
	}

	/**
     * __unset
     * 
     * @author Luke Watts <luke@affinity4.ie>
     * 
     * @since 1.0.0
     * 
     * @param string $name
     * 
	 * @throws \Error
	 */
	public function __unset(string $name)
	{
		$class = get_class($this);
		if (!self::__hasProperty($class, $name)) {
			throw new \Error("Cannot unset the property $class::\$$name.");
		}
	}

    /**
     * __isset
     * 
     * @author Luke Watts <luke@affinity4.ie>
     * 
     * @since 1.0.0
     * 
     * @param string $name
     * 
     * @return bool
     */
	public function __isset(string $name): bool
	{
		return isset(self::__getMagicProperties(get_class($this))[$name]);
	}
}
