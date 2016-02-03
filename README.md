Silverstripe Shop Order History
======
**Silverstripe Shop Order History** add some additional versioning and history to your orders, allowing you to completely use the CMS for order management, and plugging into external communication tools (only email is supported for now).

This makes a few changes to the current order processing within the shop module, in that the main source of status logging is via the status log, rather than through the Order itself. This allows users to set up their own statuses. If you do not like this idea, you can always decorate the OrderStatusLog class and replace the field with a dropdown.

# IMPORTANT NOTE
This completely replaces the OrderStatusLog class with the OrderLog class. It is not actually used very much in the default module, I have made it more useful for my specific use cases.

## Features
A few of the features this module provides includes:

1. Full history - plugs in to Order events (and also relevant objects such as member, items, modifiers, address and payment depending on event)
2. Communication log - send emails via the CMS related to orders
3. Attach tracking number to orders
4. New front end actions for order owners
   - Forward via email
   - Print order
   - Repeat orders

## Install
Add the following to your composer.json file

```

    "require"          : {
		"milkyway-multimedia/ss-shop-order-history": "~0.3"
	}

```

## Usage

### Enabling the new front end actions
These have to be added manually as extensions to: `Milkyway\SS\Shop\OrderHistory\Actions\Handler` in your _config.yml file. By default, these add logs to the order so users/admins know what they have done.

#### Enable forward via email

```

    Milkyway\SS\Shop\OrderHistory\Actions\Handler:
      extensions:
        - Milkyway\SS\Shop\OrderHistory\Actions\ForwardViaEmail

```

#### Enable printing

```

    Milkyway\SS\Shop\OrderHistory\Actions\Handler:
      extensions:
        - Milkyway\SS\Shop\OrderHistory\Actions\Printable

```

#### Enable repeat orders

```

    Milkyway\SS\Shop\OrderHistory\Actions\Handler:
      extensions:
        - Milkyway\SS\Shop\OrderHistory\Actions\RepeatOrder

```

## License
* MIT

## Version
* Version 0.3 (Alpha)

## Contact
#### Milkyway Multimedia
* Homepage: http://milkywaymultimedia.com.au
* E-mail: mell@milkywaymultimedia.com.au
* Twitter: [@mwmdesign](https://twitter.com/mwmdesign "mwmdesign on twitter")
