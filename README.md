# phpLindung

It is a simple script to provide a simple login page for PHP. It is also configurable to have polymorphic features by randomizing view on client source code such as variable, spacing, capitalization and garbage code.

The code are PoC only. It is based on Zubrag.com code with extensive modification:
http://www.zubrag.com/scripts/password-protect.php

NOTE: Lindung in Malay mean Protect.

## Usage
Just simply put the following code on top of your code:
```
<?php include "lindung.php"; ?>
```
And your page will be lock until user input a correct username/password.

![1](https://user-images.githubusercontent.com/1006000/39636860-06ed20ae-4ff4-11e8-8cc7-1efce260190f.png)

![2018-05-04_23-34-12](https://user-images.githubusercontent.com/1006000/39636797-d475c928-4ff3-11e8-9d5e-851e128744be.gif)

Please refer to the source code comments for more information about the usage.

## Features
1. Add random spaces
2. Add random capitalization
3. Add random newline
4. Add random hidden garbage code
5. Random variable.
6. Minimalistic UI.

## Limitation
1. Some browser may not support on certain types of randomization.
2. Not tested on mobile phone. Expect some bugs.
3. Not suitable for commercial usage. Need more secure approaches.
4. Random capitalization letter may not work well on URL and hotlinks.
5. Tested on PHP 5-5.6 only

## License
1. GNU General Public License v3.0
2. Meh, feel free to modify and use.

## References
1. http://www.zubrag.com/scripts/password-protect.php
