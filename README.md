# Affinity4 Magic

Magic Trait used to easily add event listeners, spelling suggestions in errors and Javascript __set and __get style setters an getters to any class. Magic!

See the [Wiki](https://github.com/affinity4/magic/wiki) for this repo for full documentation

## Installation

```php
composer require affinity4/magic
```

## Event Listeners

Simply include Magic in any class to instantly have event listeners!

Once you've included Magic as a trait you can then add any public "camelCased" property starting with "on". You now have an event listener! That's all it takes!

Let's say we have a Model called User

```php
class User extends Model
{
    public function register(string $username, string $email, string $password)
    {
        // ...save data to `users` table

        echo "New user saved to `users` table\n";
    }
}
```

When a new user is registered, we want to email them to let them know their login details.

We'll add the Magic trait and create a public `onRegistration` property. It must be an array.

```php
use Affinity4\Magic\Magic;

class User extends Model
{
    use Magic;

    /**
     * @var array
     */
    public $onRegistration = [];

    public function register(string $username, string $email, string $password)
    {
        echo "New user saved to `users` table";

        $this->onRegistration($username, $email, $password);
    }
}
```

Now each time `User::register()` is called the `User::onRegistration()` method will also be called, with the users details available to any event listener attached.

## Event Listeners

To attach an event listener you simply need to add a callback to the onRegistration array. They will then be called in order every time `User::registration()` is executed.

```php
require_once __DIR__ . '/vendor/autoload.php';

use Affinity4\Magic\Magic;

class Model {}

class User extends Model
{
    use Magic;

    /**
     * @var array
     */
    public $onRegistration = [];

    public function register(string $username, string $email, string $password)
    {
        echo "New user saved to `users` table\n";

        $this->onRegistration($username, $email, $password);
    }
}

$User = new User;

$User->onRegistration[] = function($username, $email, $password)
{
    echo "Send email to $email\n";
    echo "Hi $username!";
    echo "Thank you for signing up! Your password is '$password'";
};

$User->register('johndoe', 'john.doe@somewhere.com', 'whoami');

// echos:
// New user saved to `users` table
// Send email to john.doe@somewhere.com
// Hi johndoe!
// Thank you for signing up!. Your password is 'whoami'
```

Of course you'll want to do something more clever (and security conscious) than this but you get the idea.

### "Chained" or "nested" events

__IMPORTANT__

One thing to always be conscious of is that event listeners are not shared across all instances of the class. If you create the following:

```php
require_once __DIR__ . '/vendor/autoload.php';

use Affinity4\Magic\Magic;
use Some\Library\Log;

class Email
{
    use Magic;

    public $onEmail;

    public function send($to, $from, $body)
    {
        // Email stuff...

        $this->onEmail($to, $from, $body);
    }
}

$EmailA = new Email;

$EmailA->onEmail[] = function($to, $from, $body) {
    Log::info("Email sent to $to from $from that said $body");
};

$EmailB = new Email;

$EmailB->send('someone@work.com', 'my.email@home.com', 'Check this out!');
```

No log event will be fired. This is because the events listener that will log the email is only listening to `$EmailA`. 

This might be fairly obvious when side-by-side like this but in a large project this can be confusing if you forget what instance you are dealing with and what events are bound to it. You could get your logs mixed up, or worse. SO BE CAREFUL!

### Containers for Scalability

This is where ServiceManagers, or IoC and DI Containers,  are a life saver. However, because Containers will by default always return the same instance of the class when you get it from the container, you will need to use factories if you intend to set your events in the container while creating the class.

```php
require_once __DIR__ . '/vendor/autoload.php';

use Affinity4\Magic\Magic;
use Pimple\Container;

class Email
{
    use Magic;

    public $onEmail = [];

    public function send($to)
    {
        echo "Emailed $to\n";

        $this->onEmail($to);
    }
}

class User
{
    use Magic;

    public $onSave = [];

    public function save($id)
    {
        echo "Saved $id\n";

        $this->onSave($id);
    }
}

$Container = new Container();

$Container[User::class] = $Container->factory(function($c) {
    $User = new User;

    $User->onSave[] = function($id) use ($c) {
        echo "EVENT: Saved $id\n";

        $c[Email::class]->send('email');
    };

    return $User;
});


$Container[Email::class] = $Container->factory(function($c) {
    $Email = new Email;

    $Email->onEmail[] = function($to) {
        echo "EVENT: Emailed $to";
    };

    return $Email;
});

$Container[User::class]->onSave[] = function($id) use ($Container) {
    echo "EVENT: Saved $id\n";

    $Container[Email::class]->send('email');
};

$Container[User::class]->save(1);

// Will echo:
// Saved 1
// EVENT: Saved 1
// Emailed email
// EVENT: Emailed email
```

However, sometimes it's very powerful to have events unique to each instance. For games with multiple instances of a "Player" class, you don't want every player getting points for a kill do you?

You'll see an example of this in the "Magic Setters and Getters" section

## Magic Properties

Another enhancement the Magic trait gives you is the ability to ensure setter and getter methods are called every time you set or get a value directly from a property outside of it's defining class, whether you use the setter/getter methods or not.

Consider this academic example of a user account on a platform like StackOverflow. You have an account with reputation points. There is an event to be called once the user gets to the next "level" and gains access to new features, and so other events can be fired, like emailing them or moderators etc. 

The mistake here is that the `$reputation` property has been set to public, allowing the events to be bypassed by mistake.

let's take a look at an example of this system being used correctly:

```php
require_once __DIR__ . '/vendor/autoload.php';

use Affinity4\Magic\Magic;

class User
{
    // User model
}

class UserAccount
{
    use Magic;

    private $User;

    public $reputation = 0;

    public $level = 0;

    public $onReputationChange = [];

    public $onLevelUp = [];

    public function __construct(\User $User)
    {
        $this->User = $User;
    }

    public function setReputation(int $reputation)
    {
        $current_reputation = $this->reputation;
        // We want acces to the user model also in our event listeners
        $this->onReputationChange($current_reputation, $reputation, $this->User);

        $this->reputation = $reputation;
    }

    public function getReputation(): int
    {
        return $this->reputation;
    }

    public function setLevel(int $level)
    {
        $current_level = $this->level;

        if ($current_level < $level) {
            $this->onLevelUp($level, $this->User);
        }

        $this->level = $level;
    }

    public function getLevel(): int
    {
        return $this->level;
    }
}

$User = new User;
$UserAccount = new UserAccount($User);

$UserAccount->onReputationChange[] = function(int $current_reputation, int $new_reputation, \User $User) use ($UserAccount)
{
    // Chweck this was a reputation increase and by 10 points or more
    if ($current_reputation < $new_reputation && $new_reputation >= 10) {
        echo "Reputation increased to $new_reputation\n";

        // Make sure to use the same instance of $UserAccount
        $UserAccount->setLevel(1); // Level up to Level 1
    }
};

$UserAccount->onLevelUp[] = function(int $new_level) {
    echo "You have leveled up! You're now on Level $new_level!\n";
};

$UserAccount->setReputation(10);
// echos...
// Reputation increased to 10
// You have leveled up! You're now on Level 1!
```

__NOTE__: You can set it to 9 to verify the level up event doesn't happen if you want.

This is all well and good while things are used as expected, however, because the reputation property and the level property are left as public, the following can be done:

```php
// ....

// $UserAccount->setReputation(10);

$UserAccount->reputation = 10;

```

Nothing happens. You could even directly set the level property and nothing would happen. The system is unaware these properties changed.

Magic can fix this just by changing the properties to protected or private and adding 2 doc block attributes!

```php
/**
 * @property int $reputation
 * @property int $level
 */
class UserAccount
{
    use Magic;

    private $User;

    private $reputation = 0; // Change to private

    private $level = 0; // Change to private

   // ...the rest is uncahnged!

```

now this...

```php
$UserAccount->reputation = 10;
```

...will fire our setter events correctly.

```php
Reputation increased to 10
You have leveled up! You're now on Level 1!
```

You can still use you're setters and getters as normal of course! But if you forget to, Magic will happen and keep your system working as expected.

### Highlander game example

To show how all this can save you tons of conditional if/else/elseif code that becomes a nightmare to maintain, check out this game (or the start of one at least), based on 1986 movie The Highlander. You know, "There can be only one" and all that.

__Requirements:__

1. There must be a `Highlander` class that all players are an instance of
1. Each player starts the game with a "lifeforce" (not health related) of 10
1. When a player kills another player they absorb/gain that opponents lifeforce, whatever it may be at the time
1. We will be aware of how many highlanders are left only when we've killed another player
1. If there are still other players to defeat the player will shout "There can be only one!"

That's basically the plot of the movie :)

So, first we create a class called Highlander that uses `Affinity4\Magic\Magic` with 2 private properties `$number_of_highlanders` and `$lifeforce`. These will have setter/getter methods `set/get_number_of_highlanders` and `set/getLifeforce`. We'll add `@property` docblock attributes for `$number_of_highlanders` and `$lifeforce` to enable the magic. We'll also have a `shout` method that just echoes a phrase

```php
require_once __DIR__ . '/vendor/autoload.php';

use Affinity4\Magic\Magic;

/**
 * @property int $number_of_highlanders
 * @property int $lifeforce
 */
class Highlander
{
    use Magic;

    /**
     * @var int
     */
    private $number_of_highlanders= 3;

    /**
     * @var int
     */
    private $lifeforce = 10;

    public function setNumberOfHighlanders(int $number_of_highlanders)
    {
        $this->number_of_highlanders= $number_of_highlanders;
    }

    public function getNumberOfHighlanders(): int
    {
        return $this->number_of_highlanders;
    }

    public function setLifeforce(int $lifeforce)
    {
        $this->lifeforce = $lifeforce;
    }

    public function getLifeforce()
    {
        return $this->lifeforce;
    }

    public function shout(string $phrase)
    {
        echo $phrase;
    }
}
```

Next, we create the `kills` method, which takes in the instance of the player you killed (so you can take their lifeforce etc). It fires the `onKill` event with the defeated player passed in:

```php
require_once __DIR__ . '/vendor/autoload.php';

use Affinity4\Magic\Magic;

/**
 * @property int $lifeforce
 * @property int $number_of_highlanders
 */
class Highlander
{
    use Magic;

    private $lifeforce = 10;

    private $number_of_highlanders= 4;

    public $onKill = [];

    public function setLifeforce(int $lifeforce)
    {
        $this->lifeforce = $lifeforce;
    }

    public function getLifeforce(): int
    {
        return $this->lifeforce;
    }

    public function setNumberOfHighlanders(int $number_of_highlanders)
    {
        $this->number_of_highlanders= $number_of_highlanders;
    }

    public function getNumberOfHighlanders(): int
    {
        return $this->number_of_highlanders;
    }

    public function shout(string $phrase)
    {
        echo $phrase;
    }

    public function kill(\Highlander $Opponent)
    {
        $this->onKill($Opponent);
    }
}

$Highlander = new Highlander;
$Opponent = new Highlander;

// He killed someone along the way here. But so far only 
// he's aware there are only 3 Highlanders left, our player still thinks there are 4
--$Opponent->number_of_highlanders; 

$Highlander->onKill[] = function($Opponent) use ($Highlander) {
    $Highlander->lifeforce += $Opponent->getLifeforce();

    if ($Opponent->number_of_highlanders< $Highlander->number_of_highlanders) {
        $Highlander->number_of_highlanders= ($Opponent->number_of_highlanders- 1);
    }

    echo "You lifeforce is {$Highlander->lifeforce}!\n";
    echo "There are {$Highlander->number_of_highlanders} highlanders left\n";

    if ($Highlander->number_of_highlanders> 1) {
        $Highlander->shout("There can be only one!!!\n");
    }
};

$Highlander->kill($Opponent);

// echoes...
// There are only 2 Highlanders left
// You now have 20 lifeforce!
// There can be only one!!
```

Not only is this less than 75 lines, but no method in the Highlander class has more than 1 line of code! And it will never need to. From now on if we decide we need more to happen when someone gets killed or makes a kill we just add more event handlers! 

If that's not magic I don't what is!

## Invokable Classes as Event Handlers

While callbacks as event handlers are convenient and quick to write, they have limitations and can often encourage bad design choices.

For example, our Highlander game example in the [Magic Properties](./Magic-Properties) page, which used callbacks as event handler only had about 11 lines of code. However, it already has serious problems that will only get worse as more lines are added or more callbacks are added.

This is the event handler:

```php
$Highlander = new Highlander;
$Opponent = new Highlander;

// He killed someone along the way here. But so far only 
// he's aware there are only 3 Highlanders left, our player still thinks there are 4
--$Opponent->number_of_highlanders; 

$Highlander->onKill[] = function($Opponent) use ($Highlander) {
    $Highlander->lifeforce += $Opponent->getLifeforce();

    if ($Opponent->number_of_highlanders< $Highlander->number_of_highlanders) {
        $Highlander->number_of_highlanders= ($Opponent->number_of_highlanders- 1);
    }

    echo "You lifeforce is {$Highlander->lifeforce}!\n";
    echo "There are {$Highlander->number_of_highlanders} highlanders left\n";

    if ($Highlander->number_of_highlanders> 1) {
        $Highlander->shout("There can be only one!!!\n");
    }
};

$Highlander->kill($Opponent);
```

### Problem 1: Enforcing Types

Let's start with the first line:

```php
$Highlander->onKill[] = function($Opponent) use ($Highlander) {
```

The issue here is that we cannot enforce types. We can type hint `$Opponent` in our `Highlander::kill()` method that fires the `onKill` method, but that assumes we're passing the same value through to the `kill` method. We may in fact be passing it a generated value, that could be anything.

We're also unable to ensure `$Highlander` is actually an instance of `\Highlander`. If something else gets passed in we'll either get errors or worse, we could pass in another class with the same properties and methods that does completely unexpected things. This would mean no errors, but quite possibly hard to debug side-effects.

### Problem 2: Single Responsibility

With only a callback to add our code to, we lose the organisational benefits of OOP. It's quite easy to end up breaking SRP without even realizing, especially on projects with numerous developers.

While out code looks initially like it all belongs together, with some closer examination, we can see it's actually modifying 2 parts of our Highlander class, updating `$lifeforce` and updating `$number_of_highlanders`

```php
$Highlander->lifeforce += $Opponent->getLifeforce();
echo "You lifeforce is {$Highlander->lifeforce}!\n";

if ($Opponent->number_of_highlanders< $Highlander->number_of_highlanders) {
    $Highlander->number_of_highlanders= ($Opponent->number_of_highlanders - 1);
}

echo "There are {$Highlander->number_of_highlanders} highlanders left\n";

if ($Highlander->number_of_highlanders> 1) {
    $Highlander->shout("There can be only one!!!\n");
}
```

The first 2 lines are only dealing with the `$lifeforce` property, and should be moved out of this function. However, splitting everything out into their own callback would quickly become messy and hard to maintain. Callbacks and closures would require reading the code to determine what they are doing. If these lines were instead refactored to a class we would know what each class is for and what each method should be doing from the names (which should be clear and descriptive). We would also have everything else classes provide which callbacks do not.

### Problem 3: Organization

How should we organize all of this? Should we simply create a separate file for each event in the application and dump everything in each file? We could, but I can imagine that becoming pretty horrible after a while.

Instead, if we had autoloading and a sensible folder structure we could simply loop over autoloaded classes and add event listeners to events. This would mean creating a new class in the right folder would be all it would take to bind a handler to an event.

### Solution

Invokable classes can solve all of these problems, and give a few more perks that only OOP can provide. So let's refactor our existing code in to 2 separate event handler classes `LifeforceEventHandler` and `NumberOfHighlandersEventHandler`.

### TakeOpponentsLifeforceEventHandler

The only requirement of an invokable event handler class is the it has an `__invoke()` method with the same arguments as out callback. However, we can now do more "setup" using the constructor as well.

Our event handler would now look something like:

```php
class TakeOpponentsLifeforceEventHandler
{
    private $Highlander;

    public function __construct(\Highlander $Highlander)
    {
        $this->Highlander = $Highlander;
    }

    public function __invoke(\Highlander $Opponent)
    {
        $this->Highlander->lifeforce += $Opponent->getLifeforce();

        echo "You're lifeforce is now {$this->Highlander->lifeforce}!\n";
    }
}
```

We can now now enforce our `$Highlander` and `$Opponent` arguments are `\Highlander` instances. Really we should be using an interface here but that's up to you. 

It's also quite clear that this classes purpose is to deal with anything to do with taking your opponents lifeforce.

We could even use the Magic trait here and fire an event for other classes to subscribe to. Let's say we need to add a `SpecialAbility` feature that gives a player a random special ability after they hit 50 lifeforce points. We could simply add an event `onFiftyLifeforce` in out `__invoke()` method. Now our special ability class could subscribe to this event to do what it needs to do.

### UpdateNumberOfHighlandersEventHandler

It should be pretty obvious how to implement the `UpdateNumberOfHighlandersEventHandler` but for completeness sake let's see it

```php
class UpdateNumberOfHighlandersEventHandler
{
    private $Highlander;

    public function __construct(\Highlander $Highlander)
    {
        $this->Highlander = $Highlander;
    }

    private function decrementNumberOfHighlanders(\Highlander $Opponent)
    {
        if ($Opponent->number_Of_highlanders < $this->Highlander->number_Of_highlanders) {
            $this->Highlander->number_Of_highlanders = (--$Opponent->number_Of_highlanders);
        }
    }

    public function __invoke(\Highlander $Opponent)
    {
        $this->decrementNumberOfHighlanders($Opponent);

        echo "There are {$this->Highlander->number_Of_highlanders} highlanders left\n";
    
        if ($this->Highlander->number_Of_highlanders > 1) {
            $this->Highlander->shout("There can be only one!!!\n");
        }
    }
}
```

### Calling the Event Handlers

To attached our event handlers we simply replace the callbacks with the initialized EventHandler class, like so:

```php
$Highlander = new Highlander;
$Opponent = new Highlander;

// He killed someone along the way here. But so far only 
// he's aware there are only 3 Highlanders left, our player still thinks there are 4
--$Opponent->number_of_highlanders;

$Highlander->onKill[] = new TakeOpponentsLifeforceEventHandler($Highlander);

$Highlander->onKill[] = new UpdateNumberOfHighlandersEventHandler($Highlander);

$Highlander->kill($Opponent);
```

Internally, the invoke methods will be used and passed in our the `$Opponent` instance from the `kill()` method
