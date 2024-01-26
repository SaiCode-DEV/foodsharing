<?php

namespace Flourish;

use Exception;

/**
 * An exception that allows for easy l10n, printing, tracing and hooking.
 */
class fException extends \Exception
{
	/**
	 * Callbacks for when exceptions are created.
	 *
	 * @var array
	 */
	private static array $callbacks = [];

	/**
	 * Composes text using fText if loaded.
	 *
	 * @param string $message    The message to compose
	 * @param  mixed   $component  A string or number to insert into the message
	 * @param  mixed   ...
	 *
	 * @return string  The composed and possible translated message
	 */
	protected static function compose(string $message): string
    {
		$components = array_slice(func_get_args(), 1);

		// Handles components passed as an array
		if (count($components) == 1 && is_array($components[0])) {
			$components = $components[0];
		}

		return vsprintf($message, $components);
	}

	/**
	 * Creates a string representation of any variable using predefined strings for booleans, `NULL` and empty strings.
	 *
	 * The string output format of this method is very similar to the output of
	 * [http://php.net/print_r print_r()] except that the following values
	 * are represented as special strings:
	 *
	 *  - `TRUE`: `'{true}'`
	 *  - `FALSE`: `'{false}'`
	 *  - `NULL`: `'{null}'`
	 *  - `''`: `'{empty_string}'`
	 *
	 * @param  mixed $data  The value to dump
	 *
	 * @return string  The string representation of the value
	 */
	protected static function dump(mixed $data): string
    {
        if (is_bool($data)) {
            return ($data) ? '{true}' : '{false}';
        }

        if (is_null($data)) {
return '{null}';
}

        if ($data === '') {
            return '{empty_string}';
        }

        if (is_array($data) || is_object($data)) {
            ob_start();
            var_dump($data);
            $output = ob_get_clean();

// Make the var dump more like a print_r
            $output = preg_replace('#=>\n( {2})+(?=[a-zA-Z]|&)#m', ' => ', $output);
            $output = str_replace('string(0) ""', '{empty_string}', $output);
            $output = preg_replace('#=> (&)?NULL#', '=> \1{null}', $output);
            $output = preg_replace('#=> (&)?bool\((false|true)\)#', '=> \1{\2}', $output);
            $output = preg_replace('#string\(\d+\) "#', '', $output);
            $output = preg_replace('#"(\n( {2})*)(?=[\[}])#', '\1', $output);
            $output = preg_replace('#(?:float|int)\((-?\d+(?:.\d+)?)\)#', '\1', $output);
            $output = preg_replace('#((?: {2})+)\["(.*?)"]#', '\1[\2]', $output);
            $output = preg_replace('#(?:&)?array\(\d+\) \{\n((?:  )*)((?:  )(?=\[)|(?=\}))#', "Array\n\\1(\n\\1\\2", $output);
            $output = preg_replace('/object\((\w+)\)#\d+ \(\d+\) {\n((?:  )*)((?:  )(?=\[)|(?=\}))/', "\\1 Object\n\\2(\n\\2\\3", $output);
            $output = preg_replace('#^((?: {2})+)}(?=\n|$)#m', "\\1)\n", $output);
            $output = substr($output, 0, -2) . ')';

            // Fix indenting issues with the var dump output
            $output_lines = explode("\n", $output);
            $new_output = [];
            $stack = 0;
            foreach ($output_lines as $line) {
                if (preg_match('#^((?: {2})*)([^ ])#', $line, $match)) {
                    $spaces = strlen($match[1]);
                    if ($spaces && $match[2] == '(') {
                        ++$stack;
                    }
                    $new_output[] = str_pad('', ($spaces) + (4 * $stack)) . $line;
                    if ($spaces && $match[2] == ')') {
                        --$stack;
                    }
                } else {
                    $new_output[] = str_pad('', ($spaces) + (4 * $stack)) . $line;
                }
            }

            return implode("\n", $new_output);
        }

        return (string)$data;
    }


