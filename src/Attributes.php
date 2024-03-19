<?php

declare(strict_types=1);

namespace Leeto\FastAttributes;

use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionClassConstant;
use ReflectionException;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionProperty;

/**
 * @template-covariant AttributeClass
 *
 */
final class Attributes
{
    private bool $withClass = false;

    private ?string $method = null;

    private ?string $property = null;

    private ?string $constant = null;

    private ?string $parameter = null;

    private bool $withMethod = false;

    /** @var array<int, AttributeClass|ReflectionAttribute<object>> */
    private array $attributes = [];

    private bool $constants = false;

    private bool $methods = false;

    private bool $properties = false;

    private bool $parameters = false;

    private ?CacheInterface $cache = null;

    /**
     * @param  object|class-string  $class
     * @param  ?class-string  $attribute
     */
    public function __construct(
        private object|string $class,
        private ?string $attribute = null,
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
    public function cached(?CacheInterface $cache = null): self
    {
        $this->cache = $cache ?? new MemoryCache();

        return $this;
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
    public function class(): self
    {
        $this->withClass = true;

        return $this;
    }

    /**
     * @return self<AttributeClass>
     */
    public function constants(): self
    {
        $this->constants = true;

        return $this;
    }

    /**
     * @return self<AttributeClass>
     */
    public function properties(): self
    {
        $this->properties = true;

        return $this;
    }

    /**
     * @return self<AttributeClass>
     */
    public function methods(): self
    {
        $this->methods = true;

        return $this;
    }

    /**
     * @return self<AttributeClass>
     */
    public function parameters(): self
    {
        $this->parameters = true;

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
     * @return list<ReflectionAttribute<object>>|list<AttributeClass>
     * @throws ReflectionException|InvalidArgumentException
     */
    public function get(): array
    {
        return $this->retrieve();
    }

    /**
     * @return AttributeClass|ReflectionAttribute|mixed|null
     * @throws ReflectionException|InvalidArgumentException
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
     * @throws ReflectionException|InvalidArgumentException
     */
    private function retrieve(): array
    {
        $key = is_object($this->class)
            ? $this->class::class
            : $this->class;

        if($this->cache?->has($key)) {
            /**
             * @var list<AttributeClass>|list<ReflectionAttribute<object>> $cached
             */
            $cached = $this->cache->get($key);

            return $cached;
        }

        $reflection = new ReflectionClass($this->class);

        $this->fillAttributes($reflection, $this->withClass);

        if ($this->properties || ! is_null($this->property)) {
            foreach ($reflection->getProperties() as $property) {
                $this->fillAttributes(
                    $property,
                    is_null($this->property) || $this->property === $property->getName()
                );
            }
        }

        if ($this->constants || ! is_null($this->constant)) {
            foreach ($reflection->getReflectionConstants() as $constant) {
                $this->fillAttributes(
                    $constant,
                    is_null($this->constant) || $this->constant === $constant->getName()
                );
            }
        }

        if ($this->methods || ! is_null($this->method)) {
            foreach ($reflection->getMethods() as $method) {
                $this->retrieveMethodOrParametersAttributes($method);
            }
        }

        $this->cache?->set($key, $this->attributes);

        return $this->attributes;
    }

    private function retrieveMethodOrParametersAttributes(ReflectionMethod $method): void
    {
        if (is_null($this->parameter) || $this->withMethod) {
            $this->fillAttributes(
                $method,
                is_null($this->method) || $this->method === $method->getName()
            );
        }

        if ($this->parameters || ! is_null($this->parameter)) {
            foreach ($method->getParameters() as $parameter) {
                $this->fillAttributes(
                    $parameter,
                    is_null($this->parameter) || $this->parameter === $parameter->getName()
                );
            }
        }
    }

    /**
     * @param  ReflectionClass<object>|ReflectionProperty|ReflectionClassConstant|false|ReflectionMethod|ReflectionParameter  $reflection
     * @param  bool  $condition
     * @return void
     */
    private function fillAttributes(mixed $reflection, bool $condition = true): void
    {
        if ($condition) {
            $this->attributes = [
                ...$this->attributes,
                ...$this->retrieveAttributes($reflection),
            ];
        }
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
