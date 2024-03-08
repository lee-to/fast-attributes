<?php

declare(strict_types=1);

namespace Leeto\FastAttributes;

use ReflectionAttribute;
use ReflectionClass;
use ReflectionClassConstant;
use ReflectionException;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionProperty;

/**
 *  TODO
 *  [] Get all attributes from (constants, properties, methods, parameters)
 *  [] Cache
 * @template-covariant AttributeClass
 *
 */
final class Attributes
{
    protected ?string $method = null;

    protected ?string $property = null;

    protected ?string $constant = null;

    protected ?string $parameter = null;

    protected bool $withMethod = false;

    protected bool $withClass = false;

    /** @var array<int, AttributeClass|ReflectionAttribute<object>> */
    protected array $attributes = [];

    /**
     * @param  object|class-string  $class
     * @param  ?class-string  $attribute
     */
    public function __construct(
        protected object|string $class,
        protected ?string $attribute = null,
    ) {
    }

    /**
     * @template T
     * @param  object|class-string  $class
     * @param  ?class-string<T>  $attribute
     * @return self<T>
     */
    public static function for(object|string $class, ?string $attribute = null): self
    {
        return new self($class, $attribute);
    }

    /**
     * @return self<AttributeClass>
     */
    public function method(string $value): self
    {
        $this->method = $value;

        return $this;
    }

    /**
     * @return self<AttributeClass>
     */
    public function property(string $value): self
    {
        $this->property = $value;

        return $this;
    }

    /**
     * @return self<AttributeClass>
     */
    public function constant(string $value): self
    {
        $this->constant = $value;

        return $this;
    }

    /**
     * @return self<AttributeClass>
     */
    public function parameter(string $value, bool $withMethod = false): self
    {
        $this->withMethod = $withMethod;
        $this->parameter = $value;

        return $this;
    }

    /**
     * @template T
     * @param  class-string<T>  $attribute
     * @return self<T>
     */
    public function attribute(string $attribute): self
    {
        return new self($this->class, $attribute);
    }

    /**
     * @return list<AttributeClass>|list<ReflectionAttribute<object>>
     * @throws ReflectionException
     */
    public function get(bool $withClass = false): array
    {
        $this->withClass = $withClass;

        return $this->retrieve();
    }

    /**
     * @return AttributeClass|ReflectionAttribute|mixed|null
     * @throws ReflectionException
     */
    public function first(?string $property = null): mixed
    {
        $attributes = $this->get();

        if ($attributes === []) {
            return null;
        }

        return $this->retrieveAttribute($attributes[0], $property);
    }

    /**
     * @return list<AttributeClass>|list<ReflectionAttribute<object>>
     * @throws ReflectionException
     */
    private function retrieve(): array
    {
        $reflection = new ReflectionClass($this->class);
        $nothingSpecified = true;

        if (! is_null($this->property)) {
            $nothingSpecified = false;

            $this->attributes = [
                ...$this->attributes,
                ...$this->retrieveAttributes(
                    $reflection->getProperty($this->property)
                )
            ];
        }

        if (! is_null($this->constant)) {
            $nothingSpecified = false;

            $this->attributes = [
                ...$this->attributes,
                ...$this->retrieveAttributes(
                    $reflection->getReflectionConstant($this->constant)
                )
            ];
        }

        if (! is_null($this->method)) {
            $nothingSpecified = false;

            $this->attributes = [
                ...$this->attributes,
                ...$this->retrieveMethodOrParameterAttributes($reflection)
            ];
        }

        if ($this->withClass || $nothingSpecified) {
            $this->attributes = [
                ...$this->attributes,
                ...$this->retrieveAttributes($reflection)
            ];
        }

        return $this->attributes;
    }

    /**
     * @param  ReflectionClass<object>  $reflection
     * @return list<AttributeClass>|list<ReflectionAttribute<object>>
     * @throws ReflectionException
     */
    public function retrieveMethodOrParameterAttributes(ReflectionClass $reflection): array
    {
        $attributes = [];

        if (is_null($this->method)) {
            return $attributes;
        }

        $reflectionMethod = $reflection->getMethod($this->method);

        if (! is_null($this->parameter)) {
            /** @var list<ReflectionParameter> $parameters */
            $parameters = array_filter(
                $reflectionMethod->getParameters(),
                fn (ReflectionParameter $param) => $param->getName() === $this->parameter
            );

            $attributes = isset($parameters[0]) ? $this->retrieveAttributes(
                $parameters[0]
            ) : [];
        }

        if(!is_null($this->parameter) && !$this->withMethod) {
            return $attributes;
        }

        return [
            ...$attributes,
            ...$this->retrieveAttributes(
                $reflectionMethod
            ),
        ];
    }

    /**
     * @param  ReflectionClass<object>|ReflectionProperty|ReflectionClassConstant|false|ReflectionMethod|ReflectionParameter  $reflection
     * @return list<AttributeClass>|list<ReflectionAttribute<object>>
     */
    private function retrieveAttributes(mixed $reflection): array
    {
        if ($reflection === false) {
            return [];
        }

        return $reflection->getAttributes(
            $this->attribute,
            ReflectionAttribute::IS_INSTANCEOF
        );
    }

    /**
     * @param  AttributeClass|ReflectionAttribute<object>  $attribute
     * @param  string|null  $property
     * @return mixed
     */
    private function retrieveAttribute(mixed $attribute, ?string $property = null): mixed
    {
        return is_null($property)
            ? $attribute->newInstance()
            : $attribute->newInstance()->{$property};
    }
}
