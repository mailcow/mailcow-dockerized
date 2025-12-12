# PHP 7.1 enums

[![Build Status](https://github.com/DASPRiD/Enum/actions/workflows/tests.yml/badge.svg)](https://github.com/DASPRiD/Enum/actions?query=workflow%3Atests)
[![Coverage Status](https://coveralls.io/repos/github/DASPRiD/Enum/badge.svg?branch=master)](https://coveralls.io/github/DASPRiD/Enum?branch=master)
[![Latest Stable Version](https://poser.pugx.org/dasprid/enum/v/stable)](https://packagist.org/packages/dasprid/enum)
[![Total Downloads](https://poser.pugx.org/dasprid/enum/downloads)](https://packagist.org/packages/dasprid/enum)
[![License](https://poser.pugx.org/dasprid/enum/license)](https://packagist.org/packages/dasprid/enum)

It is a well known fact that PHP is missing a basic enum type, ignoring the rather incomplete `SplEnum` implementation
which is only available as a PECL extension. There are also quite a few other userland enum implementations around,
but all of them have one or another compromise. This library tries to close that gap as far as PHP allows it to.

## Usage

### Basics

At its core, there is the `DASPRiD\Enum\AbstractEnum` class, which by default will work with constants like any other
enum implementation you might know. The first clear difference is that you should define all the constants as protected
(so nobody outside your class can read them but the `AbstractEnum` can still do so). The other even mightier difference
is that, for simple enums, the value of the constant doesn't matter at all. Let's have a look at a simple example:

```php
use DASPRiD\Enum\AbstractEnum;

/**
 * @method static self MONDAY()
 * @method static self TUESDAY()
 * @method static self WEDNESDAY()
 * @method static self THURSDAY()
 * @method static self FRIDAY()
 * @method static self SATURDAY()
 * @method static self SUNDAY()
 */
final class WeekDay extends AbstractEnum
{
    protected const MONDAY = null;
    protected const TUESDAY = null;
    protected const WEDNESDAY = null;
    protected const THURSDAY = null;
    protected const FRIDAY = null;
    protected const SATURDAY = null;
    protected const SUNDAY = null;
}
```

If you need to provide constants for either internal use or public use, you can mark them as either private or public,
in which case they will be ignored by the enum, which only considers protected constants as valid values. As you can
see, we specifically defined the generated magic methods in a class level doc block, so anyone using this class will
automatically have proper auto-completion in their IDE. Now since you have defined the enum, you can simply use it like
that:

```php
function tellItLikeItIs(WeekDay $weekDay)
{
    switch ($weekDay) {
        case WeekDay::MONDAY():
            echo 'Mondays are bad.';
            break;

        case WeekDay::FRIDAY():
            echo 'Fridays are better.';
            break;

        case WeekDay::SATURDAY():
        case WeekDay::SUNDAY():
            echo 'Weekends are best.';
            break;

        default:
            echo 'Midweek days are so-so.';
    }
}

tellItLikeItIs(WeekDay::MONDAY());
tellItLikeItIs(WeekDay::WEDNESDAY());
tellItLikeItIs(WeekDay::FRIDAY());
tellItLikeItIs(WeekDay::SATURDAY());
tellItLikeItIs(WeekDay::SUNDAY());
```

### More complex example

Of course, all enums are singletons, which are not cloneable or serializable. Thus you can be sure that there is always
just one instance of the same type. Of course, the values of constants are not completely useless, let's have a look at
a more complex example:

```php
use DASPRiD\Enum\AbstractEnum;

/**
 * @method static self MERCURY()
 * @method static self VENUS()
 * @method static self EARTH()
 * @method static self MARS()
 * @method static self JUPITER()
 * @method static self SATURN()
 * @method static self URANUS()
 * @method static self NEPTUNE()
 */
final class Planet extends AbstractEnum
{
    protected const MERCURY = [3.303e+23, 2.4397e6];
    protected const VENUS = [4.869e+24, 6.0518e6];
    protected const EARTH = [5.976e+24, 6.37814e6];
    protected const MARS = [6.421e+23, 3.3972e6];
    protected const JUPITER = [1.9e+27, 7.1492e7];
    protected const SATURN = [5.688e+26, 6.0268e7];
    protected const URANUS = [8.686e+25, 2.5559e7];
    protected const NEPTUNE = [1.024e+26, 2.4746e7];

    /**
     * Universal gravitational constant.
     *
     * @var float
     */
    private const G = 6.67300E-11;

    /**
     * Mass in kilograms.
     *
     * @var float
     */
    private $mass;

    /**
     * Radius in meters.
     *
     * @var float
     */
    private $radius;

    protected function __construct(float $mass, float $radius)
    {
        $this->mass = $mass;
        $this->radius = $radius;
    }

    public function mass() : float
    {
        return $this->mass;
    }

    public function radius() : float
    {
        return $this->radius;
    }

    public function surfaceGravity() : float
    {
        return self::G * $this->mass / ($this->radius * $this->radius);
    }

    public function surfaceWeight(float $otherMass) : float
    {
        return $otherMass * $this->surfaceGravity();
    }
}

$myMass = 80;

foreach (Planet::values() as $planet) {
    printf("Your weight on %s is %f\n", $planet, $planet->surfaceWeight($myMass));
}
```
