# Company icons

This directory contains 500 colored SVG brand icons. Most come from [Simple Icons](https://simpleicons.org/), version 16.26.0, and use the brand color from its metadata. Reqable is included from its official GitHub organization because it is not available in Simple Icons.

- Use an icon directly with `/public/company-icons/{slug}.svg`.
- Use `manifest.json` to resolve display names, filenames, brand colors and original sources.
- Simple Icons is distributed under CC0-1.0. Additional official assets retain their respective rights. Brand names and logos remain trademarks of their respective owners; follow each brand's usage guidelines.

Regenerate the directory from an extracted Simple Icons package:

```sh
php scripts/sync-company-icons.php /path/to/simple-icons/package
```
