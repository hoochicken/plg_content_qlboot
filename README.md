# plg_content_qlboot

qlboot divides an article's text into columns using the Bootstrap 5 grid (Joomla 6 ships Bootstrap 5.3).

```
{row}{span6}Lorem ipsum ...{/span}{span6}Lorem ipsum ...{/span}{/row}
```

becomes

```html
<div class="qlboot row">
<div class="col-12 col-md-6">Lorem ipsum ...</div>
<div class="col-12 col-md-6">Lorem ipsum ...</div>
</div>
```

The numbers behind `span` of one row should add up to 12. Paragraphs the editor wraps around the tags are removed.

## Attributes

```
{row type="flex" class="g-4" id="team" style="..."}{span4 class="text-center" id="" style=""}...{/span}{/row}
```

- `type`: `row` (Bootstrap grid, default) or `flex` (columns get `flex1` ... `flex12`)
- `class`, `id`, `style`: added to the row or column

## Options

- **Default**: Bootstrap row or flex
- **Breakpoint**: from which width on the columns are side by side (`sm`, `md`, `lg`, `xl`, `xxl`)
- **Load Bootstrap**: no / grid only / complete, for templates without Bootstrap
- **Use styles**: padding and text align of the columns, mobile switch for flex

## Requirements

Joomla 6, PHP 8.3+

## Development

```
composer install
vendor/bin/phpunit            # JOOMLA_ROOT=/path/to/joomla if not installed in a Joomla site
vendor/bin/php-cs-fixer fix
```

## What about a coffee ..

I love coding. My extensions are for free. Wanna say thanks? You're welcome!
<https://www.buymeacoffee.com/mareikeRiegel>
