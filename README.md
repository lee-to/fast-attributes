### Fast Attributes

### Usage

```php
// All class attributes
$classAttributes = Attributes::for(ClassWithAttributes::class)->get();
```

```php
// Only SomeAttribute class attributes
$someAttributes = Attributes::for(ClassWithAttributes::class)
    ->attribute(SomeAttribute::class)
    ->get();
```

```php
// Only SomeAttribute instance
$someAttribute = Attributes::for(ClassWithAttributes::class)
    ->attribute(SomeAttribute::class)
    ->first();
```

```php
// SomeAttribute variable property
$someAttribute = Attributes::for(ClassWithAttributes::class)
    ->attribute(SomeAttribute::class)
    ->first('variable');
```

```php
// Method parameter attributes
$someAttribute = Attributes::for(ClassWithAttributes::class)
    ->method('someMethod')
    ->parameter('variable')
    ->get();
```

```php
$someAttribute = Attributes::for(ClassWithAttributes::class)
    ->constant('VARIABLE')
    ->property('variable')
    ->method('someMethod')
    ->parameter('variable')
    ->get();
```
