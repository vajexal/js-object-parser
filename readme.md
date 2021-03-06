JS object parser

[![Build Status](https://github.com/vajexal/js-object-parser/workflows/Build/badge.svg)](https://github.com/vajexal/js-object-parser/actions)
[![codecov](https://codecov.io/gh/vajexal/js-object-parser/branch/master/graph/badge.svg?token=CJRDDKU3P4)](https://codecov.io/gh/vajexal/js-object-parser)

### Installation

```bash
composer require vajexal/js-object-parser
```

### Usage

```php
<?php

declare(strict_types=1);

use Vajexal\JsObjectParser\JsObjectParser;

require_once 'vendor/autoload.php';

$jsObjectParser = new JsObjectParser();

var_dump(
    $jsObjectParser->parse('true'), // bool(true)
    $jsObjectParser->parse('123'), // int(123)
    $jsObjectParser->parse("'foo'"), // string(3) "foo"
    $jsObjectParser->parse('[1, 2]'), // array(2) { [0] => int(1) [1] => int(2) }
    $jsObjectParser->parse("{foo: 'bar'}") // array(1) { ["foo"] => string(3) "bar" }
);
```
