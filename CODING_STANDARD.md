# CODING STANDARD

> PHP

- Follow the [PSR2 Coding Standard](https://www.php-fig.org/psr/psr-2/)
- Using FQDN & import the :
  - `function`
  - `class/trait/interface`
  - `constant`
- Reduce unused variable name
- Php Code Sniffer Config [phpcs.xml](phpcs.xml)
- Always use validation about:
  - `variables`
  - `array[index]`
  - and code existences
- Do not use private injection to core files (`src/`) outside `src/` / core files
- Do not use global variables using register globals ```$GLOBALS/global $var```, the global object must be used static object variables.

> Performance Guide

- Focus to performance & security of the codes. Avoid to use dangerous functions & direct activity eg: `eval/passthru/exec ...etc`. 
- Does not perform direct file uploads without mime-type validation.
- Renaming extension for dangerous extension for uploaded files, eg: `.php`, `.js` etc. to safe extension like `txt` eg : `file.js.txt`
