# Internationalization

We want the code to be free from German-specific texts to enable non-German speakers to use the site and to make it easier for groups in other countries to use the code.
Unfortunately, we are not quite there yet, but a lot of progress has been made and the remaining hardcoded strings are indicative of obvious legacy code.

We use the Symfony `TranslatorInterface` to replace translation keys (given in code) with their translated values. Translations are read from files in YAML format: for German that would be `/translations/messages.de.yml`.
Aside from those German strings, there are four mail ways in which fixed text on the website can be given in the source code with our translation mechanisms:

## In PHP frontend/view code
Classes derived from Control and View already contain the TranslatorInterface, which is accessible as `$this->translator`, and its `trans()` function that resolves translation keys.  
Other helpers are including it like this:

```php
use Symfony\Contracts\Translation\TranslatorInterface;
// instantiate or inject (usually autowired)
$this->translator->trans('fsp.acceptName', ['{name}' => $foodSharePoint['name']])
```