    /**
	 * Sets the message for the exception, allowing for string interpolation and internationalization.
	 *
	 * The `$message` can contain any number of formatting placeholders for
	 * string and number interpolation via [http://php.net/sprintf `sprintf()`].
	 * Any `%` signs that do not appear to be part of a valid formatting
	 * placeholder will be automatically escaped with a second `%`.
	 *
	 * The following aspects of valid `sprintf()` formatting codes are not
	 * accepted since they are redundant and restrict the non-formatting use of
	 * the `%` sign in exception messages:
	 *  - `% 2d`: Using a literal space as a padding character - a space will be used if no padding character is specified
	 *  - `%'.d`: Providing a padding character but no width - no padding will be applied without a width
	 *
	 * @param  string $message    The message for the exception. This accepts a subset of [http://php.net/sprintf
     * `sprintf()`] strings - see method description for more details.
	 * @param  mixed  $component  A string or number to insert into the message
	 * @param  mixed  ...
	 * @param  mixed  $code       The exception code to set
	 *
	 * @return fException
	 */
	public function __construct($message = '')
	{
		$args = array_slice(func_get_args(), 1);
		$required_args = preg_match_all(
			'/
				(?<!%)                       # Ensure this is not an escaped %
				%(                           # The leading %
				  (?:\d+\$)?                 # Position
				  \+?                        # Sign specifier
				  (?:(?:0|\'.)?-?\d+|-?)     # Padding, alignment and width or just alignment
				  (?:\.\d+)?				 # Precision
				  [bcdeufFosxX]              # Type
				)/x',
			$message,
			$matches
		);

		// Handle %s that weren't properly escaped
		$formats = $matches[1];
		$delimiters = ($formats) ? array_fill(0, count($formats), '#') : [];
		$lookahead = implode(
			'|',
			array_map(
				'preg_quote',
				$formats,
				$delimiters
			)
		);
		$lookahead = ($lookahead) ? '|' . $lookahead : '';
		$message = preg_replace('#(?<!%)%(?!%' . $lookahead . ')#', '%%', $message);

		// If we have an extra argument, it is the exception code
		$code = null;
		if ($required_args == count($args) - 1) {
			$code = array_pop($args);
		}

		if (count($args) != $required_args) {
			$message = self::compose(
				'%1$d components were passed to the %2$s constructor, while %3$d were specified in the message',
				count($args),
				static::class,
				$required_args
			);
			throw new Exception($message);
		}

		$args = array_map([self::class, 'dump'], $args);

		parent::__construct(self::compose($message, $args));
		$this->code = $code;

		foreach (self::$callbacks as $class => $callbacks) {
			foreach ($callbacks as $callback) {
				if ($this instanceof $class) {
					$callback($this);
				}
			}
		}
	}

	/**
	 * All requests that hit this method should be requests for callbacks.
	 *
	 * @param  string $method  The method to create a callback for
	 *
	 * @return callback  The callback for the method requested
	 *@internal
	 *
	 */
	public function __get(string $method)
	{
		return [$this, $method];
	}

	/**
	 * Prepares content for output into HTML.
	 *
	 * @return string  The prepared content
	 */
	protected function prepare($content)
	{
		// See if the message has newline characters but not br tags, extracted from fHTML to reduce dependencies
		static $inline_tags_minus_br = '<a><abbr><acronym><b><big><button><cite><code><del><dfn><em><font><i><img><input><ins><kbd><label><q><s><samp><select><small><span><strike><strong><sub><sup><textarea><tt><u><var>';
		$content_with_newlines = (strip_tags($content, $inline_tags_minus_br)) ? $content : nl2br($content);

		// Check to see if we have any block-level html, extracted from fHTML to reduce dependencies
		$inline_tags = $inline_tags_minus_br . '<br>';
		$no_block_html = strip_tags($content, $inline_tags) == $content;

		// This code ensures the output is properly encoded for display in (X)HTML, extracted from fHTML to reduce dependencies
		$reg_exp = "/<\s*\/?\s*[\w:]+(?:\s+[\w:]+(?:\s*=\s*(?:\"[^\"]*?\"|'[^']*?'|[^'\">\s]+))?)*\s*\/?\s*>|&(?:#\d+|\w+);|<\!--.*?-->/";
		preg_match_all($reg_exp, $content, $html_matches, PREG_SET_ORDER);
		$text_matches = preg_split($reg_exp, $content_with_newlines);

		foreach ($text_matches as $key => $value) {
			$value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
		}

		for ($i = 0; $i < sizeof($html_matches); ++$i) {
			$text_matches[$i] .= $html_matches[$i][0];
		}

		$content_with_newlines = implode('', $text_matches);

		$output = ($no_block_html) ? '<p>' : '';
		$output .= $content_with_newlines;
		$output .= ($no_block_html) ? '</p>' : '';

		return $output;
	}
}
