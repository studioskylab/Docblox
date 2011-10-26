<?php
/**
 * Checkstyle Transformer File
 *
 * PHP Version 5
 *
 * @category   DocBlox
 * @package    Transformer
 * @subpackage Writers
 * @author     Jaik Dean <jaik@studioskylab.com>
 * @copyright  2010-2011 Mike van Riel / Naenius (http://www.naenius.com)
 * @license    http://www.opensource.org/licenses/mit-license.php MIT
 * @link       http://docblox-project.org
 */

/**
 * Sphinx transformation writer; generates Sphinx PHP Domain-compatible rst
 * files for incorporation into a Sphinx documentation project
 *
 * @category   DocBlox
 * @package    Transformer
 * @subpackage Writers
 * @author     Jaik Dean <jaik@studioskylab.com>
 * @license    http://www.opensource.org/licenses/mit-license.php MIT
 * @link       http://docblox-project.org
 */
class DocBlox_Plugin_Core_Transformer_Writer_Sphinx
    extends DocBlox_Transformer_Writer_Abstract
{

	/**
	 * XPath query instance
	 *
	 * @var DOMXPath
	 **/
	protected $xpath;

	/**
	 * undocumented class variable
	 *
	 * @var array
	 **/
	protected $packages;

	/**
	 * undocumented class variable
	 *
	 * @var string
	 **/
	protected $transformation;


	/**
	 * undocumented function
	 *
	 * @param DOMDocument                        $structure      XML source.
	 * @param DocBlox_Transformer_Transformation $transformation Transformation.
	 * @throws Exception
	 * @return void
	 * @author Jaik Dean
	 **/
	public function transform(DOMDocument $structure, DocBlox_Transformer_Transformation $transformation)
	{
		$this->xpath          = new DOMXPath($structure);
		$this->packages       = array();
		$this->transformation = $transformation;

		// process the interfaces
		$interfaces = $this->xpath->query('//interface[full_name]');
		foreach ($interfaces as $interface) {
			$this->formatInterface($interface);
		}

		// process the classes
		$classes = $this->xpath->query('//class[full_name]');
		foreach ($classes as $class) {
			$this->formatClass($class);
		}

		/* @todo
		foreach ($file->constant as $constant) {
			$node = $output->addChild('node');
			$node->value = (string) $constant->name;
			$node->id = $file['generated-path'] . '#::' . $node->value;
			$node->type = 'constant';
		}*/

		/* todo
		foreach ($file->function as $function) {
			$node = $output->addChild('node');
			$node->value = (string) $function->name . '()';
			$node->id = $file['generated-path'] . '#::' . $node->value;
			$node->type = 'function';
		}*/

		// generate TOC
		// @todo Create smaller TOC for each package/subpackage instead of one "super-TOC"
		$toc  = "API Documentation\n";
		$toc .= "-----------------\n\n";
		$toc .= ".. toctree::\n";
		ksort($this->packages);

		foreach ($this->packages as $package => $subpackages) {
			foreach ($subpackages as $subpackage => $elements) {
				foreach ($elements as $element => $file) {
					$toc .= "\n\t$package/$subpackage/$element";
				}
			}
		}

		$this->file_force_contents($transformation->getTransformer()->getTarget() . DIRECTORY_SEPARATOR . 'index.rst', $toc);
	}


	/**
	 * undocumented function
	 *
	 * @param DOMElement $class
	 * @return void
	 * @author Jaik Dean
	 **/
	protected function formatClass($class)
	{
		$this->formatObject($class, 'class');
	}


	/**
	 * undocumented function
	 *
	 * @param DOMElement $interface
	 * @return void
	 * @author Jaik Dean
	 **/
	protected function formatInterface($interface)
	{
		$this->formatObject($interface, 'interface');
	}


	/**
	 * undocumented function
	 *
	 * @return void
	 * @author Jaik Dean
	 **/
	protected function formatObject($object, $type = 'class')
	{
		$package          = $this->xpath->query('docblock/tag[@name="package"]', $object->parentNode);
		$package          = ($package->length ? (string) $package->item(0)->getAttribute('description') : '');
		$subpackage       = $this->xpath->query('docblock/tag[@name="subpackage"]', $object->parentNode);
		$subpackage       = ($subpackage->length ? (string) $subpackage->item(0)->getAttribute('description') : '');
		$name             = $this->xpath->evaluate('string(name[1])', $object);
		$description      = $this->formatDescription($this->xpath->evaluate('string(docblock/description[1])', $object), 1);
		$full_description = $this->formatDescription($this->xpath->evaluate('string(docblock/full_description[1])', $object), 1);

		// generate the file name
		$filename = (empty($package) ? '' : $package . DIRECTORY_SEPARATOR) . (empty($subpackage) ? '' : $subpackage . DIRECTORY_SEPARATOR) . $name . '.rst';

		// register the file
		$this->packages[$package][$subpackage][$name] = $filename;

		// build the file contents
		$contents  = $name . "\n";
		$contents .= str_repeat('-', mb_strlen($name)) . "\n\n";
		$contents .= ".. php:{$type}:: {$name}\n\n";

		if ($description)      $contents .= "\t{$description}\n\n";
		if ($full_description) $contents .= "\t{$full_description}\n\n";

		foreach ($this->xpath->query('constant', $object) as $constant) {
			$contents .= $this->formatConstant($constant);
		}

		foreach ($this->xpath->query('property', $object) as $property) {
			$contents .= $this->formatProperty($property);
		}

		foreach ($this->xpath->query('method', $object) as $method) {
			$contents .= $this->formatMethod($method);
		}

		// output the file
		$this->file_force_contents($this->transformation->getTransformer()->getTarget() . DIRECTORY_SEPARATOR . $filename, $contents);
	}


	/**
	 * Create the Sphinx PHP Domain compatibile reStructuredText for the given
	 * constant
	 *
	 * @param DOMElement $constant
	 * @return string
	 * @author Jaik Dean
	 **/
	protected function formatConstant($constant)
	{
		$name  = $this->xpath->evaluate('string(name[1])', $constant);
		$value = $this->xpath->evaluate('string(value[1])', $constant);
		return "\t.. php:const:: {$name}\n\n\t\t{$value}\n";
	}


	/**
	 * Create the Sphinx PHP Domain compatibile reStructuredText for the given
	 * property
	 *
	 * @param DOMElement $property
	 * @return string
	 * @author Jaik Dean
	 **/
	protected function formatProperty($property)
	{
		$name  = $this->xpath->evaluate('string(name[1])', $property);
		$value = $this->xpath->evaluate('string(value[1])', $property);
		return "\t.. php:attr:: {$name}\n\n\t\t{$value}\n";
	}


	/**
	 * Create the Sphinx PHP Domain compatibile reStructuredText for the given
	 * method
	 *
	 * @param DOMElement $method
	 * @return string
	 * @author Jaik Dean
	 **/
	protected function formatMethod($method)
	{
		// build the method name
		$args  = '';
		$first = true;
		foreach ($method->getElementsByTagName('argument') as $argument) {
			$default = $this->xpath->evaluate('string(default[1])', $argument);

			if (!$first) $args .= ' ';
			if (mb_strlen($default)) $args .= '[';
			if (!$first) $args .= ', ';
			$args .= $this->xpath->evaluate('string(name[1])', $argument);
			if (mb_strlen($default)) $args .= " = {$default}]";
			$first = false;
		}

		$method_name      = $this->xpath->evaluate('string(name[1])', $method) . "($args)";
		$description      = $this->formatDescription($this->xpath->evaluate('string(docblock/description[1])', $method), 2);
		$full_description = $this->formatDescription($this->xpath->evaluate('string(docblock/full_description[1])', $method), 2);

		if ($method->getAttribute('static') == 'true') {
			$contents = "\t.. php:staticmethod:: {$method_name}\n\n";
		} else {
			$contents = "\t.. php:method:: {$method_name}\n\n";
		}

		if ($description)      $contents .= "\t\t" . $description . "\n\n";
		if ($full_description) $contents .= "\t\t" . $full_description . "\n\n";

		foreach ($method->getElementsByTagName('argument') as $argument) {
			$tags = $this->xpath->query("docblock/tag[@name='param'][@variable='" . $this->xpath->evaluate('string(name[1])', $argument) . "']", $method);
			$contents .= $this->formatArgument($argument, $tags->item(0));
		}

		$return = $this->xpath->query("docblock/tag[@name='return']", $method);
		if ($return->length) {
			$contents .= "\t\t:returns: {$return->item(0)->getAttribute('description')}\n";
			$contents .= "\t\t:rtype: {$return->item(0)->getAttribute('type')}\n\n";
		}

		return "$contents\n\n";
	}


	/**
	 * Create the Sphinx PHP Domain compatibile reStructuredText for the given
	 * function/method argument
	 *
	 * @param DOMElement $argument
	 * @param DOMElement $tag
	 * @return string
	 * @author Jaik Dean
	 **/
	protected function formatArgument($argument, $tag = false)
	{
		$type        = $this->xpath->evaluate('string(type[1])', $argument);
		$name        = $this->xpath->evaluate('string(name[1])', $argument);
		if (is_object($tag)) var_dump($tag->getAttribute('description'));
		$description = (is_object($tag) ? $this->formatDescription($tag->getAttribute('description'), 3) : '');

		return "\t\t:param {$type} {$name}: {$description}\n";
	}


	/**
	 * undocumented function
	 *
	 * @param string $description
	 * @param int $indentation Indentation level for the start of new lines
	 * @return string
	 * @author Jaik Dean
	 **/
	protected function formatDescription($description, $indentation = 1)
	{
		$in = str_repeat("\t", $indentation);

		// trim
		$description = trim($description);

		// add indentation to any new lines
		$description = preg_replace('/\\n|\\r/', "\n$in", $description);

		// reformat link tags to attributes
		$description = preg_replace('/{@link ([^}:]+)::$([^}]+)}/', ':php:attr:`$1::$$2`', $description);

		// reformat link tags to methods
		$description = preg_replace('/{@link ([^}:]+)::([^}]+)(\(\))?}/', ':php:meth:`$1::$2()`', $description);

		// reformat link tags to classes
		$description = preg_replace('/{@link ([^}]+)}/', ':php:class:`$1`', $description);

		return $description;
	}


	/**
	 * Write the given contents in a file, creating any directories in the path
	 * as necessary.
	 *
	 * @param string $path
	 * @param string $contents
	 * @return void
	 * @author Jaik Dean
	 **/
	protected function file_force_contents($path, $contents)
	{
		$parts = explode('/', $path);
		$file  = array_pop($parts);
		$dir   = '';
		foreach ($parts as $part) {
		    if (!is_dir($dir .= "/$part")) {
				mkdir($dir);
			}
		}
		file_put_contents("$dir/$file", $contents);
	}

}