## Views

This project uses a barebones implementation of views. Place an HTML file in this folder and then you can call it from the project as follows.

```php
// This returns a closure already pointing at this views folder
$view = expose_all()['view'];

// Then you can pull the view file forward and make any string replacements you need
$content = $view('index.html', ['{{ title }}' => 'Super awesome website!']);

// $content is a string containing the HTML content, ready to send as a response!
```

Just to clarify, the `$view` Closure in the example above can also be summoned anywhere by using the `view` function defined in the `src/functions.php` file.

But the version in `expose_all` comes set up already pointing at this folder, so it can be a bit more convenient to use.

The Closure (contained in `$view` above or by the `view` function) accepts two arguments, the filename as a string (with directory prefix) and an array of `$kv_replacements`. The latter should have the same structure as an array you would pass in as the second argument of `strtr`.

This means you can place something like `{{ title }}` anywhere in the HMTL file, allowing you to replace it with `['{{ title }}' => 'Super awesome website!']`.
