# Affinity4 Magic

Magic Trait used to easily add event listeners, spelling suggestions in errors and Javascript __set and __get style setters an getters to any class. Magic!

## Installation

```php
composer require affinity4/magic
```

## Usage

### Events

Simply include Magic in any class to instantly have event listeners!

Once you've included Magic as a trait you can then add any public "camelCased" property starting with "on". You now have an event listener! That's all it takes!

Let's say we have a Model called User

```php
class User extends Model
{
    public function register(string $username, string $email, string $password)
    {
        // ... saves to `users` table
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
    trait Magic;

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

#### Event Listeners

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

#### Containers to the Rescue!

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

